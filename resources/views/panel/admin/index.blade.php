{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    $actions = ['Idle', 'AFK', 'Playing', 'Editing', 'Modding', 'In multiplayer', 'Watching', 'Unknown', 'Testing', 'Submitting', 'Paused', 'In the lobby', 'Multiplaying', 'osu!direct'];
    $modes = ['osu!', 'osu!taiko', 'osu!catch', 'osu!mania'];
    $userIds = array_filter(array_map(fn ($e) => str_starts_with($e['action'], 'beatmapset.') ? null : $e['targetId'], $entries));
    $targets = App\Http\Controllers\Panel\GmtController::userNames($userIds);
@endphp
@extends('master', ['titlePrepend' => 'Admin panel'])

@section('content')
    @include('panel._header', ['crumbs' => [['url' => route('panel.admin.index'), 'title' => 'Admin panel']]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <p class="tomyum-panel__section-title">Server</p>
        <div class="tomyum-panel__links tomyum-panel__links--stats">
            <div class="tomyum-panel__link js-tomyum-live">
                <span class="tomyum-panel__kicker">Game server</span>
                @if ($server !== null)
                    <span class="tomyum-panel__stat"><span class="tomyum-panel__live-dot"></span>{{ number_format($server['online']) }} online</span>
                    <p class="tomyum-panel__hint">TomYum {{ $server['version'] }} &middot; up {{ Carbon\Carbon::parse($server['startedAt'])->diffForHumans(null, true) }}</p>
                @else
                    <span class="tomyum-panel__stat"><span class="tomyum-panel__live-dot tomyum-panel__live-dot--off"></span>Unreachable</span>
                    <p class="tomyum-panel__hint">The website can't reach the game server.</p>
                @endif
            </div>
            @foreach ($counts as $label => $count)
                <div class="tomyum-panel__link">
                    <span class="tomyum-panel__kicker">{{ $label }}</span>
                    <span class="tomyum-panel__stat">{{ number_format($count) }}</span>
                </div>
            @endforeach
        </div>

        {{-- How the server is doing lately: hover a bar or the line for its numbers. --}}
        <p class="tomyum-panel__section-title">Lately</p>
        <div class="tomyum-panel__charts">
            <div class="tomyum-panel__card">
                <h2><i class="fas fa-signal"></i>In game, last 24 hours</h2>
                @include('panel._chart', ['points' => $charts['online'], 'type' => 'line', 'empty' => 'Counting started: the first numbers show within 5 minutes.'])
            </div>
            <div class="tomyum-panel__card">
                <h2><i class="fas fa-chart-bar"></i>Scores per day</h2>
                @include('panel._chart', ['points' => $charts['scores'], 'type' => 'bars'])
            </div>
            <div class="tomyum-panel__card">
                <h2><i class="fas fa-user-plus"></i>Sign-ups per day</h2>
                @include('panel._chart', ['points' => $charts['signups'], 'type' => 'bars'])
            </div>
        </div>

        <p class="tomyum-panel__section-title">Tools</p>
        <div class="tomyum-panel__links tomyum-panel__links--rows">
            @foreach ([
                ['panel.admin.daily-challenge.index', 'fa-calendar-day', 'lazer', 'Daily challenge', 'Pick and order the maps'],
                ['panel.admin.matchmaking.index', 'fa-trophy', 'lazer', 'Ranked Play', 'Pools and their maps'],
                ['panel.admin.mods.index', 'fa-sliders-h', 'lazer', 'Ranked mods', 'Which mods give pp and medals'],
                ['panel.admin.badges.index', 'fa-certificate', 'profiles', 'Badges', 'Give and take profile badges'],
                ['panel.admin.assets.index', 'fa-photo-video', 'assets host', 'Assets', 'Badge, banner and medal images'],
                ['panel.admin.titles.index', 'fa-id-badge', 'profiles', 'Titles', "The line under a player's name"],
                ['panel.admin.tournament-banners.index', 'fa-trophy', 'profiles', 'Tournament banners', 'Banners under players\' headers'],
                ['panel.admin.news.index', 'fa-newspaper', 'website & lazer', 'News', 'Write and edit posts'],
                ['panel.admin.seasonal-backgrounds.index', 'fa-image', 'lazer & stable', 'Seasonal backgrounds', "Menu backgrounds and the season's end"],
                ['panel.admin.menu-banners.index', 'fa-images', 'lazer', 'Menu banners', 'The main menu\'s rotating banners'],
                ['panel.admin.branding.index', 'fa-signature', 'website', 'Branding', 'Name, logo, favicons and texts'],
                ['panel.admin.artists.index', 'fa-music', 'website', 'Featured artists', 'Artists, albums and tracks'],
                ['panel.admin.contests.index', 'fa-award', 'website', 'Contests', 'Art, beatmap and music contests'],
                ['panel.packs.index', 'fa-box-open', 'website', 'Beatmap packs', 'Make packs and their downloads'],
                ['panel.changelog.index', 'fa-code-branch', 'website & lazer', 'Changelog', 'Versions and their changes'],
            ] as [$route, $icon, $kicker, $title, $hint])
                <a class="tomyum-panel__link tomyum-panel__link--row" href="{{ route($route) }}">
                    <span class="tomyum-panel__link-icon"><i class="fas {{ $icon }}"></i></span>
                    <span class="tomyum-panel__link-text">
                        <span class="tomyum-panel__kicker">{{ $kicker }}</span>
                        <h2>{{ $title }}</h2>
                        <span class="tomyum-panel__hint">{{ $hint }}</span>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-user-cog"></i>Accounts</h2>
            <form method="GET" action="{{ route('panel.admin.users.index') }}" class="tomyum-panel__row">
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Username, user id or email</span>
                    <input class="input-text" type="text" name="q">
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Find</button>
            </form>
        </div>

        <div class="tomyum-panel__card {{ ($maintenance['enabled'] ?? false) ? 'tomyum-panel__card--danger' : '' }}" id="maintenance">
            <h2><i class="fas fa-tools"></i>Maintenance</h2>
            @if ($maintenance['enabled'] ?? false)
                <p>
                    <span class="tomyum-panel__badge tomyum-panel__badge--error">{{ ($maintenance['locked'] ?? false) ? 'ON' : 'STARTING' }}</span>
                    @if (!($maintenance['locked'] ?? false) && $maintenance['startsAt'])
                        Locks {!! timeago(Carbon\Carbon::parse($maintenance['startsAt'])) !!}.
                    @else
                        Only staff can log in (stable, lazer, IRC) since {!! $maintenance['since'] ? timeago(Carbon\Carbon::parse($maintenance['since'])) : '—' !!}.
                    @endif
                </p>
                <p class="tomyum-panel__hint">Players see: "{{ $maintenance['notice'] }}". Their game keeps reconnecting and gets back in on its own when you end it.</p>
                <form method="POST" action="{{ route('panel.admin.maintenance') }}">
                    @csrf
                    <input type="hidden" name="action" value="stop">
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-play"></i>End maintenance</button>
                </form>
            @else
                <p class="tomyum-panel__hint">
                    Keeps everyone but staff (admins, developers, GMT) out of stable, lazer and IRC. Players are told at once (an in-game
                    alert, and a BanchoBot message in lazer); when the lock starts, stable is disconnected and keeps reconnecting until
                    maintenance ends, then gets back in by itself. Score submission stays open, so plays in progress are kept. The website stays up.
                </p>
                <form method="POST" action="{{ route('panel.admin.maintenance') }}" class="tomyum-panel__row" style="align-items: flex-end"
                    onsubmit="return confirm('Start maintenance? Players will be disconnected.')">
                    @csrf
                    <input type="hidden" name="action" value="start">
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Message for players (optional)</span>
                        <input class="input-text" type="text" name="message" maxlength="500" placeholder="{{ $GLOBALS['cfg']['tomyum']['server_name'].' is under maintenance. You\'ll be reconnected automatically when it\'s over.' }}">
                    </label>
                    <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 170px">
                        <span class="input-container__label">Lock</span>
                        <select class="input-text" name="delay">
                            <option value="0">in 10 seconds (countdown)</option>
                            <option value="60">in 1 minute</option>
                            <option value="300" selected>in 5 minutes</option>
                            <option value="600">in 10 minutes</option>
                            <option value="1800">in 30 minutes</option>
                        </select>
                    </label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit"><i class="fas fa-tools"></i>Start maintenance</button>
                </form>
            @endif
        </div>

        {{-- TomYum: operator kill switch for the client-side AC (AdminController@anticheatKillSwitch). --}}
        <div class="tomyum-panel__card" id="anticheat-killswitch">
            <h2>
                <i class="fas fa-shield-alt"></i>Client anti-cheat
                @if ($anticheatKillSwitch['clientRequired'])
                    <span class="tomyum-panel__badge tomyum-panel__badge--success">ON</span>
                @else
                    <span class="tomyum-panel__badge tomyum-panel__badge--error">OFF</span>
                @endif
            </h2>
            <p class="tomyum-panel__hint">
                When ON, modern-stable scores need the Somtum Patcher (pkms) running. OFF lets anyone
                on plain osu! pointed at <code>-devserver blueskychan.dev</code> submit scores — use
                for incident response only.
            </p>
            <form method="POST" action="{{ route('panel.admin.anticheat-killswitch') }}" class="tomyum-panel__row" style="align-items: flex-end">
                @csrf
                <label class="tomyum-panel__field" style="flex: 0 0 auto; display: flex; gap: 6px; align-items: center">
                    <input type="checkbox" name="client_required" value="1" @if ($anticheatKillSwitch['clientRequired']) checked @endif>
                    pkms required
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save</button>
            </form>
        </div>

        <div class="tomyum-panel__card" id="development-banner">
            {{-- On or off beside the title, the explanation as a quiet note. --}}
            <h2>
                <i class="fas fa-flask"></i>Development banner
                @if ($developmentBanner['enabled'])
                    <span class="tomyum-panel__badge tomyum-panel__badge--error">ON</span>
                @else
                    <span class="tomyum-panel__badge">OFF</span>
                @endif
            </h2>
            <p class="tomyum-panel__hint">
                The red bar at the bottom of every page of the website. Markdown works for links:
                <code>[osu! website](https://osu.ppy.sh)</code>. Leave the message empty for the default one.
            </p>
            <form method="POST" action="{{ route('panel.admin.development-banner') }}" class="tomyum-panel__row" style="align-items: flex-end">
                @csrf
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Message</span>
                    <input class="input-text" type="text" name="message" maxlength="500"
                        value="{{ $developmentBanner['message'] === App\Libraries\TomYumSiteSettings::DEVELOPMENT_BANNER_DEFAULT ? '' : $developmentBanner['message'] }}"
                        placeholder="{{ App\Libraries\TomYumSiteSettings::DEVELOPMENT_BANNER_DEFAULT }}">
                </label>
                <label class="tomyum-panel__field" style="flex: 0 0 auto; display: flex; gap: 6px; align-items: center">
                    <input type="checkbox" name="enabled" value="1" @if ($developmentBanner['enabled']) checked @endif>
                    Show it
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save</button>
            </form>
        </div>

        <div class="tomyum-panel__card" id="bancho-welcome">
            <h2>
                <i class="fas fa-comment-dots"></i>In-game welcome
                @if ($banchoWelcome['enabled'])
                    <span class="tomyum-panel__badge tomyum-panel__badge--success">ON</span>
                @else
                    <span class="tomyum-panel__badge">OFF</span>
                @endif
            </h2>
            <p class="tomyum-panel__hint">
                The notice osu! (stable) shows when a player logs in. <code>{username}</code> is the player's name.
                Leave the message empty for the default one. Logins use a change within a minute.
            </p>
            <form method="POST" action="{{ route('panel.admin.bancho-welcome') }}" class="tomyum-panel__row" style="align-items: flex-end">
                @csrf
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Message</span>
                    <input class="input-text" type="text" name="message" maxlength="500"
                        value="{{ $banchoWelcome['message'] === App\Libraries\TomYumSiteSettings::BANCHO_WELCOME_DEFAULT ? '' : $banchoWelcome['message'] }}"
                        placeholder="{{ App\Libraries\TomYumSiteSettings::BANCHO_WELCOME_DEFAULT }}">
                </label>
                <label class="tomyum-panel__field" style="flex: 0 0 auto; display: flex; gap: 6px; align-items: center">
                    <input type="checkbox" name="enabled" value="1" @if ($banchoWelcome['enabled']) checked @endif>
                    Show it
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save</button>
            </form>
        </div>

        {{-- TomYum: TumThai (lazer) version gating (AdminController@tumthai); the API middleware TomYumLazerVersionGate enforces it. --}}
        <div class="tomyum-panel__card" id="tumthai">
            <h2>
                <i class="fas fa-gamepad"></i>TumThai versions
                @if ($tumthai['unrestricted'])
                    <span class="tomyum-panel__badge">OPEN</span>
                @else
                    <span class="tomyum-panel__badge tomyum-panel__badge--success">GATED</span>
                @endif
            </h2>
            <p class="tomyum-panel__hint">
                Which TumThai (lazer) builds can connect. Lazer sends its build as <code>x-api-version</code> (YYYYMMDD; 0 for a local build).
                A disallowed build sees "Realtime online functionality is not supported on this version of the game. Please upgrade to the latest version." (403).
                Leave the minimum blank (or 0) and the whitelist blank for no limit. If a whitelist is set, only those exact builds are allowed; otherwise the minimum applies.
            </p>
            <form method="POST" action="{{ route('panel.admin.tumthai') }}" class="tomyum-panel__row" style="align-items: flex-end; flex-wrap: wrap">
                @csrf
                <label class="input-container tomyum-panel__field" style="flex: 0 1 200px">
                    <span class="input-container__label">Minimum build (YYYYMMDD)</span>
                    <input class="input-text" type="number" name="min" min="0" step="1" value="{{ $tumthai['min'] ?: '' }}" placeholder="e.g. 20260818">
                </label>
                <label class="input-container tomyum-panel__field" style="flex: 1 1 320px">
                    <span class="input-container__label">Whitelist (comma or space separated, YYYYMMDD)</span>
                    <input class="input-text" type="text" name="whitelist" value="{{ implode(', ', $tumthai['whitelist']) }}" placeholder="e.g. 20260818, 20260924">
                </label>
                <label class="tomyum-panel__field" style="flex: 0 0 auto; display: flex; gap: 6px; align-items: center">
                    <input type="checkbox" name="allow_local" value="1" @if ($tumthai['allowLocal']) checked @endif>
                    Allow local builds (version 0)
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save</button>
            </form>
        </div>

        {{-- TomYum: pkms version registry (AdminController@pkmsVersions); TomYum's handshake-flag plugin enforces it. --}}
        <div class="tomyum-panel__card" id="pkms-versions">
            <h2>
                <i class="fas fa-shield-alt"></i>pkms versions
                @if (count($pkmsVersions) === 0)
                    <span class="tomyum-panel__badge">OPEN</span>
                @else
                    <span class="tomyum-panel__badge tomyum-panel__badge--success">{{ count($pkmsVersions) }}</span>
                @endif
            </h2>
            <p class="tomyum-panel__hint">
                Modern-stable anti-cheat (pkms) sends its <code>build_id</code> on every handshake (e.g.&nbsp;<code>pkms/0.3.1-test2</code>).
                <strong>revoked</strong> rejects the handshake (client is dropped). <strong>grace</strong> accepts the handshake with an "update required" marker until <em>grace_until</em>, then it becomes revoked.
                <strong>enabled</strong> is the normal state; a build id that isn't in the list is also allowed (default accept). Changes apply within a minute without a server restart.
            </p>
            <form method="POST" action="{{ route('panel.admin.pkms-versions') }}">
                @csrf
                <table class="tomyum-panel__table">
                    <thead>
                        <tr>
                            <th>build_id</th>
                            <th style="width: 110px">status</th>
                            <th style="width: 130px">grace_until (YYYY-MM-DD)</th>
                            <th>note</th>
                            <th style="width: 50px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pkmsVersions as $i => $version)
                            <tr>
                                <td><input class="input-text" type="text" name="build_id[{{ $i }}]" value="{{ $version['build_id'] }}" required></td>
                                <td>
                                    <select class="input-text" name="status[{{ $i }}]">
                                        @foreach (App\Libraries\TomYumPkmsVersions::STATUSES as $status)
                                            <option value="{{ $status }}" @if ($status === $version['status']) selected @endif>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input class="input-text" type="date" name="grace_until[{{ $i }}]" value="{{ $version['grace_until'] }}"></td>
                                <td><input class="input-text" type="text" name="note[{{ $i }}]" value="{{ $version['note'] }}"></td>
                                <td style="text-align: center"><label><input type="checkbox" name="delete[{{ $i }}]" value="1"> delete</label></td>
                            </tr>
                        @endforeach
                        <tr>
                            <td><input class="input-text" type="text" name="new_build_id" placeholder="pkms/0.3.1-test2"></td>
                            <td>
                                <select class="input-text" name="new_status">
                                    @foreach (App\Libraries\TomYumPkmsVersions::STATUSES as $status)
                                        <option value="{{ $status }}">{{ $status }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input class="input-text" type="date" name="new_grace_until"></td>
                            <td><input class="input-text" type="text" name="new_note" placeholder="(optional)"></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
                <div class="tomyum-panel__row" style="margin-top: 10px">
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save</button>
                    <a class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" href="{{ route('panel.admin.log', ['kind' => 'score.pkms_']) }}"><i class="fas fa-flag"></i>Flagged scores</a>
                </div>
            </form>
        </div>

        {{-- TomYum: what each pkms finding does (AdminController@pkmsFindingPolicy); TomYum's score submission applies it. --}}
        <div class="tomyum-panel__card" id="pkms-finding-policy">
            <h2>
                <i class="fas fa-gavel"></i>pkms finding actions
                @if (App\Libraries\TomYumPkmsFindingPolicy::isDefault($pkmsFindingPolicy))
                    <span class="tomyum-panel__badge">DEFAULT</span>
                @else
                    <span class="tomyum-panel__badge tomyum-panel__badge--warn">CUSTOM</span>
                @endif
            </h2>
            <p class="tomyum-panel__hint">
                What the client anti-cheat's findings do when a modern-stable or legacy score is submitted, for ordinary players and for players on the anti-cheat whitelist.
                <strong>log</strong> only writes the staff log; <strong>refuse</strong> refuses the score and tells the player why (no restriction);
                <strong>restrict</strong> restricts the player and keeps the score in the restricted pool for review. When a score has several findings, the strongest action wins.
                Staff accounts and already-restricted players are never restricted automatically. Changes apply within a minute.
            </p>
            <form method="POST" action="{{ route('panel.admin.pkms-finding-policy') }}">
                @csrf
                <table class="tomyum-panel__table">
                    <thead>
                        <tr>
                            <th style="width: 70px">bit</th>
                            <th>finding</th>
                            <th style="width: 130px">players</th>
                            <th style="width: 130px">whitelisted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (App\Libraries\TomYumPkmsFindingPolicy::FINDINGS as $key => [$bit, $name, $about])
                            <tr>
                                <td><code>{{ $bit === 0 ? 'other' : sprintf('0x%X', $bit) }}</code></td>
                                <td><strong>{{ $name }}</strong><br><span class="tomyum-panel__hint" style="margin: 0">{{ $about }}</span></td>
                                @foreach (App\Libraries\TomYumPkmsFindingPolicy::SIDES as $side)
                                    <td>
                                        <select class="input-text" name="{{ $side }}[{{ $key }}]">
                                            @foreach (App\Libraries\TomYumPkmsFindingPolicy::ACTIONS as $action)
                                                <option value="{{ $action }}" @if ($pkmsFindingPolicy[$side][$key] === $action) selected @endif>
                                                    {{ $action }}{{ App\Libraries\TomYumPkmsFindingPolicy::DEFAULTS[$side][$key] === $action ? ' (default)' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="tomyum-panel__row" style="margin-top: 10px">
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save</button>
                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" type="submit" name="reset" value="1" onclick="return confirm('Put every finding back to its default action?')"><i class="fas fa-undo"></i>Reset to defaults</button>
                    <a class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" href="{{ route('panel.admin.log', ['kind' => 'score.pkms_']) }}"><i class="fas fa-flag"></i>Flagged scores</a>
                </div>
            </form>
        </div>

        {{-- TomYum: pkms handle-requester allowlist (AdminController@handleRequesters). --}}
        <div class="tomyum-panel__card" id="handle-requesters">
            <h2>
                <i class="fas fa-shield-alt"></i>pkms handle requesters
                @if (count($handleRequesters) === 0)
                    <span class="tomyum-panel__badge">EMPTY</span>
                @else
                    <span class="tomyum-panel__badge tomyum-panel__badge--success">{{ count($handleRequesters) }}</span>
                @endif
            </h2>
            <p class="tomyum-panel__hint">
                Which external processes may hold a <code>PROCESS_VM_READ</code> handle to osu! while pkms is attached. Each row pins a <strong>basename</strong> to a <strong>sha256</strong> of the exact exe (so a renamed cheat masquerading as <code>tosu.exe</code> fails the hash check). pkms refreshes this list every few minutes — <strong>no pkms rebuild</strong> on changes.
                Hash: <code>certutil -hashfile &lt;file&gt; SHA256</code> on Windows, or <code>sha256sum</code> on Linux.
            </p>
            <form method="POST" action="{{ route('panel.admin.handle-requesters') }}">
                @csrf
                <table class="tomyum-panel__table">
                    <thead>
                        <tr>
                            <th style="width: 180px">basename</th>
                            <th>sha256 (64 lowercase hex)</th>
                            <th>note</th>
                            <th style="width: 50px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($handleRequesters as $i => $requester)
                            <tr>
                                <td><input class="input-text" type="text" name="basename[{{ $i }}]" value="{{ $requester['basename'] }}" required></td>
                                <td><input class="input-text" type="text" name="sha256[{{ $i }}]" value="{{ $requester['sha256'] }}" pattern="[0-9a-f]{64}" required style="font-family: monospace"></td>
                                <td><input class="input-text" type="text" name="note[{{ $i }}]" value="{{ $requester['note'] }}"></td>
                                <td style="text-align: center"><label><input type="checkbox" name="delete[{{ $i }}]" value="1"> delete</label></td>
                            </tr>
                        @endforeach
                        <tr>
                            <td><input class="input-text" type="text" name="new_basename" placeholder="tosu.exe"></td>
                            <td><input class="input-text" type="text" name="new_sha256" placeholder="0b365384f245..." pattern="[0-9a-f]{64}" style="font-family: monospace"></td>
                            <td><input class="input-text" type="text" name="new_note" placeholder="tosu v4.26.2"></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
                <div class="tomyum-panel__row" style="margin-top: 10px">
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save</button>
                </div>
            </form>
        </div>

        {{-- TomYum: OpenTabletDriver filter masterlist (TabletPluginPolicy in TomYum.Server). --}}
        <div class="tomyum-panel__card" id="otd-filters">
            <h2>
                <i class="fas fa-pen-nib"></i>OpenTabletDriver filter masterlist
                <span class="tomyum-panel__badge tomyum-panel__badge--success">AKATSUKI POLICY</span>
            </h2>
            <p class="tomyum-panel__hint">
                pkms queries OpenTabletDriver's live daemon over IPC (<code>GetSettings</code> / <code>GetTablets</code>) — falling back to <code>settings.json</code> and the Plugins folder only when OTD is running but its IPC can't be reached (pkms 0.8.0+, marked "from settings.json").
                Disabled blacklisted filters are logged like any other plugin and are not treated as banned. Each player's full filter breakdown is shown on their account page under <strong>Client &amp; OTD</strong> instead of cluttering <code>!client</code> in chat.
            </p>
            <table class="tomyum-panel__table">
                <thead>
                    <tr>
                        <th style="width: 190px">Category</th>
                        <th>Filters / plugins &amp; parameter limits</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="tomyum-panel__badge tomyum-panel__badge--error">Banned</span></td>
                        <td>
                            <code>Chatter Generator</code>, <code>sin(cursor)</code>, <code>SpringInterpolator</code>, <code>MeL (entire suite)</code>, <code>Windows Ink</code>, <code>Saturn (Position &amp; Velocity Interpolation)</code>, <code>Stroke Snapping</code>, <code>BezierInterpolator</code>
                        </td>
                    </tr>
                    <tr>
                        <td><span class="tomyum-panel__badge tomyum-panel__badge--warn">Allowed with exceptions</span></td>
                        <td>
                            <div style="display: flex; flex-direction: column; gap: 4px">
                                <div><strong>Temporal Resampler:</strong> Prediction Ratio &gt; 0 banned; Frame Time Shift &gt; 0 banned; Reverse EMA &lt; 0.6 banned; Reverse EMA with another filter's EMA banned.</div>
                                <div><strong>Reconstructor:</strong> EMA Weight &lt; 0.6 banned; use with another filter's EMA banned.</div>
                                <div><strong>jaaakb's ScuffedInter/Extrapolator:</strong> Interpolation &lt; 1 banned.</div>
                                <div><strong>Devocub Antichatter (<code>DevocubFilters</code> / <code>Antichatter</code>):</strong> Any use of Prediction (<code>PredictionEnabled=true</code>) is banned.</div>
                                <div><strong>Saturn (Non-Interpolated):</strong> Reverse EMA &lt; 0.6 banned; Stock EMA weight &lt; 1 banned; Reverse EMA with another filter's EMA banned.</div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><span class="tomyum-panel__badge tomyum-panel__badge--warn">Use at own risk</span></td>
                        <td><code>MouseUtils</code>, <code>ScriptRunner</code></td>
                    </tr>
                    <tr>
                        <td><span class="tomyum-panel__badge tomyum-panel__badge--success">100% Legal</span></td>
                        <td>
                            <code>Chatter Exterminator</code>, <code>Radial Follow Smoothing</code>, <code>Wireless Kit Addon</code>, <code>Touch Gestures Installer</code>, <code>Tablet Debounce</code>, <code>Scroll Bindings</code>, <code>Hand Speed Viewer</code>, <code>Monitor Toggle</code>, <code>MabletMapping</code>, <code>LED Sandbox</code>, <code>Hover Distance Limiter</code>, <code>Flip Axes</code>, <code>BetterCalibrator</code>, <code>OpenKneeboard OTD-IPC</code>, <code>Precision Control</code>, <code>PSSA</code>, <code>Relative Mode Area</code>, <code>Tablet Calibration</code>, <code>Tilt Calibration</code>, <code>HawkuFilters</code>, <code>DevocubSmoothing</code>, <code>Tablet Area Randomizer</code>, <code>TouchEmu</code>, <code>Circular Area</code>, <code>SlimyScylla</code>, <code>Cycle Keybind</code>, <code>DragThreshold</code>, <code>Additional Keys</code>, <code>DualActionBinds</code>, <code>Enhanced Output modes</code>, <code>Erase button</code>, <code>Ux remote</code>, <code>VMultiMode</code>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="tomyum-panel__row" style="margin-top: 10px">
                <a class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" href="{{ route('panel.admin.log', ['kind' => 'score.tablet_plugins']) }}"><i class="fas fa-flag"></i>Flagged OTD scores</a>
            </div>
        </div>

        <div class="tomyum-panel__card" id="alert">
            <h2><i class="fas fa-bullhorn"></i>In-game alert</h2>
            <p class="tomyum-panel__hint">A notification to everyone online in stable (osu!'s yellow pop-up); lazer players get it as a BanchoBot message.</p>
            <form method="POST" action="{{ route('panel.admin.alert') }}" class="tomyum-panel__row" style="align-items: flex-end">
                @csrf
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Message</span>
                    <input class="input-text" type="text" name="message" maxlength="500" required>
                </label>
                <label class="tomyum-panel__check"><input type="checkbox" name="lazer" value="1" checked> Also lazer</label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-bullhorn"></i>Send alert</button>
            </form>
        </div>

        @php
            $ppReworks = App\Libraries\TomYumPpRework::status();
        @endphp
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-calculator"></i>pp rework</h2>
            <p class="tomyum-panel__hint">
                Works out every score's pp again in the modes you pick, as osu! does when its pp changes. While it runs, those modes'
                rankings stay as they were and new scores' pp waits; then everything switches over at once. Vanilla modes then wait
                for the website's score processor too.
            </p>
            @foreach ($ppReworks['active'] ?? [] as $rework)
                <div class="tomyum-panel__item">
                    <strong>#{{ $rework['id'] }}: {{ App\Libraries\TomYumPpRework::modeNames($rework['modes'] ?? []) }}</strong>
                    @if (present($rework['reason'] ?? null)) <span class="tomyum-panel__muted">· {{ $rework['reason'] }}</span> @endif
                    @if (present($rework['toVersion'] ?? null))
                        <span class="tomyum-panel__muted">· {{ $rework['library'] }} {{ $rework['fromVersion'] }} → {{ $rework['toVersion'] }}</span>
                    @endif
                    <div class="tomyum-panel__progress" style="margin: 6px 0"><div style="width: {{ App\Libraries\TomYumPpRework::percent($rework) }}%"></div></div>
                    <div class="tomyum-panel__muted">
                        {{ App\Libraries\TomYumPpRework::progress($rework) }} ·
                        {{ i18n_number_format(max(0, ($rework['total'] ?? 0) - ($rework['done'] ?? 0))) }} score(s) left to recalculate ·
                        started {!! timeago(Carbon\Carbon::parse($rework['startedAt'])) !!}
                    </div>
                    <form method="POST" action="{{ route('panel.admin.pp-rework') }}" onsubmit="return confirm('Stop this rework? The pp stays as it was.')">
                        @csrf
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="id" value="{{ $rework['id'] }}">
                        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit" style="margin-top: 6px">Cancel</button>
                    </form>
                </div>
            @endforeach

            @php
                $ppLibraries = App\Libraries\TomYumPpRework::libraries();
                $ppStatus = $ppLibraries['status'] ?? null;
                $ppJob = $ppLibraries['job'] ?? null;
                $shortRef = fn (?string $ref) => $ref === null ? '' : substr($ref, 0, 7);
            @endphp
            <h3>pp libraries</h3>
            @if ($ppStatus === null)
                <p class="tomyum-panel__hint">The pp updater hasn't reported yet (it runs on the server itself: deploy/pp-updater).</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>Library</th><th>Running</th><th>Newest</th><th></th></tr>
                    @foreach (['vanilla' => 'Vanilla', 'akatsuki' => 'Relax / Autopilot'] as $key => $label)
                        @php $library = $ppStatus[$key] ?? []; @endphp
                        <tr>
                            <td>
                                <strong>{{ $label }}</strong><br>
                                <span class="tomyum-panel__muted">{{ $library['library'] ?? '' }}</span>
                            </td>
                            <td>
                                {{ $library['current'] ?? '?' }}
                                @if ($key === 'akatsuki') <span class="tomyum-panel__muted">({{ $shortRef($library['commit'] ?? null) }})</span>
                                @else <span class="tomyum-panel__muted">(processor {{ $shortRef($library['processorRef'] ?? null) }})</span> @endif
                            </td>
                            <td>
                                @if (isset($library['error']))
                                    <span class="tomyum-panel__pill tomyum-panel__pill--bad">couldn't check</span>
                                @else
                                    {{ $library['latest'] ?? '?' }}
                                    @if ($key === 'akatsuki') <span class="tomyum-panel__muted">({{ $shortRef($library['latestCommit'] ?? null) }})</span>
                                    @else <span class="tomyum-panel__muted">(processor {{ $shortRef($library['latestProcessorRef'] ?? null) }})</span> @endif
                                    @if ($key === 'vanilla' && isset($library['osuRelease']) && $library['osuRelease'] !== ($library['latest'] ?? null))
                                        <br><span class="tomyum-panel__muted">osu! released {{ $library['osuRelease'] }}; ppy's score processor isn't on it yet (vanilla pp must match it).</span>
                                    @endif
                                @endif
                            </td>
                            <td>
                                @if ($library['available'] ?? false)
                                    <form method="POST" action="{{ route('panel.admin.pp-library') }}"
                                        onsubmit="return confirm({{ json_encode('Update the '.$label.' pp library? The server rebuilds with it, then reworks '.App\Libraries\TomYumPpRework::modeNames($library['modes'] ?? []).': those rankings pause and new pp waits until it is done.') }})">
                                        @csrf
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="library" value="{{ $key }}">
                                        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-download"></i>Update</button>
                                    </form>
                                @elseif (!isset($library['error']))
                                    <span class="tomyum-panel__pill tomyum-panel__pill--good">up to date</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
                <form method="POST" action="{{ route('panel.admin.pp-library') }}" class="tomyum-panel__row" style="align-items: center; margin-top: 6px">
                    @csrf
                    <input type="hidden" name="action" value="check">
                    <span class="tomyum-panel__muted">Checked {!! isset($ppStatus['checkedAt']) ? timeago(Carbon\Carbon::parse($ppStatus['checkedAt'])) : 'never' !!} (every 6 hours).</span>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Check now</button>
                </form>
            @endif
            @if ($ppJob !== null)
                <div class="tomyum-panel__item" style="margin-top: 8px">
                    <strong>{{ $ppJob['library'] === 'vanilla' ? 'Vanilla' : 'Relax / Autopilot' }} update:</strong>
                    <span class="{{ class_with_modifiers('tomyum-panel__pill', [($ppJob['state'] ?? '') === 'failed' ? 'bad' : (($ppJob['state'] ?? '') === 'done' ? 'good' : 'info')]) }}">{{ $ppJob['state'] ?? '?' }}</span>
                    <ul class="tomyum-panel__muted" style="margin: 4px 0 0 18px">
                        @foreach (array_slice($ppJob['steps'] ?? [], -6) as $step)
                            <li>{{ $step['text'] }}</li>
                        @endforeach
                    </ul>
                    @if (present($ppJob['error'] ?? null))
                        <pre class="tomyum-panel__muted" style="white-space: pre-wrap">{{ $ppJob['error'] }}</pre>
                    @endif
                </div>
            @endif
            <h3 style="margin-top: 12px">Rework by hand</h3>
            <form method="POST" action="{{ route('panel.admin.pp-rework') }}" onsubmit="return confirm('Start a pp rework? Rankings in these modes pause until it is done.')">
                @csrf
                <input type="hidden" name="action" value="start">
                <div class="tomyum-panel__row" style="flex-wrap: wrap; gap: 4px 12px; margin-bottom: 8px">
                    @foreach (App\Libraries\TomYumPpRework::MODES as $mode => [, , $name])
                        <label class="tomyum-panel__check"><input type="checkbox" name="modes[]" value="{{ $mode }}"> {{ $name }}</label>
                    @endforeach
                </div>
                <label class="input-container">
                    <span class="input-container__label">Reason (shown on the website)</span>
                    <input class="input-text" type="text" name="reason" maxlength="200" placeholder="e.g. Relax now uses Akatsuki's formula">
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit" style="margin-top: 8px"><i class="fas fa-play"></i>Start rework</button>
            </form>

            @if (count($ppReworks['recent'] ?? []) > 0)
                <h3 style="margin-top: 12px">Recent</h3>
                <table class="tomyum-panel__table">
                    <tr><th>#</th><th>Modes</th><th>State</th><th>Scores</th><th>Finished</th></tr>
                    @foreach ($ppReworks['recent'] as $rework)
                        <tr>
                            <td>{{ $rework['id'] }}</td>
                            <td>{{ App\Libraries\TomYumPpRework::modeNames($rework['modes'] ?? []) }}</td>
                            <td>{{ $rework['state'] }}</td>
                            <td>{{ i18n_number_format($rework['done'] ?? 0) }}</td>
                            <td>{!! isset($rework['finishedAt']) ? timeago(Carbon\Carbon::parse($rework['finishedAt'])) : '' !!}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>

        <div class="tomyum-panel__card js-tomyum-live">
            <h2><i class="fas fa-signal"></i>Online now</h2>
            @if (count($online) === 0)
                <p class="tomyum-panel__empty">Nobody is online.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>Player</th><th>Mode</th><th>Doing</th><th>Last seen</th></tr>
                    @foreach ($online as $player)
                        <tr>
                            <td><a href="{{ route('panel.admin.users.show', ['user' => $player['userId']]) }}">{{ $player['username'] }}</a></td>
                            <td class="tomyum-panel__nowrap">{{ $modes[$player['mode'] % 4] ?? $player['mode'] }}</td>
                            <td>
                                {{ $actions[$player['action']] ?? 'Unknown' }}
                                @if ($player['actionInfo'] !== '')
                                    &middot;
                                    @if ($player['beatmapId'] > 0)
                                        <a href="{{ route('beatmaps.show', ['beatmap' => $player['beatmapId']]) }}">{{ $player['actionInfo'] }}</a>
                                    @else
                                        {{ $player['actionInfo'] }}
                                    @endif
                                @endif
                            </td>
                            <td class="tomyum-panel__nowrap tomyum-panel__muted">{!! timeago(Carbon\Carbon::parse($player['lastActivity'])) !!}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <div class="tomyum-panel__card-head">
                <h2><i class="fas fa-history"></i>Recent staff actions</h2>
                <a class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" href="{{ route('panel.admin.log') }}">All, with filters<i class="fas fa-angle-right"></i></a>
            </div>
            @if (count($entries) === 0)
                <p class="tomyum-panel__empty">Nothing yet.</p>
            @else
                @include('panel._staff_log')
            @endif
        </div>
    </div>
    {{-- The online count and list follow along without reloading (the account search keeps its text). --}}
    <script>
        if (window.tomyumLiveRefresh == null) {
            const refresh = async function () {
                const path = {!! json_encode(parse_url(route('panel.admin.index'), PHP_URL_PATH)) !!};
                const live = document.querySelectorAll('.js-tomyum-live');
                if (location.pathname !== path || live.length === 0 || document.hidden) {
                    return;
                }
                try {
                    const response = await fetch(location.href, { credentials: 'same-origin', cache: 'no-store' });
                    if (!response.ok) {
                        return;
                    }
                    const fresh = new DOMParser().parseFromString(await response.text(), 'text/html').querySelectorAll('.js-tomyum-live');
                    live.forEach(function (element, i) {
                        if (fresh[i] != null) {
                            element.innerHTML = fresh[i].innerHTML;
                        }
                    });
                } catch (e) {
                    // Offline for a moment: the next tick tries again.
                }
            };
            window.tomyumLiveRefresh = setInterval(refresh, 10000);
            // It pauses while the tab is in the background: catch up as soon as it's back.
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    refresh();
                }
            });
        }
    </script>
@endsection
