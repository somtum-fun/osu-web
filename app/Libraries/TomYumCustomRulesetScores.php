<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\Beatmap;
use App\Models\Solo\Score;
use App\Models\TomYumCustomRuleset;
use App\Models\User;
use App\Transformers\ScoreTransformer;
use Cache;
use DB;

/**
 * TomYum: custom lazer rulesets' leaderboards and rankings (TomYumCustomRulesets), from the website's own scores, by
 * score: a map's board has each player's best (passed, ranked map, no automation mods), and the rankings add up
 * everyone's best on each ranked map (ranked score). Restricted players aren't on them.
 */
class TomYumCustomRulesetScores
{
    const BOARD_SIZE = 50;

    const RANKINGS_PAGE = 50;

    /**
     * A map's board for lazer and the beatmap page, shaped like the website's own (scores, user_score).
     *
     * @param string[] $mods Acronyms: only plays with exactly these mods ('NM': none); empty: all.
     */
    public static function beatmapLeaderboard(Beatmap $beatmap, TomYumCustomRuleset $ruleset, string $type, array $mods, array $includes, string $variant = 'vanilla'): array
    {
        if ($beatmap->approved <= 0 || (int) $beatmap->playmode !== Beatmap::MODES['osu']) {
            return ['scores' => []];
        }

        $viewer = auth()->user();
        $userIds = match ($type) {
            'country' => $viewer === null ? [] : User::default()->where('country_acronym', $viewer->country_acronym)->pluck('user_id')->all(),
            'friend' => $viewer === null ? [] : [...$viewer->friends()->pluck('zebra_id'), $viewer->getKey()],
            'team' => $viewer?->team?->members()->pluck('user_id')->all() ?? [],
            default => null,
        };
        if ($userIds === []) {
            return ['scores' => []];
        }

        $best = static::bestPerUser($beatmap->getKey(), $ruleset->id, $userIds, $mods, $variant);
        $top = array_slice($best, 0, static::BOARD_SIZE);
        $scores = Score::with(['beatmap', 'user.country', 'user.team'])->whereIn('id', array_column($top, 'id'))->get()->keyBy('id');
        $transformer = new ScoreTransformer(false);

        $result = [
            'scores' => array_values(array_filter(array_map(
                fn ($row) => isset($scores[$row['id']]) ? json_item($scores[$row['id']], $transformer, $includes) : null,
                $top,
            ))),
            'score_count' => count($best),
        ];

        if ($viewer !== null) {
            foreach ($best as $position => $row) {
                if ($row['user_id'] === $viewer->getKey()) {
                    $mine = $scores[$row['id']] ?? Score::with(['beatmap', 'user.country', 'user.team'])->find($row['id']);
                    if ($mine !== null) {
                        $result['user_score'] = ['position' => $position + 1, 'score' => json_item($mine, $transformer, $includes)];
                    }
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Everyone's ranked score in a custom ruleset (their best on each ranked map, added up), highest first, kept five
     * minutes: [['user_id', 'ranked_score', 'maps', 'best'], ...] for one page, and how many players there are.
     *
     * @return array{rows: array<int, array{user_id: int, ranked_score: int, maps: int, best: int}>, total: int}
     */
    public static function rankings(TomYumCustomRuleset $ruleset, ?string $country, int $page, string $variant = 'vanilla'): array
    {
        $all = static::allRanked($ruleset, $country, $variant);

        return [
            'rows' => array_slice($all, max(0, $page - 1) * static::RANKINGS_PAGE, static::RANKINGS_PAGE),
            'total' => count($all),
        ];
    }

    /**
     * A page of a custom ruleset's rankings as the rankings API answers for a ruleset (TumThai's rankings overlay asks
     * rankings/{slug}/score): each player's statistics in it (ranked score; play count and total score of every play;
     * accuracy and grades of their best per ranked map) with their user, country, cover and team. No pp or level.
     */
    public static function apiRankings(TomYumCustomRuleset $ruleset, ?string $country, int $page, string $variant = 'vanilla'): array
    {
        $rankings = static::rankings($ruleset, $country, $page, $variant);
        $rows = $rankings['rows'];
        $ids = array_column($rows, 'user_id');
        $users = User::with(['country', 'team'])->whereIn('user_id', $ids)->get()->keyBy('user_id');

        $plays = [];
        $best = [];
        if ($ids !== []) {
            $in = implode(',', array_map('intval', $ids));
            $variantSql = TomYumCustomRulesets::variantSql($variant);
            foreach (DB::select(
                "SELECT s.user_id, COUNT(*) AS plays, CAST(COALESCE(SUM(s.total_score), 0) AS SIGNED) AS total, COALESCE(MAX(s.max_combo), 0) AS combo
                FROM scores s WHERE s.ruleset_id = ? AND s.user_id IN ({$in}) AND {$variantSql} GROUP BY s.user_id",
                [$ruleset->id],
            ) as $row) {
                $plays[(int) $row->user_id] = $row;
            }
            foreach (DB::select(
                "SELECT b.user_id, AVG(b.accuracy) AS accuracy,
                  SUM(b.grade = 'XH') AS ssh, SUM(b.grade = 'X') AS ss, SUM(b.grade = 'SH') AS sh, SUM(b.grade = 'S') AS s, SUM(b.grade = 'A') AS a
                FROM (
                  SELECT s.user_id, s.accuracy, s.rank AS grade,
                    ROW_NUMBER() OVER (PARTITION BY s.user_id, s.beatmap_id ORDER BY s.total_score DESC, s.id) AS place
                  FROM scores s
                  WHERE s.ruleset_id = ? AND s.user_id IN ({$in}) AND s.passed = 1 AND s.preserve = 1 AND s.ranked = 1 AND {$variantSql}
                ) b WHERE b.place = 1 GROUP BY b.user_id",
                [$ruleset->id],
            ) as $row) {
                $best[(int) $row->user_id] = $row;
            }
        }

        $ranking = [];
        $first = (max(1, $page) - 1) * static::RANKINGS_PAGE;
        foreach ($rows as $index => $row) {
            $user = $users[$row['user_id']] ?? null;
            if ($user === null) {
                continue;
            }
            $p = $plays[$row['user_id']] ?? null;
            $b = $best[$row['user_id']] ?? null;
            $accuracy = (float) ($b->accuracy ?? 0);
            $ranking[] = [
                'accuracy' => $accuracy,
                'count_100' => 0,
                'count_300' => 0,
                'count_50' => 0,
                'count_miss' => 0,
                'global_rank' => $first + $index + 1,
                'global_rank_exp' => null,
                'grade_counts' => [
                    'a' => (int) ($b->a ?? 0),
                    's' => (int) ($b->s ?? 0),
                    'sh' => (int) ($b->sh ?? 0),
                    'ss' => (int) ($b->ss ?? 0),
                    'ssh' => (int) ($b->ssh ?? 0),
                ],
                'hit_accuracy' => round($accuracy * 100, 2),
                'is_ranked' => true,
                'level' => ['current' => 0, 'progress' => 0],
                'maximum_combo' => (int) ($p->combo ?? 0),
                'play_count' => (int) ($p->plays ?? 0),
                'play_time' => 0,
                'pp' => 0,
                'pp_exp' => 0,
                'ranked_score' => $row['ranked_score'],
                'replays_watched_by_others' => 0,
                'total_hits' => 0,
                'total_score' => (int) ($p->total ?? 0),
                'user' => json_item($user, new \App\Transformers\UserCompactTransformer(), ['country', 'cover', 'team']),
            ];
        }

        $hasMore = $first + count($rows) < $rankings['total'];

        return [
            ...cursor_for_response($hasMore ? ['page' => $page + 1] : null),
            'ranking' => $ranking,
            'total' => $rankings['total'],
        ];
    }

    /**
     * A player's standing in a custom ruleset (what TumThai shows on their card): global and country rank (null when
     * unranked), ranked score, ranked maps played and their best play.
     */
    public static function standing(TomYumCustomRuleset $ruleset, User $user, string $variant = 'vanilla'): array
    {
        $find = function (array $rows) use ($user) {
            foreach ($rows as $index => $row) {
                if ($row['user_id'] === $user->getKey()) {
                    return [$index + 1, $row];
                }
            }

            return [null, null];
        };
        [$globalRank, $row] = $find(static::allRanked($ruleset, null, $variant));
        $countryRank = $row !== null && $user->country_acronym !== null
            ? $find(static::allRanked($ruleset, $user->country_acronym, $variant))[0]
            : null;

        return [
            // The same names as a user's statistics (TumThai reads it as UserStatistics).
            'is_ranked' => $globalRank !== null,
            'pp' => 0,
            'global_rank' => $globalRank,
            'country_rank' => $countryRank,
            'ranked_score' => $row['ranked_score'] ?? 0,
            'maps' => $row['maps'] ?? 0,
            'best' => $row['best'] ?? 0,
        ];
    }

    /**
     * A player's scores in a custom ruleset, for their profile (the website's and TumThai's): best (their best on each
     * ranked map, by score), firsts (those that are the map's #1) or recent (the last 24 hours on that board, newest
     * first; failed plays only when asked). The page's scores as the profile shows them, and how many there are.
     *
     * @return array{items: array, total: int}
     */
    public static function profileScores(User $user, TomYumCustomRuleset $ruleset, string $type, int $offset, int $limit, bool $includeFails = false, string $variant = 'vanilla'): array
    {
        $variantSql = TomYumCustomRulesets::variantSql($variant);
        $bindings = [$user->getKey(), $ruleset->id];

        if ($type === 'recent') {
            $ids = array_map(fn ($r) => (int) $r->id, DB::select(
                'SELECT s.id FROM scores s
                WHERE s.user_id = ? AND s.ruleset_id = ? AND s.ended_at > NOW() - INTERVAL 1 DAY AND '.$variantSql.($includeFails ? '' : ' AND s.passed = 1').'
                ORDER BY s.ended_at DESC, s.id DESC
                LIMIT 1000',
                $bindings,
            ));
        } elseif ($type === 'best' || $type === 'firsts') {
            $best = "SELECT id, beatmap_id, total_score FROM (
                  SELECT s.id, s.beatmap_id, s.total_score,
                    ROW_NUMBER() OVER (PARTITION BY s.beatmap_id ORDER BY s.total_score DESC, s.id) AS place
                  FROM scores s
                  WHERE s.user_id = ? AND s.ruleset_id = ? AND s.passed = 1 AND s.preserve = 1 AND s.ranked = 1 AND {$variantSql}
                ) b WHERE place = 1";
            if ($type === 'firsts') {
                $otherVariant = TomYumCustomRulesets::variantSql($variant, 'o');
                $best = "SELECT * FROM ({$best}) mine WHERE NOT EXISTS (
                    SELECT 1 FROM scores o
                    JOIN phpbb_users u ON u.user_id = o.user_id AND u.user_warnings = 0 AND u.user_type = 0
                    WHERE o.beatmap_id = mine.beatmap_id AND o.ruleset_id = ? AND o.passed = 1 AND o.preserve = 1 AND o.ranked = 1
                      AND o.user_id <> ? AND {$otherVariant}
                      AND (o.total_score > mine.total_score OR (o.total_score = mine.total_score AND o.id < mine.id))
                )";
                array_push($bindings, $ruleset->id, $user->getKey());
            }
            $ids = array_map(fn ($r) => (int) $r->id, DB::select("{$best} ORDER BY total_score DESC, id LIMIT 1000", $bindings));
        } else {
            return ['items' => [], 'total' => 0];
        }

        $page = array_slice($ids, max(0, $offset), max(0, $limit));
        $scores = Score::with(\App\Transformers\ScoreTransformer::USER_PROFILE_INCLUDES_PRELOAD)->whereIn('id', $page)->get()->keyBy('id');
        $transformer = new ScoreTransformer();
        $items = [];
        foreach ($page as $id) {
            if (isset($scores[$id])) {
                $scores[$id]->setRelation('user', $user);
                $items[] = json_item($scores[$id], $transformer, \App\Transformers\ScoreTransformer::USER_PROFILE_INCLUDES);
            }
        }

        return ['items' => $items, 'total' => count($ids)];
    }

    /**
     * A player's statistics in a custom ruleset, shaped as a user's statistics (the profile shows them as it would a
     * ruleset's): rank and ranked score from the rankings, play count and total score of every play, accuracy and
     * grades of their best on each ranked map. No pp, level or play time.
     */
    public static function profileStatistics(User $user, TomYumCustomRuleset $ruleset, array $base, string $variant = 'vanilla'): array
    {
        $standing = static::standing($ruleset, $user, $variant);
        $variantSql = TomYumCustomRulesets::variantSql($variant);
        $all = DB::selectOne(
            "SELECT COUNT(*) AS plays, CAST(COALESCE(SUM(s.total_score), 0) AS SIGNED) AS total, COALESCE(MAX(s.max_combo), 0) AS combo
            FROM scores s WHERE s.user_id = ? AND s.ruleset_id = ? AND {$variantSql}",
            [$user->getKey(), $ruleset->id],
        );
        $best = DB::selectOne(
            "SELECT COALESCE(AVG(b.accuracy), 0) AS accuracy,
              SUM(b.grade = 'XH') AS ssh, SUM(b.grade = 'X') AS ss, SUM(b.grade = 'SH') AS sh, SUM(b.grade = 'S') AS s, SUM(b.grade = 'A') AS a
            FROM (
              SELECT s.accuracy, s.rank AS grade,
                ROW_NUMBER() OVER (PARTITION BY s.beatmap_id ORDER BY s.total_score DESC, s.id) AS place
              FROM scores s
              WHERE s.user_id = ? AND s.ruleset_id = ? AND s.passed = 1 AND s.preserve = 1 AND s.ranked = 1 AND {$variantSql}
            ) b WHERE b.place = 1",
            [$user->getKey(), $ruleset->id],
        );

        return [
            ...$base,
            'count_100' => 0,
            'count_300' => 0,
            'count_50' => 0,
            'count_miss' => 0,
            'country_rank' => $standing['country_rank'],
            'global_rank' => $standing['global_rank'],
            'global_rank_exp' => null,
            'grade_counts' => [
                'a' => (int) ($best->a ?? 0),
                's' => (int) ($best->s ?? 0),
                'sh' => (int) ($best->sh ?? 0),
                'ss' => (int) ($best->ss ?? 0),
                'ssh' => (int) ($best->ssh ?? 0),
            ],
            'hit_accuracy' => round((float) ($best->accuracy ?? 0) * 100, 2),
            'is_ranked' => $standing['global_rank'] !== null,
            'level' => ['current' => 0, 'progress' => 0],
            'maximum_combo' => (int) ($all->combo ?? 0),
            'play_count' => (int) ($all->plays ?? 0),
            'play_time' => 0,
            'pp' => 0,
            'pp_exp' => 0,
            'rank' => ['country' => $standing['country_rank']],
            'ranked_score' => $standing['ranked_score'],
            'replays_watched_by_others' => 0,
            'total_hits' => 0,
            'total_score' => (int) ($all->total ?? 0),
            'variants' => null,
        ];
    }

    /** Everyone ranked in a custom ruleset (best per ranked map, added up), highest first, kept five minutes. */
    private static function allRanked(TomYumCustomRuleset $ruleset, ?string $country, string $variant): array
    {
        $variantSql = TomYumCustomRulesets::variantSql($variant);
        return Cache::remember("tomyum:custom-ruleset-rankings:{$ruleset->id}:{$variant}:".($country ?? ''), 300, function () use ($ruleset, $country, $variantSql) {
            $rows = DB::select(
                'SELECT b.user_id, CAST(SUM(b.best) AS SIGNED) AS ranked_score, COUNT(*) AS maps, CAST(MAX(b.best) AS SIGNED) AS best
                FROM (
                  SELECT s.user_id, s.beatmap_id, MAX(s.total_score) AS best
                  FROM scores s
                  WHERE s.ruleset_id = ? AND s.passed = 1 AND s.preserve = 1 AND s.ranked = 1 AND '.$variantSql.'
                  GROUP BY s.user_id, s.beatmap_id
                ) b
                JOIN phpbb_users u ON u.user_id = b.user_id AND u.user_warnings = 0 AND u.user_type = 0'.($country !== null ? ' AND u.country_acronym = ?' : '').'
                GROUP BY b.user_id
                ORDER BY ranked_score DESC, b.user_id
                LIMIT 10000',
                $country !== null ? [$ruleset->id, $country] : [$ruleset->id],
            );

            return array_map(fn ($r) => [
                'user_id' => (int) $r->user_id,
                'ranked_score' => (int) $r->ranked_score,
                'maps' => (int) $r->maps,
                'best' => (int) $r->best,
            ], $rows);
        });
    }

    /**
     * Each player's best play of the map in the ruleset (by score, the earlier play on a tie), best first:
     * [['id', 'user_id', 'total_score'], ...].
     */
    private static function bestPerUser(int $beatmapId, int $rulesetId, ?array $userIds, array $mods, string $variant = 'vanilla'): array
    {
        $bindings = [$beatmapId, $rulesetId];
        $filter = ' AND '.TomYumCustomRulesets::variantSql($variant);
        if ($userIds !== null) {
            $userIds = array_values(array_unique(array_map('intval', $userIds)));
            $filter .= ' AND s.user_id IN ('.implode(',', array_fill(0, count($userIds), '?')).')';
            array_push($bindings, ...$userIds);
        }
        // As the ruleset spells them (Sentakki's are "Ex", "C"...); NM is no mods.
        $mods = array_values(array_unique(array_filter(array_map(fn ($m) => (string) $m, $mods), fn ($m) => preg_match('/^[A-Za-z0-9]{1,8}$/', $m) === 1)));
        if ($mods !== []) {
            $wanted = array_values(array_diff($mods, ['NM']));
            // On a Relax/Autopilot board every play has that mod: it's part of the match.
            $boardMod = ['relax' => 'RX', 'autopilot' => 'AP'][$variant] ?? null;
            if ($boardMod !== null && !in_array($boardMod, $wanted, true)) {
                $wanted[] = $boardMod;
            }
            $filter .= ' AND JSON_LENGTH(JSON_EXTRACT(s.data, \'$.mods\')) = ?';
            $bindings[] = count($wanted);
            foreach ($wanted as $acronym) {
                $filter .= ' AND JSON_CONTAINS(JSON_EXTRACT(s.data, \'$.mods[*].acronym\'), JSON_QUOTE(?))';
                $bindings[] = $acronym;
            }
        }

        $rows = DB::select(
            "SELECT id, user_id, total_score FROM (
              SELECT s.id, s.user_id, s.total_score,
                ROW_NUMBER() OVER (PARTITION BY s.user_id ORDER BY s.total_score DESC, s.id) AS place
              FROM scores s
              JOIN phpbb_users u ON u.user_id = s.user_id AND u.user_warnings = 0 AND u.user_type = 0
              WHERE s.beatmap_id = ? AND s.ruleset_id = ? AND s.passed = 1 AND s.preserve = 1 AND s.ranked = 1{$filter}
            ) best WHERE place = 1 ORDER BY total_score DESC, id",
            $bindings,
        );

        return array_map(fn ($r) => ['id' => (int) $r->id, 'user_id' => (int) $r->user_id, 'total_score' => (int) $r->total_score], $rows);
    }
}
