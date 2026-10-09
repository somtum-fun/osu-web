<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

/**
 * TomYum: the pkms version registry (modern-stable anti-cheat). Staff set which build_ids (what
 * pkms sends as its "build_id", e.g. "pkms/0.3.1-test2") are enabled, in grace, or revoked, in
 * Admin panel > pkms versions. TomYum's handshake-flag plugin reads this through OsuWebPkmsVersions
 * and refuses revoked builds; grace builds pass with an "update required" marker.
 *
 * Stored as JSON in tomyum_site_settings.pkms_versions_json (array of {build_id, status, grace_until, note}).
 */
class TomYumPkmsVersions
{
    public const STATUSES = ['enabled', 'grace', 'revoked'];

    /** @return array<int, array{build_id: string, status: string, grace_until: ?string, note: ?string}> */
    public static function all(): array
    {
        $raw = TomYumSiteSettings::get('pkms_versions_json');
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
            if (!is_array($entry) || !isset($entry['build_id']) || !is_string($entry['build_id'])) {
                continue;
            }
            $status = is_string($entry['status'] ?? null) ? strtolower($entry['status']) : 'enabled';
            if (!in_array($status, static::STATUSES, true)) {
                $status = 'enabled';
            }
            $out[] = [
                'build_id' => $entry['build_id'],
                'status' => $status,
                'grace_until' => is_string($entry['grace_until'] ?? null) ? $entry['grace_until'] : null,
                'note' => is_string($entry['note'] ?? null) ? $entry['note'] : null,
            ];
        }

        return $out;
    }

    /** @param array<int, array{build_id: string, status: string, grace_until: ?string, note: ?string}> $entries */
    public static function save(array $entries): void
    {
        if ($entries === []) {
            TomYumSiteSettings::set('pkms_versions_json', null);

            return;
        }
        TomYumSiteSettings::set('pkms_versions_json', json_encode($entries, JSON_UNESCAPED_SLASHES));
    }
}
