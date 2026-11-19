<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

/**
 * TomYum: the cheat-signature sets pkms scans for, edited in Admin panel > Anti-cheat. TomYum's handshake-flag plugin
 * serves the first enabled set (only its enabled needles, in order) to pkms over /anticheat/scan-set, read from here
 * (TOMYUM_PKMS_SCAN_SET_SOURCE = db or auto) within a minute.
 *
 * Stored as JSON in tomyum_site_settings.pkms_scan_sets_json: an array of
 * {id, name, revision, enabled, note, updated_by, updated_at, needles: [{tag, text|hex, enabled}]}. Staff edit `name`;
 * every save bumps `revision` and so `id` ("{name}-r{revision}"): pkms reports a hit as cs:<id>#<index>, and an id
 * never changes what its indices mean. The tags of every id served are kept in pkms_scan_sets_history_json, so a hit
 * still reads as its tag later.
 */
class TomYumPkmsScanSets
{
    // The limits pkms has (cheat_sigs.c) and the plugin checks (ScanSet.Parse).
    public const MAX_NEEDLES = 32;
    public const MAX_NEEDLE_BYTES = 63;
    public const MAX_WIRE_BYTES = 2048;
    public const MAX_SETS = 10;
    public const NAME_PATTERN = '/^[A-Za-z0-9_-]{1,24}$/';
    private const HISTORY_IDS = 30;
    private const SETTING = 'pkms_scan_sets_json';
    private const HISTORY_SETTING = 'pkms_scan_sets_history_json';

