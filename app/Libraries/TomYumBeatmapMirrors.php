<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use Illuminate\Support\Facades\Redis as LaravelRedis;

/**
 * The beatmap mirrors TomYum uses (TomYum's BeatmapMirrorPool reads them every minute): which ones map lookups ask, in
 * order, and where players' downloads go. Stored as JSON in tomyum_site_settings.beatmap_mirrors_json, an array of
 * {name, url, lookups, downloads, download_path, requests_per_minute, priority, enabled}. Not set: TomYum uses its own
 * Mirror:* and Direct:* settings. How each one is doing is in Redis (tomyum:beatmap-mirrors:health), written by TomYum.
 */
class TomYumBeatmapMirrors
{
    public const SETTING = 'beatmap_mirrors_json';

    private const HEALTH_KEY = 'tomyum:beatmap-mirrors:health';

    /** Whether the panel ever set a list (an empty one included). */
    public static function isSet(): bool
    {
        return TomYumSiteSettings::get(static::SETTING) !== null;
    }

    /** The mirrors, by priority. */
    public static function all(): array
    {
        $raw = TomYumSiteSettings::get(static::SETTING);
        $entries = $raw === null ? [] : json_decode($raw, true);
        if (!is_array($entries)) {
            return [];
        }
        $entries = array_values(array_filter($entries, 'is_array'));
        usort($entries, fn ($a, $b) => ($a['priority'] ?? 100) <=> ($b['priority'] ?? 100));

        return $entries;
    }

    /** Stores the list (null: back to the game server's own settings). */
    public static function save(?array $entries): void
    {
        TomYumSiteSettings::set(static::SETTING, $entries === null ? null : json_encode(array_values($entries), JSON_UNESCAPED_SLASHES));
    }

    /**
     * Why $url can't be a mirror, or null when it can. Like TomYum's own check (BeatmapMirrorEntry.IsAllowed): https,
     * no user name or password, and not the server itself or a private network.
     */
    public static function problemWith(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || !isset($parts['host'])) {
            return 'Use an https:// address.';
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'No user name or password in the address.';
        }
        $host = strtolower(rtrim(trim($parts['host'], '[]'), '.'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal')
            || str_ends_with($host, '.local') || !str_contains($host, '.') && filter_var($host, FILTER_VALIDATE_IP) === false) {
            return 'That address is inside this server\'s network.';
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return 'That address is a private or reserved one.';
        }

        return null;
    }

    /** How each mirror is doing, by its address (as TomYum writes it: with a trailing slash). */
    public static function health(): array
    {
        try {
            $raw = LaravelRedis::connection('default')->hgetall(static::HEALTH_KEY) ?? [];
        } catch (\Throwable $e) {
            return [];
        }
        $health = [];
        foreach ($raw as $url => $json) {
            $value = json_decode((string) $json, true);
            if (is_array($value)) {
                $health[$url] = $value;
            }
        }

        return $health;
    }

    /** The address as TomYum keys its health: absolute, with one trailing slash. */
    public static function key(string $url): string
    {
        return rtrim(trim($url), '/').'/';
    }
}
