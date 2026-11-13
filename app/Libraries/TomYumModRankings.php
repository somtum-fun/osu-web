<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\Beatmap;
use App\Models\Solo\Score;
use App\Models\User;
use Cache;

/**
 * TomYum: rankings of plays with one mod (EZ, FL, TD): each player's best pp per map among their
 * passed plays that include the mod (EZ+HD counts for EZ), weighted the way TomYum weights pp
 * (0.95^n plus bonus pp). Built from osu-web's `scores`, which holds stable plays (copied from
 * TomYum) and lazer's own, so both clients count. Kept for a few minutes rather than stored.
 */
class TomYumModRankings
{
    /** Ruleset → variant (the rankings' `variant` parameter) → mod acronym. */
    const VARIANTS = [
        'osu' => ['ez' => 'EZ', 'fl' => 'FL', 'td' => 'TD'],
    ];

    const CACHE_SECONDS = 300;

    public static function acronym(string $mode, ?string $variant): ?string
    {
        return static::VARIANTS[$mode][$variant ?? ''] ?? null;
    }

    /**
     * The same shape as TomYum's /rankings/{mode} (RankingController::tomyumRanking reads it):
     * total, countries, rows of {userId, pp, accuracy (0–1), playCount, rankedScore, ss, s, a}.
     */
    public static function ranking(string $mode, string $acronym, string $sort, ?string $country, ?User $friendsOf, int $offset, int $limit): array
    {
        $all = static::all($mode, $acronym);
        $byScore = $sort === 'score';
        $rows = array_filter($all, fn ($row) => $byScore ? $row['rankedScore'] > 0 : $row['pp'] > 0);

        $countries = array_values(array_unique(array_filter(array_column($rows, 'country'))));
        sort($countries);

        if ($friendsOf !== null) {
            $circle = $friendsOf->friends()->pluck('zebra_id')->push($friendsOf->getKey())->flip();
            $rows = array_filter($rows, fn ($row) => isset($circle[$row['userId']]));
        }
        if ($country !== null) {
            $rows = array_filter($rows, fn ($row) => strcasecmp($row['country'] ?? '', $country) === 0);
        }

        $key = $byScore ? 'rankedScore' : 'pp';
        usort($rows, fn ($a, $b) => $b[$key] <=> $a[$key]);

        return [
            'total' => count($rows),
            'countries' => $countries,
            'rows' => array_slice($rows, max(0, $offset), $limit),
        ];
    }

    /** Every ranked player's row for the mod, unsorted (cached). */
    private static function all(string $mode, string $acronym): array
    {
        return Cache::remember("tomyum_mod_ranking:{$mode}:{$acronym}", static::CACHE_SECONDS, function () use ($mode, $acronym) {
            $scores = Score::where('ruleset_id', Beatmap::MODES[$mode])
                ->where('passed', true)
                ->where('pp', '>', 0)
                ->whereRaw("JSON_CONTAINS(data->'$.mods', ?)", [json_encode(['acronym' => $acronym])])
                ->get();

            $users = User::default()
                ->whereIn('user_id', $scores->pluck('user_id')->unique())
                ->pluck('country_acronym', 'user_id');

            $rows = [];
            foreach ($scores->groupBy('user_id') as $userId => $plays) {
                if (!isset($users[$userId])) {
                    continue; // restricted
                }

                $byMap = $plays->groupBy('beatmap_id');
                // Best pp on each map, best first.
                $bests = $byMap->map(fn ($p) => $p->sortByDesc('pp')->first())->sortByDesc('pp')->values();
                $weightedPp = 0.0;
                $weightedAcc = 0.0;
                foreach ($bests as $i => $best) {
                    $weightedPp += $best->pp * 0.95 ** $i;
                    $weightedAcc += $best->accuracy * 0.95 ** $i;
                }
                $n = count($bests);
                $grades = $bests->countBy(fn ($s) => $s->rank);

                $rows[] = [
                    'userId' => (int) $userId,
                    'country' => $users[$userId],
                    'pp' => $weightedPp + 416.6667 * (1 - 0.9994 ** $n),
                    'accuracy' => $weightedAcc / (20 * (1 - 0.95 ** $n)),
                    'playCount' => count($plays),
                    // Highest classic score on each map (stable plays keep their own score).
                    'rankedScore' => $byMap->sum(fn ($p) => $p->map(fn ($s) => static::classicScore($s))->max()),
                    'ss' => ($grades['X'] ?? 0) + ($grades['XH'] ?? 0),
                    's' => ($grades['S'] ?? 0) + ($grades['SH'] ?? 0),
                    'a' => $grades['A'] ?? 0,
                ];
            }

            return $rows;
        });
    }

    private static function classicScore(Score $score): int
    {
        if ($score->legacy_score_id !== null) {
            return (int) ($score->legacy_total_score ?: $score->total_score);
        }

        return $score->getClassicTotalScore();
    }
}
