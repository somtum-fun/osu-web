<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use DB;
use Illuminate\Database\QueryException;

/**
 * TomYum: which staff log actions the Discord plugin posts to the log channels (the server and user logs, and the
 * reports and flagged-scores channels for the actions that go there). Staff turn single actions off in the admin
 * panel; an action nobody has turned off is posted, so a new kind of entry is never silent. Public announcements
 * (ranked and loved maps, new custom medals, maps opened for nominations) don't depend on this.
 */
class TomYumDiscordLog
{
    /** Site setting: a JSON list of the muted actions; TomYum's Discord plugin reads it within a minute. */
    const SETTING = 'discord_log_muted_actions';

    /** @return string[] the muted actions */
    public static function muted(): array
    {
        $value = json_decode(TomYumSiteSettings::get(static::SETTING) ?? '[]', true);

        return is_array($value) ? array_values(array_filter($value, fn ($a) => is_string($a) && static::valid($a))) : [];
    }

    /** @param string[] $muted */
    public static function save(array $muted): void
    {
        $muted = array_values(array_unique(array_filter($muted, fn ($a) => is_string($a) && static::valid($a))));
        sort($muted);
        TomYumSiteSettings::set(static::SETTING, $muted === [] ? null : json_encode($muted));
    }

    /**
     * Every action the staff log has (and any muted one it no longer has), grouped by the part before the first
     * dot: ['beatmapset' => ['beatmapset.import' => 289, ...], ...], groups and actions sorted.
     *
     * @return array<string, array<string, int>>
     */
    public static function actions(): array
    {
        try {
            $counts = DB::table('tomyum.staff_log')->select('action', DB::raw('COUNT(*) AS n'))->groupBy('action')->pluck('n', 'action')->all();
        } catch (QueryException) {
            $counts = [];
        }
        foreach (static::muted() as $action) {
            $counts[$action] ??= 0;
        }

        $groups = [];
        foreach ($counts as $action => $n) {
            if (static::valid((string) $action)) {
                $groups[explode('.', (string) $action, 2)[0]][(string) $action] = (int) $n;
            }
        }
        ksort($groups);
        foreach ($groups as &$group) {
            ksort($group);
        }

        return $groups;
    }

    private static function valid(string $action): bool
    {
        return preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)*$/', $action) === 1 && strlen($action) <= 64;
    }
}
