<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\Beatmap;
use App\Models\User;
use DB;
use Illuminate\Database\QueryException;

/**
 * TomYum: osu!dan. The courses, their maps, attempts and clears are the tomyum.dan plugin's tables in
 * TomYum's database (tomyum.plugin_dan_*), which the plugin creates when it first starts. The game
 * server watches attempts and records clears; the website shows them (Community → osu!dan, a line on
 * profiles) and its staff panel edits the courses, which the plugin reads again within a minute.
 */
class TomYumDan
{
    /** The legacy mod bits a course may allow: [bit, acronym, name]. */
    const MODS = [
        [1, 'NF', 'No Fail'],
        [2, 'EZ', 'Easy'],
        [8, 'HD', 'Hidden'],
        [1048576, 'FI', 'Fade In'],
        [16, 'HR', 'Hard Rock'],
        [32, 'SD', 'Sudden Death'],
        [16384, 'PF', 'Perfect'],
        [64, 'DT', 'Double Time'],
        [512, 'NC', 'Nightcore'],
        [256, 'HT', 'Half Time'],
        [1024, 'FL', 'Flashlight'],
        [1073741824, 'MR', 'Mirror'],
        [2097152, 'RD', 'Random'],
    ];

    /** DanReform's mods: MR, HD, FI, FL, HR, DT, NC (the plugin's default). */
    const DEFAULT_MODS = 8 | 16 | 64 | 512 | 1024 | 1048576 | 1073741824;

    const RESULTS = [
        'clear' => 'Clear',
        'failed' => 'Failed',
        'paused' => 'Paused',
        'unwatched' => 'Not watched',
        'old_client' => 'Old client',
        'mode' => 'Not osu!mania',
        'mods' => 'Mods not allowed',
        'accuracy' => 'Accuracy too low',
    ];

    /**
     * Why osu!lazer (TumThai) won't submit a play of a dan map: the score token is refused with it
     * (422), and the game shows it under "Score will not be submitted", as it does when the client
     * isn't an official build. The game server only watches osu!(stable) plays for dan rules.
     */
    const LAZER_REFUSED = 'This beatmap is part of osu!dan, which can\'t be played in multiplayer or playlists. Play it on its own instead.';

    /** TomYum: a TumThai play of a dan map without Classic (it judges hold notes close to osu!(stable)). */
    const LAZER_NEEDS_CLASSIC = 'This beatmap is part of osu!dan: on TumThai, turn on the Classic (CL) mod to submit your play.';

    /** TomYum: a paused TumThai play of a dan map (osu!dan doesn't allow pausing; the map's leaderboard is for dan plays). */
    const LAZER_PAUSED = 'osu!dan doesn\'t allow pausing, so this play wasn\'t submitted.';

    /** TomYum: a TumThai from before it sent its mods with the score token can't tell us whether Classic is on. */
    const LAZER_UPDATE = 'This beatmap is part of osu!dan: update TumThai to submit your play here.';

    /** A plugin table in TomYum's database (`tomyum`, like tomyum.plugin_votes and tomyum.discord_links). */
    public static function table(string $name): string
    {
        return 'tomyum.plugin_dan_'.$name;
    }

    /** Whether the plugin has made its tables yet (it does on its first start). */
    public static function available(): bool
    {
        try {
            DB::table(static::table('courses'))->limit(1)->get();

            return true;
        } catch (QueryException) {
            return false;
        }
    }

    /** Whether the beatmap is in an active dan course (false when the plugin has no tables yet). */
    public static function isCourseMap(int $beatmapId): bool
    {
        try {
            return DB::table(static::table('course_maps').' AS m')
                ->join(static::table('courses').' AS c', 'c.id', '=', 'm.course_id')
                ->where('m.beatmap_id', $beatmapId)
                ->where('c.active', 1)
                ->exists();
        } catch (QueryException) {
            return false;
        }
    }

    /** Refuses an osu!lazer score token (or score) for a dan map, with LAZER_REFUSED (multiplayer: not for dan yet). */
    public static function refuseLazer(int $beatmapId): void
    {
        if (static::isCourseMap($beatmapId)) {
            abort(422, static::LAZER_REFUSED);
        }
    }

    /**
     * TomYum: a paused lazer play of a dan map isn't stored. $pauses is the score's "pauses" (lazer sends the
     * game time of each pause); TumThai doesn't send such a play at all.
     */
    public static function refuseLazerPaused(int $beatmapId, mixed $pauses): void
    {
        if (is_array($pauses) && $pauses !== [] && static::isCourseMap($beatmapId)) {
            abort(422, static::LAZER_PAUSED);
        }
    }

