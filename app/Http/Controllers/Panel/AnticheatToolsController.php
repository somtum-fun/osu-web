<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Libraries\TomYumPkmsReports;
use App\Libraries\TomYumPkmsScanSets;
use App\Libraries\TomYumPkmsTrustedSigners;
use App\Libraries\TomYumPkmsVersions;
use App\Models\User;
use Auth;
use Request;

/**
 * TomYum: the anti-cheat page's shortcuts for what used to need a shell (parts/part-ac-admin.md): promote a new pkms
 * build (the ones before it go to grace), trust a signer seen in players' reports with one click, one player's pkms
 * findings with the verdict on each, and the cheat-signature sets pkms scans for (TomYumPkmsScanSets).
 */
class AnticheatToolsController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseAdmin(Auth::user()));

            return $next($request);
        });
    }

    /**
     * Promotes a new pkms build: it's added (or set) to enabled, with its published DLL's SHA-256, and every other
     * enabled build goes to grace for the days picked (it's revoked after that), so players mid-session finish their
     * map. The registry as it was is kept in storage/app/pkms-versions-backups first.
     */
    public function promote()
    {
        $buildId = trim((string) Request::input('build_id', ''));
        if (!preg_match('/^pkms\/[\w.+-]{1,64}$/', $buildId)) {
            return $this->back('error', 'A build id looks like pkms/0.9.10.');
        }
        $sha = trim((string) Request::input('dll_sha256', ''));
        $dllSha256 = $sha === '' ? null : TomYumPkmsVersions::validDllSha256($sha);
        if ($sha !== '' && $dllSha256 === null) {
            return $this->back('error', 'The DLL SHA-256 must be 64 hex characters (several: separated by spaces).');
        }
        $graceDays = (int) Request::input('grace_days', 1);
        if ($graceDays < 1 || $graceDays > 7) {
            return $this->back('error', 'Grace is 1 to 7 days.');
        }
        $note = trim((string) Request::input('note', '')) ?: null;

        $before = TomYumPkmsVersions::all();
        $this->backup($before);

        $graceUntil = now()->addDays($graceDays)->toDateString();
        $graced = [];
        $entries = [];
        foreach ($before as $entry) {
            if ($entry['build_id'] === $buildId) {
                continue;
            }
            if ($entry['status'] === 'enabled') {
                $entry['status'] = 'grace';
                $entry['grace_until'] = $graceUntil;
                $graced[] = $entry['build_id'];
            }
            $entries[] = $entry;
        }
        $previous = array_values(array_filter($before, fn ($e) => $e['build_id'] === $buildId))[0] ?? null;
        array_unshift($entries, [
            'build_id' => $buildId,
            'status' => 'enabled',
            'grace_until' => null,
            'note' => $note ?? $previous['note'] ?? null,
            'dll_sha256' => $dllSha256 ?? $previous['dll_sha256'] ?? null,
        ]);

        TomYumPkmsVersions::save($entries);
        $graceText = $graced === [] ? '' : ' '.implode(', ', $graced).' in grace until '.$graceUntil.'.';
        $this->staffLog('site.pkms_versions', null, "pkms {$buildId} promoted to enabled".($dllSha256 !== null ? ' (with its DLL hash)' : '').'.'.$graceText);

        return $this->back('success', "{$buildId} is enabled.".$graceText.' TomYum picks it up within a minute.');
    }

    /** Trusts one signer from the suggestions (the reports' "[not in allowlist: X]"). */
    public function trustSigner()
    {
        $signer = trim((string) Request::input('signer', ''));
        if (!TomYumPkmsTrustedSigners::isValidSigner($signer)) {
            return $this->back('error', 'Signer CN must be 1-'.TomYumPkmsTrustedSigners::MAX_LENGTH.' visible characters.');
        }

        $signers = TomYumPkmsTrustedSigners::all();
        if (in_array(mb_strtolower($signer), array_map('mb_strtolower', $signers), true)) {
            return $this->back('success', "{$signer} is already trusted.");
        }
        $signers[] = $signer;
        TomYumPkmsTrustedSigners::save($signers);
        $this->staffLog('site.pkms_trusted_signers', null, "pkms trusted signer added: {$signer} (from players' reports).");

        return $this->back('success', "{$signer} is trusted. Its modules stop flagging within a minute.");
    }

    /** One player's pkms reports: build, findings, what pkms sent, and the verdict. */
    public function findings()
    {
        $name = presence(trim((string) Request::input('player')));
        $player = $name === null ? null : (ctype_digit($name) ? User::find((int) $name) : User::lookupWithHistory($name, 'username'));

        $reports = $player === null ? [] : TomYumPkmsReports::forUser($player->getKey());

        return ext_view('panel.admin.anticheat_findings', [
            'name' => $name,
            'player' => $player,
            'reports' => $reports,
            'problem' => match (true) {
                $name !== null && $player === null => "There's no player called \"{$name}\".",
                $reports === null => "The game server didn't answer.",
                default => null,
            },
        ]);
    }

    /** A scan set's needles to edit ($name "new": a new set). */
    public function scanSet(string $name)
    {
        $set = $name === 'new' ? null : (TomYumPkmsScanSets::find($name) ?? abort(404));

        return ext_view('panel.admin.anticheat_scan_set', ['set' => $set]);
    }

    /**
     * Saves a scan set as a new revision (a new id). Needles are rows of enabled / tag / kind (text or hex) / value; an
     * empty row is skipped, a row ticked "remove" is dropped.
     */
    public function scanSetSave()
    {
        $existing = presence(trim((string) Request::input('existing')));
        $name = $existing ?? trim((string) Request::input('name'));
        $toEdit = fn (string $type, string $message) => redirect(route('panel.admin.anticheat.scan-set', ['name' => $existing ?? 'new']))
            ->withInput()
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);

        if ($existing !== null && TomYumPkmsScanSets::find($existing) === null) {
            return $this->back('error', "There's no scan set called {$existing}.");
        }
        if ($existing === null) {
            if (!preg_match(TomYumPkmsScanSets::NAME_PATTERN, $name)) {
                return $toEdit('error', 'A set name is 1-24 of A-Z a-z 0-9 _ -.');
            }
            if (TomYumPkmsScanSets::find($name) !== null) {
                return $toEdit('error', "There's already a set called {$name}.");
            }
            if (count(TomYumPkmsScanSets::all()) >= TomYumPkmsScanSets::MAX_SETS) {
                return $toEdit('error', 'At most '.TomYumPkmsScanSets::MAX_SETS.' sets: delete one first.');
            }
        }

        $tags = (array) Request::input('needle_tag', []);
        $kinds = (array) Request::input('needle_kind', []);
        $values = (array) Request::input('needle_value', []);
        $enabled = (array) Request::input('needle_enabled', []);
        $remove = (array) Request::input('needle_remove', []);
        $needles = [];
        foreach ($values as $i => $value) {
            $value = (string) $value;
            $tag = trim((string) ($tags[$i] ?? ''));
            if (isset($remove[$i]) || ($value === '' && $tag === '')) {
                continue;
            }
            $hex = ($kinds[$i] ?? 'text') === 'hex';
            $needles[] = [
                'tag' => mb_substr($tag, 0, 64),
                ...($hex ? ['hex' => preg_replace('/\s+/', '', $value)] : ['text' => $value]),
                'enabled' => isset($enabled[$i]),
            ];
        }

        $problems = TomYumPkmsScanSets::problems($needles);
        if ($problems !== []) {
            return $toEdit('error', 'Not saved: '.implode(' ', $problems));
        }

        $set = TomYumPkmsScanSets::save(
            $name,
            $needles,
            presence(mb_substr(trim((string) Request::input('note')), 0, 200)),
            $existing === null ? false : null,
            Auth::id(),
        );
        // No needle in the log: the staff log reaches Discord, and a needle is a hint to a cheat's maker.
        $this->staffLog('site.pkms_scan_sets', null, "Scan set {$name} saved as {$set['id']}.");

        return redirect(route('panel.admin.anticheat.scan-set', ['name' => $name]))
            ->with('panel_status', ['type' => 'success', 'message' => "Saved as {$set['id']}.".($set['enabled'] ? ' pkms gets it within a minute.' : ' It isn\'t enabled: pkms doesn\'t get it.'), 'link' => null]);
    }

    /** Turns a set on (the others off: pkms gets one) or off. */
    public function scanSetEnabled(string $name)
    {
        $on = Request::boolean('enabled');
        if (!TomYumPkmsScanSets::setEnabled($name, $on)) {
            return $this->back('error', "There's no scan set called {$name}.");
        }
        $this->staffLog('site.pkms_scan_sets', null, "Scan set {$name} ".($on ? 'enabled' : 'disabled').'.');

        return $this->back('success', $on ? "pkms gets {$name} within a minute." : "{$name} is off. pkms gets none until a set is enabled.");
    }

    public function scanSetDelete(string $name)
    {
        if (!TomYumPkmsScanSets::delete($name)) {
            return $this->back('error', "There's no scan set called {$name}.");
        }
        $this->staffLog('site.pkms_scan_sets', null, "Scan set {$name} deleted.");

        return $this->back('success', "Deleted {$name} (a backup was kept).");
    }

    private function back(string $type, string $message)
    {
        return redirect(route('panel.admin.anticheat'))->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }

    /** The registry as it was, before a promote changes it (storage/app/pkms-versions-backups/<UTC>.json). */
    private function backup(array $entries): void
    {
        $dir = storage_path('app/pkms-versions-backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        file_put_contents($dir.'/pkms_versions_json-'.gmdate('Ymd\THis\Z').'.json', json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
