<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use Carbon\CarbonInterface;

/**
 * TomYum: the rankings' inactivity rule (a player without a score in a mode for so many days), as staff set it in
 * Admin panel > Rankings: inactive players are either taken off the rankings (remove, osu!'s way) or kept ranked and
 * greyed out (grey). Without a stored days value, TOMYUM_RANKING_INACTIVE_DAYS stands. TomYum
 * (RankingInactivitySettings) and the score processor (TomYumRankIndex) read the same settings within a minute.
 */
class TomYumRankingInactivity
{
    public const MODES = ['remove', 'grey'];
    public const MAX_DAYS = 3650;

    /** Days without a score after which a player is inactive (0: never). */
    public static function days(): int
    {
        $stored = TomYumSiteSettings::get('ranking_inactive_days');

        return $stored !== null && ctype_digit($stored) ? (int) $stored : (int) $GLOBALS['cfg']['tomyum']['ranking_inactive_days'];
    }

    /** Whether the days come from the panel (rather than the server's env). */
    public static function daysStored(): bool
    {
        $stored = TomYumSiteSettings::get('ranking_inactive_days');

        return $stored !== null && ctype_digit($stored);
    }

    public static function mode(): string
    {
        $mode = TomYumSiteSettings::get('ranking_inactive_mode');

        return in_array($mode, static::MODES, true) ? $mode : 'remove';
    }

    /** The days after which a player is off the rankings (0: they never are; in grey mode they stay ranked). */
    public static function removeDays(): int
    {
        return static::mode() === 'remove' ? static::days() : 0;
    }

    /** The days after which a ranked player is shown greyed out (0: nobody is). */
    public static function greyDays(): int
    {
        return static::mode() === 'grey' ? static::days() : 0;
    }

    /** Whether a player who last played then (a date, or the stats row's string) is greyed out on the rankings. */
    public static function isGreyedOut(CarbonInterface|string|null $lastPlayed): bool
    {
        $days = static::greyDays();
        if ($days === 0 || $lastPlayed === null) {
            return false;
        }
        $lastPlayed = $lastPlayed instanceof CarbonInterface ? $lastPlayed : parse_time_to_carbon($lastPlayed);

        return $lastPlayed !== null && $lastPlayed < now()->subDays($days);
    }

    /** $days null: back to the server's env value. */
    public static function save(?int $days, string $mode): void
    {
        TomYumSiteSettings::set('ranking_inactive_days', $days === null ? null : (string) $days);
        TomYumSiteSettings::set('ranking_inactive_mode', $mode);
    }
}
