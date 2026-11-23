<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * TomYum: TumThai's machine hashes (the X-TumThai-Client header, in osu!(stable)'s client hash format) and
 * stable's machine rules for them.
 *
 * TumThai sends the header on every request to a TomYum server, its sign-in included. Another lazer
 * client (or TumThai set to a non-TomYum server) sends none: it can sign in, but can't submit scores while
 * TOMYUM_LAZER_REQUIRE_CLIENT is on. A sign-in or a play whose hashes share a machine with a restricted or
 * banned account is refused as on stable (TomYum decides, so both clients follow one set of rules, and a
 * lazer account is matched against stable accounts too).
 */
class TomYumLazerClient
{
    public const HEADER = 'X-TumThai-Client';

    /** What a TumThai understands beyond osu!lazer (comma separated), e.g. "playstyles": the website's extra playstyles. */
    public const FEATURES_HEADER = 'X-TumThai-Features';

    /** Whether the request's client says it understands $feature (another lazer client says nothing). */
    public static function knows(string $feature): bool
    {
        $raw = request()?->header(static::FEATURES_HEADER);

        return is_string($raw) && in_array($feature, array_map('trim', explode(',', strtolower($raw))), true);
    }

    /** A machine already checked isn't asked about again for this long (seconds). */
    private const CACHE_SECONDS = 600;

    /** Whether lazer scores need TumThai's hashes. */
    public static function required(): bool
    {
        return $GLOBALS['cfg']['osu']['scores']['tomyum_require_client'] ?? false;
    }

    /**
     * The request's hashes, or null when it sent none or they aren't in stable's format
     * (<code>path_md5:adapters:adapters_md5:uninstall_md5:disk_md5:</code>).
     */
    public static function hashesFrom(Request $request): ?string
    {
        $raw = $request->header(static::HEADER);
        if (!is_string($raw) || $raw === '' || strlen($raw) > 4096) {
            return null;
        }

        // one trailing ':' ends the list (an empty machine id leaves "...::").
        $parts = explode(':', str_ends_with($raw, ':') ? substr($raw, 0, -1) : $raw);
        if (count($parts) !== 5) {
            return null;
        }

        foreach ([0, 2, 3, 4] as $i) {
            if ($parts[$i] !== '' && !preg_match('/\A[0-9a-f]{32}\z/', $parts[$i])) {
                return null;
            }
        }

        // the uninstall id is the one every TumThai has; without it this isn't TumThai.
        return $parts[3] === '' ? null : $raw;
    }

    /**
     * What TomYum says about $user on the machine $hashes came from: null when they may go on, or the
     * notice to show them. When TomYum can't be reached they're let in (as when stable's login is down)
     * and it's logged.
     */
    public static function refusal(User $user, string $hashes): ?string
    {
        $key = 'tomyum_lazer_client:'.$user->getKey().':'.md5($hashes);
        $cached = \Cache::get($key);
        if ($cached !== null) {
            return $cached === '' ? null : $cached;
        }

        try {
            $response = (new TomYumAdmin())->withTimeout(5)->post('/lazer/client-check', [
                'user_id' => $user->getKey(),
                'client_hashes' => $hashes,
            ]);
        } catch (\Throwable $e) {
            log_error($e);

            return null;
        }

        if (!$response->successful()) {
            log_error(new \Exception("TomYum's lazer client check answered {$response->status()} for user {$user->getKey()}"));

            return null;
        }

        $notice = $response->json('allowed') === true
            ? ''
            : (presence($response->json('notice')) ?? 'Please contact staff directly to create an account.');
        // a refusal is asked again sooner, so staff verifying the account (!verify) takes effect quickly.
        \Cache::put($key, $notice, $notice === '' ? static::CACHE_SECONDS : 60);

        return $notice === '' ? null : $notice;
    }

    /**
     * For a play: aborts when lazer scores need TumThai and this request didn't come from it, or when its
     * machine is refused.
     */
    public static function assertMayPlay(Request $request, User $user): void
    {
        $hashes = static::hashesFrom($request);
        if ($hashes === null) {
            if (static::required()) {
                static::refuse('Scores here can only be submitted from TumThai. Get it from '.url('/home/download').'.');
            }

            return;
        }

        $notice = static::refusal($user, $hashes);
        if ($notice !== null) {
            static::refuse($notice);
        }
    }

    /** Ends the request with 403 and {"error": $message}, which TumThai shows as why the play won't be submitted. */
    private static function refuse(string $message): never
    {
        abort(response()->json(['error' => $message], 403));
    }
}