    /** TomYum: lazer mods with an osu!(stable) equivalent, by the legacy bit courses list in allowed_mods. */
    const LAZER_LEGACY_MODS = [
        'NF' => 1, 'EZ' => 2, 'HD' => 8, 'HR' => 16, 'SD' => 32, 'DT' => 64, 'HT' => 256, 'NC' => 576,
        'FL' => 1024, 'PF' => 16416, 'FI' => 1048576, 'RD' => 2097152, 'MR' => 1073741824,
        '4K' => 32768, '5K' => 65536, '6K' => 131072, '7K' => 262144, '8K' => 524288,
        '9K' => 16777216, '1K' => 67108864, '2K' => 268435456, '3K' => 134217728,
    ];

    /**
     * TomYum: a lazer play of a dan map that breaks the rules of every course the map is in isn't stored (a
     * dan map's leaderboard is for dan plays only), as on osu!(stable): osu!mania, the course's mods (Classic
     * on top: TumThai needs it; a lazer-only mod or a mod's own settings, like a custom DT speed, isn't
     * allowed), and, for a pass, the course's accuracy worked out as osu!(stable) does (PERFECT and GREAT as
     * 300, GOOD 200, OK 100, MEH 50), to two decimals.
     */
    public static function refuseLazerBreakingRules(int $beatmapId, int $rulesetId, bool $passed, array $mods, array $statistics): void
    {
        try {
            $courses = DB::table(static::table('course_maps').' AS m')
                ->join(static::table('courses').' AS c', 'c.id', '=', 'm.course_id')
                ->where('m.beatmap_id', $beatmapId)
                ->where('c.active', 1)
                ->get(['c.mode', 'c.allowed_mods', 'c.min_accuracy']);
        } catch (QueryException) {
            return;
        }
        if ($courses->isEmpty()) {
            return;
        }

        $legacy = 0;
        $unknown = false;
        foreach ($mods as $mod) {
            $acronym = strtoupper((string) (is_array($mod) ? ($mod['acronym'] ?? '') : $mod));
            if ($acronym === 'CL') {
                continue;
            }
            if (!isset(static::LAZER_LEGACY_MODS[$acronym]) || (is_array($mod) && !empty($mod['settings']))) {
                $unknown = true;
                continue;
            }
            $legacy |= static::LAZER_LEGACY_MODS[$acronym];
        }

        $count = fn (string $k) => max(0, (int) ($statistics[$k] ?? 0));
        $judged = $count('perfect') + $count('great') + $count('good') + $count('ok') + $count('meh') + $count('miss');
        $accuracy = $judged === 0 ? 0.0 : round(100 * (300 * ($count('perfect') + $count('great')) + 200 * $count('good')
            + 100 * $count('ok') + 50 * $count('meh')) / (300 * $judged), 2);

        $why = null;
        foreach ($courses as $course) {
            $broken = match (true) {
                $rulesetId !== (int) $course->mode => 'it has to be played in osu!mania.',
                $unknown || ($legacy & ~(int) $course->allowed_mods) !== 0 => 'a mod you used isn\'t allowed in this course (or has its own settings).',
                $passed && $accuracy < (float) $course->min_accuracy => 'it needs '.round((float) $course->min_accuracy, 2)."% accuracy (you had {$accuracy}%, as osu!(stable) counts it).",
                default => null,
            };
            if ($broken === null) {
                return;
            }
            $why ??= $broken;
        }

        abort(422, "This play wasn't submitted: {$why} osu!dan maps only take plays that follow the course's rules.");
    }

    /**
     * TomYum: a solo TumThai play of a dan map is submitted only with the Classic mod. $mods is what the
     * client sent: acronyms ("CL,HD" or a list) or lazer's mods ([{acronym: "CL"}, ...]); null when it
     * sent none (TumThai before 2026.1024.4 doesn't send them with the score token).
     */
    public static function refuseLazerWithoutClassic(int $beatmapId, mixed $mods): void
    {
        if (!static::isCourseMap($beatmapId)) {
            return;
        }
        if ($mods === null) {
            abort(422, static::LAZER_UPDATE);
        }

        $acronyms = is_string($mods) ? explode(',', $mods) : [];
        if (is_array($mods)) {
            foreach ($mods as $mod) {
                $acronyms[] = is_array($mod) ? ($mod['acronym'] ?? '') : (is_string($mod) ? $mod : '');
            }
        }
        if (!in_array('CL', array_map(fn ($a) => strtoupper(trim($a)), $acronyms), true)) {
            abort(422, static::LAZER_NEEDS_CLASSIC);
        }
    }

