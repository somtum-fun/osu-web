{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: one player's pkms reports and the verdict on each (AnticheatToolsController@findings). --}}
@extends('master', ['titlePrepend' => 'pkms findings'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['url' => route('panel.admin.anticheat'), 'title' => 'Anti-cheat'],
        ['title' => 'pkms findings'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-search"></i>A player's pkms findings</h2>
            <p class="tomyum-panel__hint">
                Every pkms report on the player's scores (newest first): the pkms build, the findings it raised, what it sent with them, and what the
                server did (the verdict: restricted, refused, log-only under the policy, whitelisted, staff, already restricted, grace).
                Signature hits read as their tag. From TomYum's staff log.
            </p>
            <form method="GET" action="{{ route('panel.admin.anticheat.findings') }}" class="tomyum-panel__row" style="gap: 10px; align-items: end">
                <label style="flex: 0 1 280px">
                    player (name or id)
                    <input class="input-text" type="text" name="player" value="{{ $name }}" required>
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-search"></i>Look up</button>
                @if ($player !== null)
                    <a class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" href="{{ route('panel.admin.users.show', ['user' => $player->getKey()]) }}"><i class="fas fa-user"></i>{{ $player->username }}</a>
                    <a class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" href="{{ route('panel.admin.log', ['kind' => 'score.pkms_', 'player' => $player->getKey()]) }}"><i class="fas fa-flag"></i>Staff log</a>
                @endif
            </form>
        </div>

        @if ($problem !== null)
            <div class="tomyum-panel__card"><p class="tomyum-panel__hint">{{ $problem }}</p></div>
        @elseif ($player !== null)
            <div class="tomyum-panel__card">
                <h2>
                    {{ $player->username }}
                    <span class="tomyum-panel__badge">{{ count($reports) }} REPORTS</span>
                    @if ($player->isRestricted())
                        <span class="tomyum-panel__badge tomyum-panel__badge--error">RESTRICTED</span>
                    @endif
                </h2>
                @if (count($reports) === 0)
                    <p class="tomyum-panel__hint">No pkms reports on this player's scores.</p>
                @else
                    <table class="tomyum-panel__table">
                        <thead>
                            <tr>
                                <th style="width: 140px">when</th>
                                <th style="width: 110px">build</th>
                                <th>findings</th>
                                <th>what pkms sent</th>
                                <th style="width: 220px">verdict</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reports as $report)
                                <tr>
                                    <td>{!! timeago(parse_time_to_carbon($report['at'])) !!}</td>
                                    <td><code>{{ $report['build'] ?? '?' }}</code></td>
                                    <td>
                                        @if ($report['bits'] !== null)
                                            <code>{{ sprintf('0x%X', $report['bits']) }}</code><br>
                                        @endif
                                        {{ implode(', ', $report['findings']) }}
                                    </td>
                                    <td style="word-break: break-word">
                                        @foreach ($report['details'] as $detail)
                                            <code>{{ App\Libraries\TomYumPkmsScanSets::describe($detail) }}</code><br>
                                        @endforeach
                                    </td>
                                    <td>{{ $report['verdict'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    </div>
@endsection
