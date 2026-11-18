<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

/**
 * TomYum: pkms's server-maintained trusted-signer list. When a pkms finding reports a signed DLL
 * whose signer isn't in the baked-into-pkms allowlist (`ml:... [not in allowlist: <signer>]`), the
 * handshake-flag plugin drops the module_list bit when every such entry names a signer in this
 * list. A false positive is then fixed by a staff edit here — no pkms rebuild, no ship flow.
 *
 * Stored as JSON in tomyum_site_settings.pkms_trusted_signers_json (an array of strings — case-
 * insensitive, trimmed). TomYum reads the row every 60 s, so a save is live inside a minute.
 * Example entries: `OORT Inc.`, `Nahimic`, `OhSoft Co., Ltd.`.
 */
class TomYumPkmsTrustedSigners
{
    private const SETTING = 'pkms_trusted_signers_json';
    public const MAX_LENGTH = 200;

    /** @return array<int, string> */
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
            if (!is_string($entry)) {
                continue;
            }
            $trimmed = trim($entry);
            if ($trimmed === '' || !self::isValidSigner($trimmed)) {
                continue;
            }
            $out[] = $trimmed;
        }

        return $out;
    }

    /** @param array<int, string> $entries */
    public static function save(array $entries): void
    {
        // De-dup case-insensitively while keeping the first spelling the staff entered.
        $seen = [];
        $kept = [];
        foreach ($entries as $entry) {
            $key = mb_strtolower($entry);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $kept[] = $entry;
        }

        if ($kept === []) {
            TomYumSiteSettings::set(self::SETTING, null);

            return;
        }
        TomYumSiteSettings::set(self::SETTING, json_encode($kept, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public static function isValidSigner(string $signer): bool
    {
        // Signer CNs are short-ish (Authenticode subject common names). Rule out control
        // characters and anything suspiciously long; pkms compares case-insensitively.
        if ($signer === '' || mb_strlen($signer) > self::MAX_LENGTH) {
            return false;
        }
        if (preg_match('/[\x00-\x1F\x7F]/u', $signer) === 1) {
            return false;
        }
        return true;
    }
}
