{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: the game server's nodes (TomYum /_admin/cluster). --}}
@php
    $bytes = function (?int $value): string {
        if ($value === null) {
            return '–';
        }
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($value < 1024 || $unit === 'TB') {
                return ($unit === 'B' ? $value : number_format($value, 1)).' '.$unit;
            }
            $value /= 1024;
        }
    };
    $duration = function (int $seconds): string {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $days > 0 ? "{$days}d {$hours}h" : ($hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m");
    };
    $ms = fn ($value) => $value === null ? '–' : number_format((float) $value, $value < 10 ? 1 : 0).' ms';
@endphp
@extends('master', ['titlePrepend' => 'Servers'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'Servers'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        @if ($cluster === null)
            <div class="tomyum-panel__card">
                <p class="tomyum-panel__empty">The game server didn't answer.</p>
            </div>
        @else
            <div class="tomyum-panel__card">
                <h2><i class="fas fa-server"></i>{{ $cluster['serverName'] }}</h2>
                <div class="tomyum-panel__facts">
                    <div class="tomyum-panel__fact">
                        <span class="tomyum-panel__kicker">{{ $cluster['product'] }}</span>
                        <span class="tomyum-panel__fact-value">{{ $cluster['version'] }}</span>
                    </div>
                    <div class="tomyum-panel__fact">
                        <span class="tomyum-panel__kicker">Commit</span>
                        <span class="tomyum-panel__fact-value">{{ $cluster['commit'] }}</span>
                    </div>
                    <div class="tomyum-panel__fact">
                        <span class="tomyum-panel__kicker">Nodes</span>
                        <span class="tomyum-panel__fact-value">{{ count($cluster['nodes']) }}{{ $cluster['clustered'] ? '' : ' (no cluster)' }}</span>
                    </div>
                    <div class="tomyum-panel__fact">
                        <span class="tomyum-panel__kicker">Players online</span>
                        <span class="tomyum-panel__fact-value">{{ array_sum(array_map(fn ($n) => $n['details']['players'] ?? 0, $cluster['nodes'])) }}</span>
                    </div>
                    <div class="tomyum-panel__fact">
                        <span class="tomyum-panel__kicker">Redis from {{ $cluster['answeredBy'] }}</span>
                        <span class="tomyum-panel__fact-value">{{ $ms($cluster['redisMs']) }}</span>
                    </div>
                </div>
                <p class="tomyum-panel__hint">Latency is measured now, from {{ $cluster['answeredBy'] }}, to each node's health check. The rest is each node's last report (every 10 seconds).</p>
            </div>

            @foreach ($cluster['nodes'] as $node)
                @php($d = $node['details'] ?? null)
                <div class="tomyum-panel__card">
                    <h2>
                        <span class="tomyum-panel__live-dot {{ ($node['reachable'] ?? true) !== false && $node['reportAgeSeconds'] < 30 ? '' : 'tomyum-panel__live-dot--off' }}"></span>{{ $node['id'] }}
                        @if ($node['leader'])
                            <span class="tomyum-panel__badge">leader</span>
                        @endif
                    </h2>
                    <div class="tomyum-panel__facts">
                        <div class="tomyum-panel__fact">
                            <span class="tomyum-panel__kicker">Latency</span>
                            <span class="tomyum-panel__fact-value">{{ $node['reachable'] === false ? 'unreachable' : $ms($node['httpMs']) }}</span>
                        </div>
                        <div class="tomyum-panel__fact">
                            <span class="tomyum-panel__kicker">Redis</span>
                            <span class="tomyum-panel__fact-value">{{ $ms($d['redisMs'] ?? null) }}</span>
                        </div>
                        <div class="tomyum-panel__fact">
                            <span class="tomyum-panel__kicker">Up</span>
                            <span class="tomyum-panel__fact-value">{{ $duration($node['uptimeSeconds']) }}</span>
                        </div>
                        <div class="tomyum-panel__fact">
                            <span class="tomyum-panel__kicker">Last report</span>
                            <span class="tomyum-panel__fact-value">{{ number_format($node['reportAgeSeconds'], 0) }}s ago</span>
                        </div>
                        <div class="tomyum-panel__fact">
                            <span class="tomyum-panel__kicker">Players / IRC / rooms</span>
                            <span class="tomyum-panel__fact-value">{{ $d['players'] ?? '–' }} / {{ $d['irc'] ?? '–' }} / {{ $d['rooms'] ?? '–' }}</span>
                        </div>
                    </div>
                    @if ($d !== null)
                        <table class="tomyum-panel__table" style="margin-top: 12px">
                            <tr><th>Machine</th><td>{{ $d['machine'] }} · {{ $d['os'] }} · {{ $d['architecture'] }}</td></tr>
                            <tr><th>CPU</th><td>{{ $d['cores'] }} cores · TomYum uses {{ number_format($d['cpuPercent'], 1) }}% · {{ $d['threads'] }} threads</td></tr>
                            <tr><th>Memory</th><td>TomYum {{ $bytes($d['processMemoryBytes']) }} (heap {{ $bytes($d['gcHeapBytes']) }}) of {{ $bytes($d['memoryBytes']) }}</td></tr>
                            <tr><th>Version</th><td>{{ $cluster['product'] }} {{ $node['version'] }} · commit {{ $d['commit'] }} · {{ $d['runtime'] }}</td></tr>
                            <tr><th>Started</th><td>{{ \Carbon\Carbon::parse($node['startedAt'])->toDayDateTimeString() }} UTC</td></tr>
                            @if ($node['internalUrl'] !== '')
                                <tr><th>Address</th><td>{{ $node['internalUrl'] }}</td></tr>
                            @endif
                            <tr>
                                <th>Plugins ({{ count($d['plugins']) }})</th>
                                <td>
                                    @forelse ($d['plugins'] as $plugin)
                                        <span class="tomyum-panel__pill">{{ $plugin['key'] }} {{ $plugin['value'] }}</span>
                                    @empty
                                        <span class="tomyum-panel__muted">none</span>
                                    @endforelse
                                </td>
                            </tr>
                        </table>
                    @else
                        <p class="tomyum-panel__hint">This node runs an older TomYum that doesn't report its details.</p>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
@endsection
