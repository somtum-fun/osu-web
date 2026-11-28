<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\Build;
use Illuminate\Http\Request;

/**
 * TomYum: TumThai's releases as client builds, by the version_hash it sends with a play.
 *
 * Every TumThai release (TumThai and TumThai!Next) is a row in osu_builds on its changelog stream, holding the MD5 of
 * its osu.Game.dll (the manifest's version_hash; the same file on Windows and Linux). Android can't read its dll and
 * sends md5("{version}-Android") instead, which is matched from the version. A play's score token records the build
 * it came from; a hash that matches nothing keeps build 0 ("lazer (unchecked)"), as before.
 *
 * The newest few releases of a stream count as current (players update a little after a release); older known ones
 * are outdated. Nothing is refused on either yet: the result is logged only.
 *
 * The hash is reported by the client and public in the manifest: a way to tell builds apart, not proof of one.
 */
class TomYumLazerBuilds
{
    /** The changelog streams of TumThai's releases: manifest key => stream id. */
    public const STREAMS = ['lazer' => 6, 'lazer_next' => 15];

    /** How many of a stream's newest releases are current. */
    public const CURRENT = 5;

    private const CACHE_KEY = 'tomyum_lazer_builds:v1';

    private const CACHE_SECONDS = 300;

    /**
     * Makes sure the manifest's releases are builds a play can be matched to: a row on their stream (a private one,
     * off the changelog, when none was written for it yet; writing its changes later just edits it), with the hash,
     * and pp and ranking allowed (scores from a build without them get no pp). Returns what it did, a line each.
     *
     * With $dryRun nothing is written: the lines say what would be done.
     *
     * @return list<string>
     */
    public static function register(array $manifest, bool $dryRun = false): array
    {
        $done = [];

        foreach (static::STREAMS as $key => $streamId) {
            $version = (string) ($manifest[$key]['version'] ?? '');
            $hash = strtolower((string) ($manifest[$key]['version_hash'] ?? ''));
            if ($version === '' || preg_match('/\A[0-9a-f]{32}\z/', $hash) !== 1) {
                $done[] = "{$key}: no version or hash in the manifest";
                continue;
            }

            $build = Build::where('stream_id', $streamId)->where('version', $version)->first();
            if ($build === null) {
                $date = $manifest[$key]['date'] ?? null;
                $build = new Build([
                    'stream_id' => $streamId,
                    'version' => $version,
                    'date' => $date !== null ? \Carbon\Carbon::parse($date, 'UTC') : now(),
                    'private' => true,
                ]);
                $what = 'added (private)';
            } else {
                $what = 'updated';
            }

            $old = is_string($build->hash) && strlen($build->hash) === 16 ? bin2hex($build->hash) : null;
            if ($old !== null && $old !== $hash) {
                $what .= ", hash was {$old}";
            }

            // The macOS apps are built on GitHub Actions, whose osu.Game.dll differs from the one built here: its hash
            // goes in last_hash (unused for these streams), so the same release matches either.
            $macHash = strtolower((string) ($manifest[$key]['version_hash_macos'] ?? ''));
            if (preg_match('/\A[0-9a-f]{32}\z/', $macHash) === 1 && $macHash !== $hash) {
                $build->last_hash = hex2bin($macHash);
                $what .= ", macOS {$macHash}";
            }

            $build->fill([
                'hash' => hex2bin($hash),
                'allow_bancho' => true,
                'allow_ranking' => true,
                'allow_performance' => true,
            ]);
            if ($dryRun) {
                $id = $build->exists ? $build->getKey() : 'new';
                $done[] = "{$key}: {$version} {$hash} build {$id} would be {$what}";
                continue;
            }
            $build->save();
            $done[] = "{$key}: {$version} {$hash} build {$build->getKey()} {$what}";
        }

        // Older releases keep counting for pp too: their plays are recorded on them from now on.
        $olderQuery = Build::whereIn('stream_id', array_values(static::STREAMS))
            ->whereNotNull('hash')
            ->where(fn ($q) => $q->where('allow_performance', false)->orWhere('allow_ranking', false));
        if ($dryRun) {
            $older = $olderQuery->count();
            if ($older > 0) {
                $done[] = "older releases that would allow pp and ranking: {$older}";
            }

            return $done;
        }
        $older = $olderQuery->update(['allow_performance' => true, 'allow_ranking' => true]);
        if ($older > 0) {
            $done[] = "older releases now allowing pp and ranking: {$older}";
        }

        \Cache::forget(static::CACHE_KEY);

        return $done;
    }

    /**
     * The build $versionHash belongs to: ['build_id' => ?int, 'version' => ?string, 'stream' => ?string,
     * 'status' => 'current' | 'outdated' | 'unknown' | 'none'] ('none': nothing was sent).
     */
    public static function lookup(?string $versionHash): array
    {
        $hash = strtolower(trim((string) $versionHash));
        if ($hash === '') {
            return ['build_id' => null, 'version' => null, 'stream' => null, 'status' => 'none'];
        }

        $known = static::known()[$hash] ?? null;
        if ($known === null) {
            return ['build_id' => null, 'version' => null, 'stream' => null, 'status' => 'unknown'];
        }

        return $known;
    }

