<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

/**
 * TomYum: what each pkms finding (the client anti-cheat's bits) does to a modern-stable or legacy
 * score, separately for ordinary players and anti-cheat-whitelisted ones: log only, refuse the
 * score, or restrict the player (the score then lands in the restricted pool for review). Staff set
 * it in Admin panel > pkms finding actions; TomYum's score submission reads it through
 * OsuWebPkmsFindingPolicy (cached a minute).
 *
 * Stored as JSON in tomyum_site_settings.pkms_finding_policy_json:
 * {"normal": {key: action, ...}, "whitelisted": {key: action, ...}}. A missing key takes DEFAULTS,
 * which is how the server behaved before this setting. Keep the keys in step with TomYum's
 * PkmsFindingPolicy.Findings.
 */
class TomYumPkmsFindingPolicy
{
    public const SETTING = 'pkms_finding_policy_json';

    public const ACTIONS = ['log', 'refuse', 'restrict'];

    public const SIDES = ['normal', 'whitelisted'];

    /** key => [bit (0 for "other"), name, what it means]. */
    public const FINDINGS = [
        'handle' => [0x1, 'Handle check', 'Another process holds a memory-read handle to osu! (not an approved handle requester).'],
        'mem_scan' => [0x2, 'Memory scan', 'New executable memory, a manually mapped or hidden module.'],
        'injected_keys' => [0x4, 'Injected key presses', 'Key presses sent by software (SendInput), not a keyboard.'],
        'module_list' => [0x8, 'Foreign module', 'An unknown DLL loaded into osu!. A known harmless add-on (Bonjour) never restricts, at most refuses.'],
        'code_verify' => [0x10, 'Code verify', 'osu!\'s code or import table changed in memory.'],
        'write_protect' => [0x20, 'Write protect', 'osu!\'s code made writable, or a NOP sled in it.'],
        'memory_guard' => [0x40, 'Memory guard', 'Retired in pkms 0.7.1; only older builds can send it.'],
        'module_integrity' => [0x80, 'Module integrity', 'pkms.dll\'s own code or exports were changed.'],
        'anti_debug' => [0x100, 'Debugger', 'A debugger is attached to osu!.'],
        'hwid' => [0x200, 'HWID', 'The hardware identity in the registry was altered (false positives after a pkms upgrade).'],
        'proxy_dll' => [0x400, 'Proxy DLL', 'A Windows DLL (opengl32.dll, d3d9.dll, …) next to osu!.exe: ReShade or a loader.'],
        'unverified_ink_file' => [0x800, 'Unverified Ink file', 'A file in Windows\' Ink folder not signed by a known signer.'],
        'injected_clicks' => [0x1000, 'Injected mouse clicks', 'Clicks sent by software. Tablet drivers do it too; dropped when OpenTabletDriver is running.'],
        'cheat_sig' => [0x2000, 'Cheat signature', 'A known cheat\'s strings found in osu!\'s memory (freedom and its forks).'],
        'other' => [0, 'Any other finding', 'A finding bit newer than this list (a future pkms).'],
    ];

    /** The behaviour from before the setting existed. */
    public const DEFAULTS = [
        'normal' => [
            'handle' => 'restrict',
            'mem_scan' => 'restrict',
            'injected_keys' => 'restrict',
            'module_list' => 'restrict',
            'code_verify' => 'restrict',
            'write_protect' => 'restrict',
            'memory_guard' => 'restrict',
            'module_integrity' => 'restrict',
            'anti_debug' => 'restrict',
            'hwid' => 'log',
            'proxy_dll' => 'refuse',
            'unverified_ink_file' => 'log',
            'injected_clicks' => 'log',
            'cheat_sig' => 'restrict',
            'other' => 'restrict',
        ],
        'whitelisted' => [
            'handle' => 'log',
            'mem_scan' => 'log',
            'injected_keys' => 'log',
            'module_list' => 'log',
            'code_verify' => 'log',
            'write_protect' => 'log',
            'memory_guard' => 'log',
            'module_integrity' => 'log',
            'anti_debug' => 'log',
            'hwid' => 'log',
            'proxy_dll' => 'log',
            'unverified_ink_file' => 'log',
            'injected_clicks' => 'log',
            'cheat_sig' => 'log',
            'other' => 'log',
        ],
    ];

    /** @return array{normal: array<string, string>, whitelisted: array<string, string>} every key, filled from DEFAULTS. */
    public static function all(): array
    {
        $policy = static::DEFAULTS;
        $raw = TomYumSiteSettings::get(static::SETTING);
        if ($raw === null || $raw === '') {
            return $policy;
        }

        try {
            $decoded = json_decode($raw, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $policy;
        }
        if (!is_array($decoded)) {
            return $policy;
        }

        foreach (static::SIDES as $side) {
            if (!is_array($decoded[$side] ?? null)) {
                continue;
            }
            foreach ($decoded[$side] as $key => $action) {
                if (isset(static::FINDINGS[$key]) && is_string($action) && in_array($action, static::ACTIONS, true)) {
                    $policy[$side][$key] = $action;
                }
            }
        }

        return $policy;
    }

    public static function isDefault(?array $policy = null): bool
    {
        return ($policy ?? static::all()) === static::DEFAULTS;
    }

    /**
     * Saves the form's choices: {side: {key: action}}; anything unknown or missing keeps the default.
     * Saving the defaults clears the row, so later changes to DEFAULTS reach this server too.
     *
     * @return array{normal: array<string, string>, whitelisted: array<string, string>} what was saved
     */
    public static function save(array $input): array
    {
        $policy = static::DEFAULTS;
        foreach (static::SIDES as $side) {
            $values = is_array($input[$side] ?? null) ? $input[$side] : [];
            foreach (static::FINDINGS as $key => $_) {
                $action = $values[$key] ?? null;
                if (is_string($action) && in_array($action, static::ACTIONS, true)) {
                    $policy[$side][$key] = $action;
                }
            }
        }

        TomYumSiteSettings::set(static::SETTING, static::isDefault($policy) ? null : json_encode($policy, JSON_UNESCAPED_SLASHES));

        return $policy;
    }

    /** Short "key: old → new" list of what differs between two policies, for the staff log. */
    public static function describeChanges(array $before, array $after): string
    {
        $changes = [];
        foreach (static::SIDES as $side) {
            foreach (static::FINDINGS as $key => $_) {
                if ($before[$side][$key] !== $after[$side][$key]) {
                    $changes[] = ($side === 'whitelisted' ? 'whitelisted ' : '')."{$key}: {$before[$side][$key]} → {$after[$side][$key]}";
                }
            }
        }

        return $changes === [] ? 'no change' : implode(', ', $changes);
    }
}
