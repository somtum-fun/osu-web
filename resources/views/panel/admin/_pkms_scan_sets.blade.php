{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: the cheat-signature sets pkms scans for (AnticheatToolsController@scanSet*, TomYumPkmsScanSets). --}}
@php
    $scanSets = App\Libraries\TomYumPkmsScanSets::all();
    $servedSet = App\Libraries\TomYumPkmsScanSets::served();
@endphp
<div class="tomyum-panel__card" id="pkms-scan-sets">
    <h2>
        <i class="fas fa-fingerprint"></i>pkms scan sets
        @if ($servedSet === null)
            <span class="tomyum-panel__badge tomyum-panel__badge--warn">NONE SERVED</span>
        @else
            <span class="tomyum-panel__badge tomyum-panel__badge--success">{{ $servedSet['id'] }}</span>
        @endif
    </h2>
    <p class="tomyum-panel__hint">
        The cheat signatures pkms scans memory for. They aren't in pkms.dll: TomYum sends the <strong>enabled</strong> set's enabled needles to each session, masked,
        so a new cheat string is an edit here, not a release. Only one set is served at a time. Every save gets a new id (<code>name-rN</code>), so a hit
        (<code>cs:&lt;id&gt;#&lt;n&gt;</code>) always reads as the tag it was. TomYum picks a change up within a minute
        (when it reads sets from the database: <code>TOMYUM_PKMS_SCAN_SET_SOURCE</code> <code>db</code> or <code>auto</code>).
        Every change is backed up first. Needles never go to the staff log or Discord.
    </p>
    @if (count($scanSets) === 0)
        <p class="tomyum-panel__hint">No sets yet. TomYum falls back to its scan-set file, if it has one.</p>
    @else
        <table class="tomyum-panel__table">
            <thead>
                <tr>
                    <th>set</th>
                    <th style="width: 110px">id</th>
                    <th style="width: 110px">needles</th>
                    <th>note</th>
                    <th style="width: 150px">updated</th>
                    <th style="width: 260px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($scanSets as $set)
                    @php
                        $enabledNeedles = count(array_filter($set['needles'] ?? [], fn ($n) => $n['enabled'] ?? true));
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $set['name'] }}</strong>
                            @if ($set['enabled'] ?? false)
                                <span class="tomyum-panel__badge tomyum-panel__badge--success">SERVED</span>
                            @endif
                        </td>
                        <td><code>{{ $set['id'] }}</code></td>
                        <td>{{ $enabledNeedles }} of {{ count($set['needles'] ?? []) }} on</td>
                        <td>{{ $set['note'] ?? '' }}</td>
                        <td>{!! isset($set['updated_at']) ? timeago(parse_time_to_carbon($set['updated_at'])) : '' !!}</td>
                        <td class="tomyum-panel__row" style="gap: 6px">
                            <a class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" href="{{ route('panel.admin.anticheat.scan-set', ['name' => $set['name']]) }}"><i class="fas fa-edit"></i>Edit</a>
                            <form method="POST" action="{{ route('panel.admin.anticheat.scan-set-enabled', ['name' => $set['name']]) }}"
                                  onsubmit="return confirm('{{ ($set['enabled'] ?? false) ? 'Stop serving '.$set['name'].'? pkms then gets no set.' : 'Serve '.$set['name'].' instead of the current set?' }}')">
                                @csrf
                                <input type="hidden" name="enabled" value="{{ ($set['enabled'] ?? false) ? 0 : 1 }}">
                                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">
                                    <i class="fas fa-{{ ($set['enabled'] ?? false) ? 'pause' : 'play' }}"></i>{{ ($set['enabled'] ?? false) ? 'Stop' : 'Serve' }}
                                </button>
                            </form>
                            @if (!($set['enabled'] ?? false))
                                <form method="POST" action="{{ route('panel.admin.anticheat.scan-set-delete', ['name' => $set['name']]) }}" onsubmit="return confirm('Delete {{ $set['name'] }}? (A backup is kept.)')">
                                    @csrf
                                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" type="submit"><i class="fas fa-trash"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <div class="tomyum-panel__row" style="margin-top: 10px">
        <a class="btn-osu-big btn-osu-big--rounded-thin" href="{{ route('panel.admin.anticheat.scan-set', ['name' => 'new']) }}"><i class="fas fa-plus"></i>New set</a>
    </div>
</div>
