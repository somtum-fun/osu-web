<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Libraries\TomYumChangelog;
use App\Models\Build;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis as LaravelRedis;

/**
 * TomYum: how many players are online on each client stream, for the changelog ("N users online"
 * next to a stream, and the chart builds:update-propagation-history draws from it). osu-web counts a
 * stream's players on its versions (osu_builds.users); the game doesn't say which version each player
 * runs, so a stream's whole count goes on its newest version and the others are set to 0.
 *
 *   - TumThai (lazer): the players in lazer's presence feed (tomyum:lazer-presence, written by TomYum's
 *     spectator build).
 *   - Somtum Patcher (stable40) and Legacy: the fields of the same name in tomyum:changelog-online,
 *     written by TomYum (see TomYum docs/changelog-online.md; the key expires, so a stopped server's
 *     counts don't stay). No field: 0, so the stream shows no count.
 *
 * The other streams (Web / Wiki, Server, Discord, Somtum Launcher) have no players to count.
 */
class TomYumChangelogOnline extends Command
{
    /** TomYum's counts by stream name (only the client streams). */
    public const COUNTS_KEY = 'tomyum:changelog-online';

    public const LAZER_PRESENCE_KEY = 'tomyum:lazer-presence';

    /** The streams this counts. */
    private const STREAMS = ['lazer', 'stable40', 'legacy'];

    protected $signature = 'tomyum:changelog-online';

    protected $description = 'Puts the players online on each client stream on its newest changelog version.';

    public function handle(): void
    {
        $redis = LaravelRedis::connection('default');
        $counts = array_fill_keys(static::STREAMS, 0);
        foreach ($redis->hGetAll(static::COUNTS_KEY) ?: [] as $stream => $count) {
            if (in_array($stream, static::STREAMS, true) && ctype_digit((string) $count)) {
                $counts[$stream] = (int) $count;
            }
        }
        // No presence feed: nobody on lazer, or the spectator server is down. Either way, 0.
        $counts['lazer'] = (int) $redis->hLen(static::LAZER_PRESENCE_KEY);

        foreach ($counts as $stream => $count) {
            $streamId = TomYumChangelog::STREAMS[$stream][0];
            $newest = Build::where('stream_id', $streamId)->where('private', false)
                ->orderByDesc('date')->orderByDesc('build_id')->first();
            if ($newest === null) {
                continue;
            }

            Build::where('stream_id', $streamId)->where('build_id', '<>', $newest->getKey())->where('users', '<>', 0)->update(['users' => 0]);
            // osu-web only counts versions players may log in with (allow_bancho); the newest is the one.
            if ($newest->users !== $count || !$newest->allow_bancho) {
                $newest->update(['users' => $count, 'allow_bancho' => true]);
            }
            $this->line("{$stream}: {$count} on {$newest->version}");
        }
    }
}