    /**
     * The courses with their maps (each map with its beatmap and set, when the website has them),
     * by key count and order. Empty when the plugin hasn't made its tables yet.
     */
    public static function courses(bool $activeOnly = true): array
    {
        try {
            $query = DB::table(static::table('courses'))->orderBy('keys')->orderBy('sort_order')->orderBy('id');
            if ($activeOnly) {
                $query->where('active', 1);
            }
            $courses = $query->get()->keyBy('id');
            $maps = DB::table(static::table('course_maps'))
                ->whereIn('course_id', $courses->keys())
                ->orderBy('course_id')
                ->orderBy('position')
                ->get();
        } catch (QueryException) {
            return [];
        }

        $beatmaps = Beatmap::with('beatmapset')->whereIn('beatmap_id', $maps->pluck('beatmap_id'))->get()->keyBy('beatmap_id');
        foreach ($courses as $course) {
            $course->maps = [];
        }
        foreach ($maps as $map) {
            $map->beatmap = $beatmaps[$map->beatmap_id] ?? null;
            $courses[$map->course_id]->maps[] = $map;
        }

        return $courses->values()->all();
    }

    public static function course(int $id): ?object
    {
        foreach (static::courses(false) as $course) {
            if ((int) $course->id === $id) {
                return $course;
            }
        }

        return null;
    }

    /**
     * How many players cleared each course: [course id => count]. $publicOnly leaves out restricted
     * and deleted players, like the clear lists do; staff pages count every clear.
     */
    public static function clearCounts(bool $publicOnly = true): array
    {
        try {
            $query = DB::table(static::table('clears').' AS c');
            if ($publicOnly) {
                $query->join('phpbb_users AS u', 'u.user_id', '=', 'c.user_id')
                    ->where('u.user_warnings', 0)
                    ->where('u.user_type', 0);
            }

            return $query
                ->select('c.course_id', DB::raw('COUNT(*) AS clears'))
                ->groupBy('c.course_id')
                ->pluck('clears', 'course_id')
                ->map(fn ($count) => (int) $count)
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * A course's clears, first clear first, each with its player (deleted and restricted players
     * left out).
     */
    public static function clears(int $courseId, int $limit = 100): array
    {
        try {
            $rows = DB::table(static::table('clears'))
                ->where('course_id', $courseId)
                ->orderBy('cleared_at')
                ->orderBy('user_id')
                ->limit($limit)
                ->get();
        } catch (QueryException) {
            return [];
        }

        return static::withUsers($rows);
    }

    /** The latest clears of any course, newest first, each with its player and course id. */
    public static function recentClears(int $limit = 10): array
    {
        try {
            $rows = DB::table(static::table('clears'))->orderByDesc('cleared_at')->limit($limit)->get();
        } catch (QueryException) {
            return [];
        }

        return static::withUsers($rows);
    }

    /**
     * $user's highest cleared course for each key count: [['course_id', 'name', 'keys']], fewest keys
     * first. For their profile.
     */
    public static function highestFor(User $user): array
    {
        try {
            $rows = DB::table(static::table('clears').' AS c')
                ->join(static::table('courses').' AS d', 'd.id', '=', 'c.course_id')
                ->where('c.user_id', $user->getKey())
                ->orderBy('d.keys')
                ->orderByDesc('d.sort_order')
                ->get(['d.id', 'd.name', 'd.keys']);
        } catch (QueryException) {
            return [];
        }

        $highest = [];
        foreach ($rows as $row) {
            $highest[$row->keys] ??= ['course_id' => (int) $row->id, 'name' => $row->name, 'keys' => (int) $row->keys];
        }

        return array_values($highest);
    }

    /** The mods' acronyms (MR, HD, ...), "none" when there are none. */
    public static function modNames(int $mods): string
    {
        $names = [];
        foreach (static::MODS as [$bit, $acronym]) {
            if (($mods & $bit) !== 0) {
                $names[] = $acronym;
            }
        }

        return count($names) === 0 ? 'none' : implode(', ', $names);
    }

    /** Like modNames, for a play: NC hides the DT that comes with it, and no mods is "no mods". */
    public static function playModNames(int $mods): string
    {
        if (($mods & 512) !== 0) {
            $mods &= ~64;
        }

        return $mods === 0 ? 'no mods' : static::modNames($mods);
    }

    /** "96%", "95.5%". */
    public static function accuracyText($accuracy): string
    {
        return rtrim(rtrim(number_format((float) $accuracy, 2, '.', ''), '0'), '.').'%';
    }

    private static function withUsers($rows): array
    {
        $users = User::default()->whereIn('user_id', $rows->pluck('user_id'))->get()->keyBy('user_id');
        $clears = [];
        foreach ($rows as $row) {
            if (isset($users[$row->user_id])) {
                $row->user = $users[$row->user_id];
                $clears[] = $row;
            }
        }

        return $clears;
    }
}