    /**
     * For a play's score token: the build to record. A build the upstream token check already found (x-token, not used
     * by TumThai) stays; otherwise the release version_hash names, or $fallback when it names none. Logs what it saw.
     */
    public static function buildIdForPlay(Request $request, int $fallback, int $userId, string $where): int
    {
        $sent = $request->input('version_hash');
        $found = static::lookup(is_string($sent) ? $sent : null);
        $hasClientHeader = TomYumLazerClient::hashesFrom($request) !== null;

        \Log::info('tomyum lazer build', [
            'where' => $where,
            'user_id' => $userId,
            'version_hash' => is_string($sent) ? substr($sent, 0, 64) : null,
            'status' => $found['status'],
            'build_id' => $found['build_id'],
            'version' => $found['version'],
            'stream' => $found['stream'],
            'tumthai_header' => $hasClientHeader,
        ]);

        if ($fallback !== (int) $GLOBALS['cfg']['osu']['client']['default_build_id']) {
            return $fallback;
        }

        return $found['build_id'] ?? $fallback;
    }

    /**
     * For the admin panel: each stream's releases, newest first, with their status and how many plays they started
     * (score tokens) in all and in the last 7 days, plus the plays that matched no release (build 0) in those 7 days.
     */
    public static function overview(int $perStream = 30): array
    {
        $since = now()->subDays(7);
        $streams = [];

        foreach (static::STREAMS as $key => $streamId) {
            $builds = Build::where('stream_id', $streamId)->whereNotNull('hash')
                ->orderByDesc('date')->orderByDesc('build_id')->limit($perStream)
                ->get(['build_id', 'version', 'hash', 'last_hash', 'date', 'private', 'allow_ranking', 'allow_performance']);
            $ids = $builds->pluck('build_id')->all();
            $plays = \DB::table('score_tokens')->whereIn('build_id', $ids)
                ->selectRaw('build_id, COUNT(*) AS total, SUM(created_at >= ?) AS week, MAX(created_at) AS last', [$since])
                ->groupBy('build_id')->get()->keyBy('build_id');

            $streams[$key] = $builds->values()->map(fn (Build $build, int $i) => [
                'build_id' => $build->getKey(),
                'version' => $build->version,
                'hash' => is_string($build->hash) && strlen($build->hash) === 16 ? bin2hex($build->hash) : null,
                'android_hash' => md5("{$build->version}-Android"),
                'macos_hash' => is_string($build->last_hash) && strlen($build->last_hash) === 16 ? bin2hex($build->last_hash) : null,
                'date' => $build->date,
                'private' => (bool) $build->private,
                'counts' => (bool) $build->allow_ranking && (bool) $build->allow_performance,
                'status' => $i < static::CURRENT ? 'current' : 'outdated',
                'plays' => (int) ($plays[$build->getKey()]->total ?? 0),
                'plays_week' => (int) ($plays[$build->getKey()]->week ?? 0),
                'last_play' => $plays[$build->getKey()]->last ?? null,
            ])->all();
        }

        $defaultBuild = (int) $GLOBALS['cfg']['osu']['client']['default_build_id'];
        $unmatched = \DB::table('score_tokens')->where('build_id', $defaultBuild)->where('created_at', '>=', $since)->count();
        $matched = \DB::table('score_tokens')->where('build_id', '<>', $defaultBuild)->where('created_at', '>=', $since)->count();

        return ['streams' => $streams, 'unmatched_week' => $unmatched, 'matched_week' => $matched, 'current' => static::CURRENT];
    }

    /** @return array<string, array> hash => what lookup() returns for it, for every known release. */
    private static function known(): array
    {
        return \Cache::remember(static::CACHE_KEY, static::CACHE_SECONDS, function () {
            $map = [];

            foreach (static::STREAMS as $key => $streamId) {
                $builds = Build::where('stream_id', $streamId)
                    ->whereNotNull('hash')
                    ->orderByDesc('date')->orderByDesc('build_id')
                    ->get(['build_id', 'version', 'hash', 'last_hash']);

                foreach ($builds as $i => $build) {
                    if (!is_string($build->hash) || strlen($build->hash) !== 16) {
                        continue;
                    }

                    $entry = [
                        'build_id' => $build->getKey(),
                        'version' => $build->version,
                        'stream' => $key,
                        'status' => $i < static::CURRENT ? 'current' : 'outdated',
                    ];
                    // the desktop dll, the macOS apps' (built elsewhere, see register), and Android's stand-in for it
                    $map[strtolower(bin2hex($build->hash))] ??= $entry;
                    if (is_string($build->last_hash) && strlen($build->last_hash) === 16) {
                        $map[strtolower(bin2hex($build->last_hash))] ??= $entry;
                    }
                    $map[md5("{$build->version}-Android")] ??= $entry;
                }
            }

            return $map;
        });
    }
}
