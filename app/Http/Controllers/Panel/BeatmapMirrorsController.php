<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Libraries\TomYumBeatmapMirrors;
use Auth;
use Request;

/**
 * Admin panel: the beatmap mirrors the game server uses (TomYumBeatmapMirrors): which ones map lookups ask, in order of
 * priority, and where players' downloads go. TomYum reads the list every minute; with none set it uses its own
 * Mirror:* and Direct:* settings.
 */
class BeatmapMirrorsController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseAdmin(Auth::user()));

            return $next($request);
        });
    }

    public function index()
    {
        $health = TomYumBeatmapMirrors::health();

        return ext_view('panel.admin.beatmap_mirrors', [
            'isSet' => TomYumBeatmapMirrors::isSet(),
            'mirrors' => array_map(fn ($mirror) => $mirror + [
                'health' => $health[TomYumBeatmapMirrors::key((string) ($mirror['url'] ?? ''))] ?? null,
            ], TomYumBeatmapMirrors::all()),
        ]);
    }

    public function store()
    {
        $entry = $this->entryFromRequest();
        if (is_string($entry)) {
            return $this->back('error', $entry);
        }
        $mirrors = TomYumBeatmapMirrors::all();
        foreach ($mirrors as $mirror) {
            if (TomYumBeatmapMirrors::key((string) ($mirror['url'] ?? '')) === TomYumBeatmapMirrors::key($entry['url'])) {
                return $this->back('error', 'That mirror is on the list already: change it there.');
            }
        }
        $mirrors[] = $entry;
        TomYumBeatmapMirrors::save($mirrors);
        $this->record("added beatmap mirror {$entry['name']} ({$entry['url']})");

        return $this->back('success', "Added {$entry['name']}. The game server uses it within a minute.");
    }

    public function update($index)
    {
        $mirrors = TomYumBeatmapMirrors::all();
        $index = (int) $index;
        if (!isset($mirrors[$index])) {
            return $this->back('error', 'That mirror isn\'t on the list any more.');
        }
        $entry = $this->entryFromRequest();
        if (is_string($entry)) {
            return $this->back('error', $entry);
        }
        $mirrors[$index] = $entry;
        TomYumBeatmapMirrors::save($mirrors);
        $this->record("changed beatmap mirror {$entry['name']} ({$entry['url']})");

        return $this->back('success', "Saved {$entry['name']}. The game server uses it within a minute.");
    }

    public function destroy($index)
    {
        $mirrors = TomYumBeatmapMirrors::all();
        $index = (int) $index;
        if (!isset($mirrors[$index])) {
            return $this->back('error', 'That mirror isn\'t on the list any more.');
        }
        $removed = $mirrors[$index];
        array_splice($mirrors, $index, 1);
        TomYumBeatmapMirrors::save($mirrors);
        $this->record("removed beatmap mirror {$removed['name']} ({$removed['url']})");

        return $this->back('success', "Removed {$removed['name']}.".(count($mirrors) === 0
            ? ' No mirror is left: map lookups ask osu! only, and downloads go where the game server\'s own settings say.'
            : ''));
    }

    /** Back to the game server's own settings (Mirror:*, Direct:*). */
    public function reset()
    {
        TomYumBeatmapMirrors::save(null);
        $this->record('reset beatmap mirrors to the game server\'s settings');

        return $this->back('success', 'The game server uses its own mirror settings again.');
    }

    /** The mirror from the form, or why it can't be one. */
    private function entryFromRequest(): array|string
    {
        $name = trim((string) Request::input('name'));
        $url = trim((string) Request::input('url'));
        $path = trim((string) Request::input('download_path'));
        if ($name === '' || mb_strlen($name) > 50) {
            return 'Give it a name (up to 50 characters).';
        }
        if (($problem = TomYumBeatmapMirrors::problemWith($url)) !== null) {
            return $problem;
        }
        if ($path !== '' && (!str_starts_with($path, '/') || !str_contains($path, '{id}') || preg_match('/\s/', $path))) {
            return 'The download path starts with / and has {id} where the set goes, e.g. /d/{id}.';
        }
        $lookups = Request::boolean('lookups');
        $downloads = Request::boolean('downloads');
        if (!$lookups && !$downloads) {
            return 'Use it for lookups, downloads or both.';
        }

        return [
            'name' => $name,
            'url' => $url,
            'lookups' => $lookups,
            'downloads' => $downloads,
            'download_path' => $path === '' ? null : $path,
            'requests_per_minute' => max(1, min(1200, (int) Request::input('requests_per_minute', 60))),
            'priority' => max(0, min(1000, (int) Request::input('priority', 100))),
            'enabled' => Request::boolean('enabled'),
        ];
    }

    private function record(string $what): void
    {
        $this->staffLog('site.beatmap_mirrors', null, ucfirst($what).'.');
    }

    private function back(string $type, string $message)
    {
        return redirect(route('panel.admin.beatmap-mirrors.index'))->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
