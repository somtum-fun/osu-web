<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use Illuminate\Support\Facades\Redis as LaravelRedis;

/**
 * TomYum: operator-flipped kill switch for the client-side anti-cheat. Writes a shared Redis key
 * (tomyum:anticheat:client_required) that TomYum's RedisAnticheatKillSwitch reads on each request
 * (cached ~5s). When OFF, modern-stable scores from clients without pkms are accepted — players
 * can join with plain osu! pointed at -devserver blueskychan.dev.
 *
 * Uses the "default" Redis connection (unprefixed) so TomYum sees the raw key. Defaults to ON
 * when the key is unset — safer default.
 */
class TomYumAnticheatKillSwitch
{
    private const REDIS_KEY = 'tomyum:anticheat:client_required';

    /** True when the pkms client is still required (the normal state). */
    public static function isClientRequired(): bool
    {
        try {
            $raw = LaravelRedis::connection('default')->get(self::REDIS_KEY);
        } catch (\Throwable $e) {
            // Redis unreachable: fail safe (AC ON).
            return true;
        }
        if ($raw === null || $raw === '') {
            return true;
        }
        $s = strtolower(trim((string) $raw));
        return !($s === '0' || $s === 'false' || $s === 'off');
    }

    /** Flip the switch. $required=false puts the server in "no pkms needed" mode. */
    public static function set(bool $required): void
    {
        LaravelRedis::connection('default')->set(self::REDIS_KEY, $required ? '1' : '0');
    }
}
