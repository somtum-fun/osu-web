{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: the anti-cheat settings (pkms and OpenTabletDriver), on their own page (AdminController@anticheat). --}}
@extends('master', ['titlePrepend' => 'Anti-cheat'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'Anti-cheat'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <p class="tomyum-panel__section-title">Kill switch</p>
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

        <p class="tomyum-panel__section-title">pkms</p>
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

        {{-- TomYum: the pkms ban wave (AdminController@pkmsWave*); TomYum queues client-only restrict findings. --}}
        <div class="tomyum-panel__card" id="pkms-wave">
            <h2>
                <i class="fas fa-water"></i>pkms ban wave
                @if (!empty($pkmsWave['state']['enabled']))
                    <span class="tomyum-panel__badge tomyum-panel__badge--warn">ON</span>
                @else
                    <span class="tomyum-panel__badge">OFF</span>
                @endif
                @if (count($pkmsWave['queue'] ?? []) > 0)
                    <span class="tomyum-panel__badge tomyum-panel__badge--success">{{ count($pkmsWave['queue']) }} QUEUED</span>
                @endif
            </h2>
            <p class="tomyum-panel__hint">
                A restriction that only the client's own findings ask for (the server detected nothing itself) can be a false positive or a client that lies.
                While the wave is <strong>on</strong>, those players go into the queue instead of being restricted: no message, no visible change, so a cheat maker can't tell what the server noticed.
                <strong>Remove</strong> drops someone staff judged fine, <strong>restrict now</strong> acts on one player, and at the wave time everyone still queued is restricted at once.
                Findings the server confirmed itself (a memory-challenge mismatch, a missing scan set) always restrict at once. Changes apply within a minute.
            </p>
            <form method="POST" action="{{ route('panel.admin.pkms-wave') }}" class="tomyum-panel__row" style="gap: 10px; align-items: end; flex-wrap: wrap">
                @csrf
                <label><input type="checkbox" name="enabled" value="1" @if (!empty($pkmsWave['state']['enabled'])) checked @endif> wave on</label>
                <label>
                    wave time (UTC)
                    <input class="input-text" type="datetime-local" name="next_at"
                           value="{{ !empty($pkmsWave['state']['nextAt']) ? \Carbon\Carbon::parse($pkmsWave['state']['nextAt'])->utc()->format('Y-m-d\TH:i') : '' }}">
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save</button>
            </form>
            <p class="tomyum-panel__hint" style="margin-top: 6px">
                @if (!empty($pkmsWave['state']['lastRunAt']))
                    Last ran {{ \Carbon\Carbon::parse($pkmsWave['state']['lastRunAt'])->utc()->toDateTimeString() }} UTC.
                @else
                    Never ran.
                @endif
            </p>
            @if (count($pkmsWave['queue'] ?? []) > 0)
                <table class="tomyum-panel__table">
                    <thead>
                        <tr>
                            <th>player</th>
                            <th>findings</th>
                            <th>build</th>
                            <th>queued</th>
                            <th style="width: 200px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pkmsWave['queue'] as $entry)
                            <tr>
                                <td>
                                    <a href="{{ route('users.show', $entry['userId']) }}">{{ $entry['username'] !== '' ? $entry['username'] : '#'.$entry['userId'] }}</a>
                                    @if ($entry['scores'] > 1)
                                        <span class="tomyum-panel__hint">({{ $entry['scores'] }} scores)</span>
                                    @endif
                                </td>
                                <td>
                                    <code>0x{{ dechex((int) $entry['findings']) }}</code>
                                    @if (!empty($entry['details']))
                                        <br><span class="tomyum-panel__hint" style="margin: 0">{{ \Illuminate\Support\Str::limit($entry['details'], 120) }}</span>
                                    @endif
                                </td>
                                <td><code>{{ $entry['buildId'] }}</code></td>
                                <td>{{ \Carbon\Carbon::parse($entry['queuedAt'])->utc()->format('Y-m-d H:i') }}</td>
                                <td>
                                    <div class="tomyum-panel__row" style="gap: 6px">
                                        <form method="POST" action="{{ route('panel.admin.pkms-wave-remove') }}">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $entry['userId'] }}">
                                            <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" type="submit"><i class="fas fa-user-check"></i>Remove</button>
                                        </form>
                                        <form method="POST" action="{{ route('panel.admin.pkms-wave-restrict') }}" onsubmit="return confirm('Restrict {{ $entry['username'] !== '' ? $entry['username'] : 'this player' }} now?')">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $entry['userId'] }}">
                                            <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary tomyum-panel__danger-text" type="submit"><i class="fas fa-gavel"></i>Restrict now</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <form method="POST" action="{{ route('panel.admin.pkms-wave-run') }}" onsubmit="return confirm('Restrict everyone still queued now ({{ count($pkmsWave['queue']) }} player(s))?')">
                    @csrf
                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary tomyum-panel__danger-text" type="submit"><i class="fas fa-water"></i>Restrict the queue now</button>
                </form>
            @else
                <p class="tomyum-panel__hint">Nobody is queued.</p>
            @endif
        </div>

        {{-- TomYum: pkms trusted-signer allowlist (AdminController@pkmsTrustedSigners). --}}
        <div class="tomyum-panel__card" id="pkms-trusted-signers">
            <h2>
                <i class="fas fa-certificate"></i>pkms trusted signers
                @if (count($pkmsTrustedSigners) === 0)
                    <span class="tomyum-panel__badge">EMPTY</span>
                @else
                    <span class="tomyum-panel__badge tomyum-panel__badge--success">{{ count($pkmsTrustedSigners) }}</span>
                @endif
            </h2>
            <p class="tomyum-panel__hint">
                Authenticode signer CNs the server trusts <strong>on top of</strong> the list baked into pkms. A finding whose <code>ml:</code> entries all name signers listed here has its module_list bit dropped — no pkms rebuild, no ship flow. TomYum picks up a change within a minute.<br>
                Signer names come from the finding reason, e.g. <code>[not in allowlist: OORT Inc.]</code> → enter <code>OORT Inc.</code> here. Match is case-insensitive.
            </p>
            <form method="POST" action="{{ route('panel.admin.pkms-trusted-signers') }}">
                @csrf
                <table class="tomyum-panel__table">
                    <thead>
                        <tr>
                            <th>signer CN</th>
                            <th style="width: 50px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pkmsTrustedSigners as $i => $signer)
                            <tr>
                                <td><input class="input-text" type="text" name="signer[{{ $i }}]" value="{{ $signer }}" maxlength="{{ App\Libraries\TomYumPkmsTrustedSigners::MAX_LENGTH }}" required></td>
                                <td style="text-align: center"><label><input type="checkbox" name="delete[{{ $i }}]" value="1"> delete</label></td>
                            </tr>
                        @endforeach
                        <tr>
                            <td><input class="input-text" type="text" name="new_signer" placeholder="OORT Inc." maxlength="{{ App\Libraries\TomYumPkmsTrustedSigners::MAX_LENGTH }}"></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
                <div class="tomyum-panel__row" style="margin-top: 10px">
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save</button>
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

        <p class="tomyum-panel__section-title">OpenTabletDriver</p>
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
    </div>
@endsection
