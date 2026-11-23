{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Beatmap mirrors'])

@php
    $fields = function (?array $mirror) {
        return [
            'name' => $mirror['name'] ?? '',
            'url' => $mirror['url'] ?? '',
            'download_path' => $mirror['download_path'] ?? '',
            'requests_per_minute' => $mirror['requests_per_minute'] ?? 60,
            'priority' => $mirror['priority'] ?? 100,
            'lookups' => $mirror['lookups'] ?? true,
            'downloads' => $mirror['downloads'] ?? false,
            'enabled' => $mirror['enabled'] ?? true,
        ];
    };
@endphp

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'Beatmap mirrors'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-server"></i>Beatmap mirrors</h2>
            <p class="tomyum-panel__hint">
                <strong>Lookups</strong>: when the game server meets a map it doesn't know (a score, a leaderboard, lazer's import), it asks
                these mirrors in order of priority (lower first), each no faster than its requests a minute. One that refuses (403), says
                "too many" (429) or fails is passed over for the next; when none answers, osu!'s own API is asked.
                The mirror must answer like osu!'s API v2 at <code>api/v2/md5|b|s/…</code> (catboy.best does).
                <strong>Downloads</strong>: players' <code>/d/</code> downloads go to the first enabled download mirror, at its download path
                (<code>{id}</code> is the set; <code>/d/{id}</code> when empty).
                The game server reads this list within a minute.
            </p>
            @if (!$isSet)
                <p class="tomyum-panel__hint">
                    No list yet: the game server uses its own settings (<code>Mirror:Url</code> for lookups, <code>Direct:MirrorBaseUrl</code> for downloads).
                    Adding a mirror here replaces them.
                </p>
            @else
                <form method="POST" action="{{ route('panel.admin.beatmap-mirrors.reset') }}" onsubmit="return confirm('Forget this list and use the game server\'s own settings?')">
                    @csrf
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Use the game server's own settings</button>
                </form>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-plus"></i>Add a mirror</h2>
            @include('panel.admin._beatmap_mirror_form', ['action' => route('panel.admin.beatmap-mirrors.store'), 'mirror' => $fields(null), 'button' => 'Add'])
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-list"></i>The list</h2>
            @if (count($mirrors) === 0)
                <p class="tomyum-panel__empty">{{ $isSet ? 'Empty: map lookups ask osu! only.' : 'None set here.' }}</p>
            @else
                @foreach ($mirrors as $index => $mirror)
                    @php($health = $mirror['health'])
                    <div style="border-top: 1px solid rgba(255,255,255,.08); padding-top: 10px; margin-top: 10px">
                        <p>
                            <strong>{{ $mirror['name'] ?? '?' }}</strong>
                            @if (!($mirror['enabled'] ?? true))
                                <span class="tomyum-panel__pill tomyum-panel__pill--bad">off</span>
                            @elseif ($health !== null && ($health['paused_until'] ?? 0) > time())
                                <span class="tomyum-panel__pill tomyum-panel__pill--bad">paused until {{ gmdate('H:i', $health['paused_until']) }} UTC</span>
                            @else
                                <span class="tomyum-panel__pill tomyum-panel__pill--good">in use</span>
                            @endif
                            @if ($health !== null)
                                <span class="tomyum-panel__hint">
                                    last problem: {{ $health['last_problem'] ?? '?' }}
                                    ({{ isset($health['last_problem_at']) ? gmdate('Y-m-d H:i', $health['last_problem_at']).' UTC' : '?' }})
                                </span>
                            @endif
                        </p>
                        @include('panel.admin._beatmap_mirror_form', [
                            'action' => route('panel.admin.beatmap-mirrors.update', ['index' => $index]),
                            'mirror' => $fields($mirror),
                            'button' => 'Save',
                        ])
                        <form method="POST" action="{{ route('panel.admin.beatmap-mirrors.destroy', ['index' => $index]) }}"
                            onsubmit="return confirm('Take {{ e($mirror['name'] ?? 'this mirror') }} off the list?')">
                            @csrf
                            <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Remove</button>
                        </form>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
@endsection
