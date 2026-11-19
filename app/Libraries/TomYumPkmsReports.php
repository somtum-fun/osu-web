<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

/**
 * TomYum: what the pkms (client anti-cheat) reports left in TomYum's staff log (score.pkms_*), read back for the admin
 * panel's anti-cheat page: signers seen in the wild that aren't trusted yet, and one player's latest findings with the
 * verdict on each.
 */
class TomYumPkmsReports
{
    private const CACHE_SECONDS = 60;
    // TomYum's /log answers at most 500 entries.
    private const RECENT = 500;

    /**
     * The newest score.pkms_* staff log entries (cached a minute), [] when TomYum doesn't answer.
     *
     * @return array<int, array{id: int, createdAt: string, action: string, targetId: ?int, summary: string}>
     */
    public static function recent(): array
    {
        return \Cache::remember('tomyum_pkms_reports_recent', static::CACHE_SECONDS, function () {
            $response = (new TomYumAdmin())->get('/log', ['action' => 'score.pkms_', 'limit' => static::RECENT]);

            return $response->successful() ? ($response->json() ?? []) : [];
        });
    }

    /**
     * Signer CNs in recent reports' "[not in allowlist: X]" that aren't trusted yet, most seen first: what staff can
     * add with a click instead of typing CNs by hand.
     *
     * @param string[] $trusted the server's trusted signers (TomYumPkmsTrustedSigners::all())
     * @return array<int, array{signer: string, reports: int, players: int, last_seen: string, module: ?string}>
     */
    public static function signerSuggestions(array $trusted, int $limit = 15): array
    {
        $isTrusted = array_flip(array_map('mb_strtolower', $trusted));
        $bySigner = [];
        foreach (static::recent() as $entry) {
            if (!preg_match_all('/([^|\[]*?)\s*\[not in allowlist: ([^\]]+)\]/', $entry['summary'] ?? '', $matches, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($matches as [, $module, $signer]) {
                $signer = trim($signer);
                $key = mb_strtolower($signer);
                if ($signer === '' || isset($isTrusted[$key])) {
                    continue;
                }
                $row = $bySigner[$key] ?? ['signer' => $signer, 'reports' => 0, 'players' => [], 'last_seen' => $entry['createdAt'], 'module' => null];
                $row['reports']++;
                $row['players'][$entry['targetId'] ?? 0] = true;
                // Entries come newest first: the first module seen is the latest.
                $row['module'] ??= static::moduleName($module);
                $bySigner[$key] = $row;
            }
        }

        $rows = array_map(fn ($row) => [...$row, 'players' => count($row['players'])], array_values($bySigner));
        usort($rows, fn ($a, $b) => [$b['players'], $b['reports']] <=> [$a['players'], $a['reports']]);

        return array_slice($rows, 0, $limit);
    }

    /**
     * One player's pkms reports, newest first, each read into its parts: build, finding bits (with their names),
     * the details pkms sent, and the verdict (what the server did and why).
     *
     * @return array<int, array{id: int, at: string, action: string, build: ?string, bits: ?int, findings: string[], details: string[], verdict: ?string, summary: string}>|null null when TomYum doesn't answer
     */
    public static function forUser(int $userId, int $limit = 50): ?array
    {
        $response = (new TomYumAdmin())->get('/log', ['action' => 'score.pkms_', 'targetId' => $userId, 'limit' => $limit]);
        if (!$response->successful()) {
            return null;
        }

        return array_map(fn ($entry) => static::read($entry), $response->json() ?? []);
    }

    /** A report's parts, from its summary (as TomYum's score submission writes it). */
    public static function read(array $entry): array
    {
        $summary = (string) ($entry['summary'] ?? '');

        preg_match('/\bpkms\/[\w.+-]+/', $summary, $build);
        $bits = preg_match('/findings 0x([0-9A-Fa-f]+)/', $summary, $hex) ? (int) hexdec($hex[1]) : null;

        // The details are in the first [...] after the bits; "|" separates pkms's entries (ml:, mc:, hf:, tp:, cs:, ...).
        $details = [];
        $start = strpos($summary, '[');
        if ($start !== false) {
            $depth = 0;
            for ($i = $start; $i < strlen($summary); $i++) {
                $depth += $summary[$i] === '[' ? 1 : ($summary[$i] === ']' ? -1 : 0);
                if ($depth === 0) {
                    break;
                }
            }
            $inside = substr($summary, $start + 1, $i - $start - 1);
            $details = array_values(array_filter(array_map('trim', explode('|', $inside)), fn ($d) => $d !== ''));
        }

        $verdict = match (true) {
            str_starts_with($entry['action'] ?? '', 'score.pkms_restricted') => 'Restricted'.(preg_match('/\(policy: ([^)]+)\)/', $summary, $p) ? " (policy: {$p[1]})" : ''),
            preg_match('/\); (.+)$/', $summary, $tail) === 1 => rtrim($tail[1], '.'),
            preg_match('/decision (\w+)/', $summary, $decision) === 1 => "Decision: {$decision[1]}",
            default => null,
        };

        return [
            'id' => (int) ($entry['id'] ?? 0),
            'at' => (string) ($entry['createdAt'] ?? ''),
            'action' => (string) ($entry['action'] ?? ''),
            'build' => $build[0] ?? null,
            'bits' => $bits,
            'findings' => $bits === null ? [] : static::findingNames($bits),
            'details' => $details,
            'verdict' => $verdict,
            'summary' => $summary,
        ];
    }

    /** The names of the finding bits set in $bits (the panel's finding actions list them). */
    public static function findingNames(int $bits): array
    {
        $names = [];
        foreach (TomYumPkmsFindingPolicy::FINDINGS as [$bit, $name]) {
            if ($bit !== 0 && ($bits & $bit) === $bit) {
                $names[] = $name;
                $bits &= ~$bit;
            }
        }
        if ($bits !== 0) {
            $names[] = sprintf('other (0x%X)', $bits);
        }

        return $names;
    }

    /** "ml:foreign module injected: C:.Program Files.Vendor.Thing.dll (base 0x…)" → "Thing.dll". */
    private static function moduleName(string $text): ?string
    {
        return preg_match('/([^.\\\\\/:\s]+\.(?:dll|exe|sys))\b/i', $text, $m) ? $m[1] : null;
    }
}