    /** Every stored set, as saved. */
    public static function all(): array
    {
        $decoded = json_decode(TomYumSiteSettings::get(static::SETTING) ?? '[]', true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    public static function find(string $name): ?array
    {
        foreach (static::all() as $set) {
            if (($set['name'] ?? null) === $name) {
                return $set;
            }
        }

        return null;
    }

    /** The set pkms gets now (the first enabled one), or null. */
    public static function served(): ?array
    {
        foreach (static::all() as $set) {
            if ($set['enabled'] ?? false) {
                return $set;
            }
        }

        return null;
    }

    /**
     * What's wrong with a set's needles (as the plugin would refuse them), [] when nothing. Counts only the enabled
     * ones: those are what pkms gets.
     *
     * @return string[]
     */
    public static function problems(array $needles): array
    {
        $problems = [];
        $enabled = array_values(array_filter($needles, fn ($n) => $n['enabled'] ?? true));
        if (count($enabled) === 0) {
            $problems[] = 'A set needs at least one enabled needle.';
        }
        if (count($enabled) > static::MAX_NEEDLES) {
            $problems[] = 'At most '.static::MAX_NEEDLES.' enabled needles ('.count($enabled).' now).';
        }

        $wire = 0;
        foreach ($needles as $i => $needle) {
            $label = '"'.(($needle['tag'] ?? '') !== '' ? $needle['tag'] : 'needle '.($i + 1)).'"';
            $bytes = static::bytes($needle);
            if ($bytes === null) {
                $problems[] = isset($needle['hex'])
                    ? "{$label}: hex must be pairs of 0-9 a-f."
                    : "{$label}: text must be Latin-1 (no characters past ÿ).";
                continue;
            }
            $length = strlen($bytes);
            if ($length === 0 || $length > static::MAX_NEEDLE_BYTES) {
                $problems[] = "{$label}: 1-".static::MAX_NEEDLE_BYTES." bytes ({$length} now).";
            }
            if ($needle['enabled'] ?? true) {
                $wire += 1 + $length;
            }
        }
        if ($wire > static::MAX_WIRE_BYTES) {
            $problems[] = "The enabled needles are {$wire} bytes on the wire, over ".static::MAX_WIRE_BYTES.'.';
        }

        return $problems;
    }

    /** A needle's bytes as pkms scans for them (text as Latin-1, or hex), null when they can't be. */
    public static function bytes(array $needle): ?string
    {
        if (isset($needle['hex'])) {
            $hex = (string) $needle['hex'];

            return preg_match('/^(?:[0-9A-Fa-f]{2})*$/', $hex) === 1 ? (string) hex2bin($hex) : null;
        }
        $text = (string) ($needle['text'] ?? '');
        $latin1 = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');

        return mb_convert_encoding($latin1, 'UTF-8', 'ISO-8859-1') === $text ? $latin1 : null;
    }

    /**
     * Saves a set (new, or the one called $name), as a new revision with a new id. $enabled: null keeps it as it was
     * (a new set starts disabled). Enabling one disables the others: pkms gets only one. Returns the saved set.
     */
    public static function save(string $name, array $needles, ?string $note, ?bool $enabled, int $userId): array
    {
        $sets = static::all();
        $index = array_search($name, array_column($sets, 'name'), true);
        $previous = $index === false ? null : $sets[$index];
        $revision = ($previous['revision'] ?? 0) + 1;

        $set = [
            'id' => "{$name}-r{$revision}",
            'name' => $name,
            'revision' => $revision,
            'enabled' => $enabled ?? ($previous['enabled'] ?? false),
            'note' => $note,
            'updated_by' => $userId,
            'updated_at' => now()->toIso8601ZuluString(),
            'needles' => array_values(array_map(fn ($n) => array_filter([
                'tag' => (string) ($n['tag'] ?? ''),
                'text' => isset($n['hex']) ? null : (string) ($n['text'] ?? ''),
                'hex' => isset($n['hex']) ? strtolower((string) $n['hex']) : null,
                'enabled' => (bool) ($n['enabled'] ?? true),
            ], fn ($v) => $v !== null), $needles)),
        ];

        if ($index === false) {
            $sets[] = $set;
        } else {
            $sets[$index] = $set;
        }
        if ($set['enabled']) {
            foreach ($sets as $i => $other) {
                if ($other['name'] !== $name) {
                    $sets[$i]['enabled'] = false;
                }
            }
        }

        static::write($sets);

        return $set;
    }

    /** Turns a set on (the others off) or off, without a new revision: its needles, and so its id, don't change. */
    public static function setEnabled(string $name, bool $enabled): bool
    {
        $sets = static::all();
        $found = false;
        foreach ($sets as $i => $set) {
            if ($set['name'] === $name) {
                $sets[$i]['enabled'] = $enabled;
                $found = true;
            } elseif ($enabled) {
                $sets[$i]['enabled'] = false;
            }
        }
        if ($found) {
            static::write($sets);
        }

        return $found;
    }

    public static function delete(string $name): bool
    {
        $sets = static::all();
        $kept = array_values(array_filter($sets, fn ($set) => $set['name'] !== $name));
        if (count($kept) === count($sets)) {
            return false;
        }
        static::write($kept);

        return true;
    }

    /** "cs:main-r7#3" in a report's details → "cs:<tag> (main-r7#3)", from the tags each id was served with. */
    public static function describe(string $details): string
    {
        if (!str_contains($details, 'cs:')) {
            return $details;
        }
        $history = static::history();

        return preg_replace_callback('/cs:([A-Za-z0-9_-]{1,31})#(\d{1,2})\b/', function ($m) use ($history) {
            $tag = $history[$m[1]][(int) $m[2]] ?? null;

            return $tag === null ? $m[0] : "cs:{$tag} ({$m[1]}#{$m[2]})";
        }, $details) ?? $details;
    }

    /** Set id → its enabled needles' tags in served order (an empty tag is served as "needle{i}"). */
    private static function history(): array
    {
        $history = json_decode(TomYumSiteSettings::get(static::HISTORY_SETTING) ?? '{}', true);
        $history = is_array($history) ? $history : [];
        foreach (static::all() as $set) {
            $history[$set['id']] = static::servedTags($set);
        }

        return $history;
    }

    private static function servedTags(array $set): array
    {
        $enabled = array_values(array_filter($set['needles'] ?? [], fn ($n) => $n['enabled'] ?? true));

        return array_map(fn ($n, $i) => ($n['tag'] ?? '') !== '' ? $n['tag'] : "needle{$i}", $enabled, array_keys($enabled));
    }

    /** Writes the sets, after a backup of what was there (storage/app/pkms-scan-sets-backups), and records their tags. */
    private static function write(array $sets): void
    {
        $dir = storage_path('app/pkms-scan-sets-backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        file_put_contents($dir.'/pkms_scan_sets_json-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(2)).'.json', TomYumSiteSettings::get(static::SETTING) ?? '[]');

        $history = json_decode(TomYumSiteSettings::get(static::HISTORY_SETTING) ?? '{}', true);
        $history = is_array($history) ? $history : [];
        foreach ($sets as $set) {
            unset($history[$set['id']]);
            $history[$set['id']] = static::servedTags($set);
        }
        $history = array_slice($history, -static::HISTORY_IDS, null, true);

        TomYumSiteSettings::set(static::SETTING, $sets === [] ? null : json_encode(array_values($sets), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        TomYumSiteSettings::set(static::HISTORY_SETTING, json_encode($history, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
