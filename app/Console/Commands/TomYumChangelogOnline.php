<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Libraries\TomYumChangelog;
use App\Libraries\TomYumDownloads;
use App\Models\Build;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis as LaravelRedis;

/**
 * TomYum: how many players are online on each client stream, for the changelog ("N users online"
 * next to a stream, and the chart builds:update-propagation-history draws from it). osu-web counts a
 * stream's players on its versions (osu_builds.users); a stream's whole count goes on its newest
 * version and the others are set to 0.
 *
 *   - TumThai (lazer) and TumThai!Next (tachyon): the players in lazer's presence feed
 *     (tomyum:lazer-presence, written by TomYum's spectator build), split by each session's
 *     X-Osu-Version-Hash (osu.Game.dll MD5 on desktop, "{version}-Android" MD5 on Android).
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
    private const STREAMS = ['lazer', 'tachyon', 'stable40', 'legacy'];

    /** Shipped TumThai!Next desktop hashes without their own osu_builds row (2026.1026.3-next, .4-next). */
    private const EXTRA_TACHYON_HASHES = [
        '19da9055c677078ccda2532eb0084e5e',
        'c133c2a843be265c71fa524173ad86da',
    ];

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

        $manifest = TomYumDownloads::manifest();
        $this->syncManifestBuildHashes($manifest);
        $tachyonHashes = $this->tachyonHashes($manifest);

        // No presence feed: nobody on lazer, or the spectator server is down. Either way, 0.
        foreach ($redis->hGetAll(static::LAZER_PRESENCE_KEY) ?: [] as $payload) {
            $data = is_string($payload) ? json_decode($payload, true) : null;
            $versionHash = is_array($data) ? strtolower((string) ($data['version_hash'] ?? '')) : '';
            if ($versionHash !== '' && isset($tachyonHashes[$versionHash])) {
                ++$counts['tachyon'];
            } else {
                ++$counts['lazer'];
            }
        }

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

    /**
     * Stamps osu.Game.dll's MD5 from manifest.json onto any matching changelog version whose hash
     * hasn't been recorded yet, so older TumThai!Next releases keep their hash after newer ones ship.
     */
    private function syncManifestBuildHashes(array $manifest): void
    {
        foreach (['lazer' => 6, 'lazer_next' => 15] as $key => $streamId) {
            $version = (string) ($manifest[$key]['version'] ?? '');
            $hash = strtolower((string) ($manifest[$key]['version_hash'] ?? ''));
            if ($version === '' || preg_match('/^[0-9a-f]{32}$/', $hash) !== 1) {
                continue;
            }

            Build::where('stream_id', $streamId)
                ->where('version', $version)
                ->whereNull('hash')
                ->update(['hash' => hex2bin($hash)]);
        }
    }

    /** @return array<string, true> Lowercase MD5 hashes sent in X-Osu-Version-Hash by TumThai!Next builds. */
    private function tachyonHashes(array $manifest): array
    {
        $hashes = array_fill_keys(static::EXTRA_TACHYON_HASHES, true);
        foreach (['2026.1026.3-next', '2026.1026.4-next'] as $unloggedVersion) {
            $hashes[md5("{$unloggedVersion}-Android")] = true;
        }

        foreach (Build::where('stream_id', TomYumChangelog::STREAMS['tachyon'][0])->get(['version', 'hash']) as $build) {
            if (is_string($build->hash) && strlen($build->hash) === 16) {
                $hashes[strtolower(bin2hex($build->hash))] = true;
            }
            if (is_string($build->version) && $build->version !== '') {
                $hashes[md5("{$build->version}-Android")] = true;
            }
        }

        $manifestNextHash = strtolower((string) ($manifest['lazer_next']['version_hash'] ?? ''));
        if (preg_match('/^[0-9a-f]{32}$/', $manifestNextHash) === 1) {
            $hashes[$manifestNextHash] = true;
        }
        $manifestNextVersion = (string) ($manifest['lazer_next']['version'] ?? '');
        if ($manifestNextVersion !== '') {
            $hashes[md5("{$manifestNextVersion}-Android")] = true;
        }

        return $hashes;
    }
}
