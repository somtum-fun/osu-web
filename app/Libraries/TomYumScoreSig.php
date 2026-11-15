<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use Illuminate\Http\Request;
use Log;

/**
 * TomYum: verifies the X-Somtum-Sig header that TumThai / TumThai-next clients attach to score
 * submits. The client computes
 *
 *   HMAC-SHA256(utf8(access_token), "somtum-score-v1\n" + Target + "\n" + json_body)
 *
 * and sends the lowercase-hex digest in X-Somtum-Sig. The server re-derives the same signature
 * using the OAuth access token it already holds for the request and compares in constant time.
 *
 * Grace control via env:
 *   TOMYUM_SCORE_SIG_REQUIRED=false  (default): log-only — a bad or missing sig is reported but
 *                                                the submit proceeds. Use during the smoke-test
 *                                                window.
 *   TOMYUM_SCORE_SIG_REQUIRED=true           : enforce — a bad or missing sig aborts(403).
 *
 * Call verify() at the top of the solo score submit controller. It returns null on OK (including
 * when the request is from a client that doesn't sign, in grace mode) or an abort-triggering
 * reason string when enforcement is on and the signature is wrong / missing.
 */
class TomYumScoreSig
{
    public const HEADER = 'X-Somtum-Sig';
    private const DOMAIN = "somtum-score-v1";

    /**
     * @return string|null null = accept, else = reason, caller should abort(403, reason) when enforcing.
     */
    public static function verify(Request $request): ?string
    {
        $required = static::required();
        $provided = $request->header(self::HEADER);
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return static::report($request, 'score-sig: no bearer token on submit', $required);
        }

        if ($provided === null || $provided === '') {
            return static::report($request, 'score-sig: header missing', $required);
        }

        $body = (string) $request->getContent();
        // Target is the API-v2 path without the "/api/v2/" prefix, exactly what the client's
        // APIRequest.Target produces.
        $target = ltrim((string) $request->path(), '/');
        if (str_starts_with($target, 'api/v2/')) {
            $target = substr($target, strlen('api/v2/'));
        }

        $payload = self::DOMAIN . "\n" . $target . "\n" . $body;
        $expected = hash_hmac('sha256', $payload, $token);

        if (!hash_equals($expected, strtolower((string) $provided))) {
            return static::report($request, 'score-sig: bad signature', $required);
        }

        return null;
    }

    /** Is enforcement on? Default false (grace / log-only). */
    public static function required(): bool
    {
        $raw = env('TOMYUM_SCORE_SIG_REQUIRED', false);
        if (is_bool($raw)) {
            return $raw;
        }
        $s = strtolower(trim((string) $raw));
        return $s === '1' || $s === 'true' || $s === 'on' || $s === 'yes';
    }

    private static function report(Request $request, string $reason, bool $required): ?string
    {
        $userId = optional($request->user())->getKey();
        $ua = (string) $request->header('User-Agent', '');
        Log::warning('[tomyum] ' . $reason, [
            'user_id' => $userId,
            'path' => $request->path(),
            'ua' => $ua,
            'enforcing' => $required,
        ]);
        return $required ? $reason : null;
    }
}
