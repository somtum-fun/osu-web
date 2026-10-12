<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

/**
 * TomYum: pkms's handle-requester allowlist. Which external processes (tosu, gosumemory, overlay
 * tools, …) are allowed to hold a PROCESS_VM_READ handle to osu!.exe while pkms is attached.
 *
 * Staff edit the list in Admin panel > pkms handle requesters. Each entry pins ONE sha256 of a
 * specific exe to a basename — a renamed cheat.exe → tosu.exe trips the hash check and gets
 * refused. The game server (handshake-flag plugin) exposes the current list at
 * {@see https://osu.blueskychan.dev/anticheat/approved-requesters} and pkms refreshes its
 * in-memory copy every few minutes, so updating tosu for everyone is a staff edit here — no pkms
 * rebuild.
 *
 * Stored as JSON in tomyum_site_settings.handle_requester_allowlist_json; array of
 * {basename, sha256, note}. basename is case-insensitive on Windows; sha256 is lowercase hex (64
 * chars). Multiple entries per basename are allowed (several approved tosu versions in flight).
 */
class TomYumHandleRequesterAllowlist
{
    private const SETTING = 'handle_requester_allowlist_json';

    /** @return array<int, array{basename: string, sha256: string, note: ?string}> */
    public static function all(): array
    {
        $raw = TomYumSiteSettings::get(self::SETTING);
        if ($raw === null || $raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $basename = is_string($entry['basename'] ?? null) ? trim($entry['basename']) : '';
            $sha256 = is_string($entry['sha256'] ?? null) ? strtolower(trim($entry['sha256'])) : '';
            if (!self::isValidBasename($basename) || !self::isValidSha256($sha256)) {
                continue;
            }
            $out[] = [
                'basename' => $basename,
                'sha256' => $sha256,
                'note' => is_string($entry['note'] ?? null) ? $entry['note'] : null,
            ];
        }

        return $out;
    }

    /** @param array<int, array{basename: string, sha256: string, note: ?string}> $entries */
    public static function save(array $entries): void
    {
        if ($entries === []) {
            TomYumSiteSettings::set(self::SETTING, null);

            return;
        }
        TomYumSiteSettings::set(self::SETTING, json_encode($entries, JSON_UNESCAPED_SLASHES));
    }

    public static function isValidBasename(string $basename): bool
    {
        // Windows exe basename: non-empty, no path separators, ends with .exe (case-insensitive),
        // reasonable length. Stricter than the file system allows on purpose — "tosu.exe" good,
        // "C:\path\tosu.exe" bad.
        if ($basename === '' || strlen($basename) > 128) {
            return false;
        }
        if (strpbrk($basename, "\\/\"<>|?*\r\n\t") !== false) {
            return false;
        }
        return preg_match('/\.exe$/i', $basename) === 1;
    }

    public static function isValidSha256(string $sha256): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $sha256) === 1;
    }
}
