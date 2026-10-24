<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\Solo\Score;
use App\Models\User;
use Carbon\Carbon;

/**
 * TomYum: the home page's "Friends activity": the viewer's friends' best plays of the last week (the three worth
 * the most pp each, newest first), each marked when it's still #1 on its map's leaderboard (vanilla plays: the
 * website's, by score) or when it's a Relax/Autopilot play.
 */
class TomYumFriendsFeed
{
    public const DAYS = 7;
    public const LIMIT = 12;
    public const PER_FRIEND = 3;

    /** @return array<int, array{score: Score, first: bool, variant: string|null, mods: string[]}> */
    public static function forUser(User $user): array
    {
        $friendIds = $user->friends()->pluck('phpbb_users.user_id')->all();
        if ($friendIds === []) {
            return [];
        }

        $scores = Score::with(['beatmap.beatmapset', 'user'])
            ->whereIn('user_id', $friendIds)
            ->where('passed', true)
            ->whereNotNull('pp')
            ->where('ended_at', '>=', Carbon::now()->subDays(static::DAYS))
            ->orderByDesc('pp')
            ->limit(300)
            ->get()
            ->filter(fn (Score $score) => $score->beatmap !== null && $score->beatmap->beatmapset !== null && $score->user !== null)
            ->groupBy('user_id')
            ->flatMap(fn ($plays) => $plays->unique('beatmap_id')->take(static::PER_FRIEND))
            ->sortByDesc('ended_at')
            ->take(static::LIMIT)
            ->values();

        return $scores->map(function (Score $score) {
            $acronyms = array_column($score->data->mods, 'acronym');
            $variant = in_array('AP', $acronyms, true) ? 'Autopilot' : (in_array('RX', $acronyms, true) ? 'Relax' : null);

            return [
                'score' => $score,
                'first' => $variant === null && static::isFirst($score),
                'variant' => $variant,
                'mods' => array_values(array_filter($acronyms, fn ($acronym) => $acronym !== 'CL')),
            ];
        })->all();
    }

    /** Whether nobody (visible) has a better vanilla score on the map's leaderboard than this one. */
    private static function isFirst(Score $score): bool
    {
        return !Score::where('beatmap_id', $score->beatmap_id)
            ->where('ruleset_id', $score->ruleset_id)
            ->where('passed', true)
            ->where('preserve', true)
            ->where('total_score', '>', $score->total_score)
            ->whereRaw("JSON_SEARCH(data, 'one', 'RX', NULL, '$.mods[*].acronym') IS NULL")
            ->whereRaw("JSON_SEARCH(data, 'one', 'AP', NULL, '$.mods[*].acronym') IS NULL")
            ->whereHas('user', fn ($q) => $q->default())
            ->exists();
    }
}
