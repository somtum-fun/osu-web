{{-- TomYum: the player's latest pkms session and OpenTabletDriver filters/plugins (AdminAccountEndpoints@ClientSession). --}}
@php
    $friendlyFilterName = function (string $name): string {
        $map = [
            'Antichatter' => 'Devocub Antichatter',
            'DevocubAntichatter' => 'Devocub Antichatter',
            'DevochubAntichatter' => 'Devocub Antichatter',
            'DevocubSmoothing' => 'Devocub Smoothing',
            'HawkuSmoothing' => 'Hawku Smoothing',
            'HawkuNoiseReduction' => 'Hawku Noise Reduction',
            'ChatterGenerator' => 'Chatter Generator',
            'TemporalResampler' => 'Temporal Resampler',
            'StrokeSnapping' => 'Stroke Snapping',
            'WindowsInk' => 'Windows Ink',
            'ChatterExterminator' => 'Chatter Exterminator',
            'RadialFollowSmoothing' => 'Radial Follow Smoothing',
            'PositionInterpolation' => 'Saturn (Position Interpolation)',
            'VelocityInterpolation' => 'Saturn (Velocity Interpolation)',
        ];
        if (isset($map[$name])) {
            return $map[$name];
        }
        return trim(preg_replace('/(?<!^)(?=[A-Z])/', ' ', $name));
    };

    $friendlyPropLabel = function (string $prop): string {
        $map = [
            'Latency' => 'Latency',
            'AntichatterStrength' => 'Antichatter Strength',
            'AntichatterMultiplier' => 'Antichatter Multiplier',
            'AntichatterOffsetX' => 'Antichatter Offset X',
            'AntichatterOffsetY' => 'Antichatter Offset Y',
            'PredictionEnabled' => 'Prediction',
            'PredictionStrength' => 'Prediction Strength',
            'PredictionSharpness' => 'Prediction Sharpness',
            'PredictionOffsetX' => 'Prediction Offset X',
            'PredictionOffsetY' => 'Prediction Offset Y',
            'Frequency' => 'Frequency',
            'PredictionRatio' => 'Prediction Ratio',
            'FrameTimeShift' => 'Frame Time Shift',
            'ReverseEmaWeight' => 'Reverse EMA',
            'ReverseEma' => 'Reverse EMA',
            'EmaWeight' => 'EMA Weight',
            'StockEmaWeight' => 'Stock EMA Weight',
        ];
        if (isset($map[$prop])) {
            return $map[$prop];
        }
        return trim(preg_replace('/(?<!^)(?=[A-Z])/', ' ', $prop));
    };

    $propOrder = [
        'Latency' => 1,
        'AntichatterStrength' => 2,
        'AntichatterMultiplier' => 3,
        'AntichatterOffsetX' => 4,
        'AntichatterOffsetY' => 5,
        'PredictionEnabled' => 6,
        'PredictionStrength' => 7,
        'PredictionSharpness' => 8,
        'PredictionOffsetX' => 9,
        'PredictionOffsetY' => 10,
        'Frequency' => 11,
    ];

@endphp
<div class="tomyum-panel__card" id="client">
    <h2>
        <i class="fas fa-pen-nib"></i>Client &amp; OpenTabletDriver (pkms)
        @if ($clientSession !== null)
            @if ($clientSession['live'] ?? false)
                <span class="tomyum-panel__badge tomyum-panel__badge--success">LIVE</span>
            @else
                <span class="tomyum-panel__badge">OFFLINE</span>
            @endif
        @endif
    </h2>
    <p class="tomyum-panel__hint">
        pkms session and the player's OpenTabletDriver setup (output mode, filters, tools). Disabled entries are logged, never treated as banned.
    </p>
    @if ($clientSession === null)
        <p class="tomyum-panel__empty">No pkms session recorded since the server started (lazer, legacy, or hasn't logged in on stable yet).</p>
    @else
        <div class="tomyum-panel__facts" style="margin-bottom: 14px">
            <div class="tomyum-panel__fact">
                <span class="tomyum-panel__kicker">Build</span>
                <span class="tomyum-panel__fact-value"><code>{{ $clientSession['buildId'] }}</code></span>
            </div>
            <div class="tomyum-panel__fact">
                <span class="tomyum-panel__kicker">Findings</span>
                <span class="tomyum-panel__fact-value">
                    @if (($clientSession['findings'] ?? 0) === 0)
                        <span class="tomyum-panel__badge tomyum-panel__badge--success">clean</span>
                    @else
                        <span class="tomyum-panel__badge tomyum-panel__badge--error">{{ $clientSession['findingsLabel'] }}</span>
                    @endif
                </span>
            </div>
            <div class="tomyum-panel__fact">
                <span class="tomyum-panel__kicker">Last heartbeat</span>
                <span class="tomyum-panel__fact-value">{!! isset($clientSession['seenAt']) ? timeago(Carbon\Carbon::parse($clientSession['seenAt'])) : '—' !!}</span>
            </div>
            <div class="tomyum-panel__fact">
                <span class="tomyum-panel__kicker">Other details</span>
                <span class="tomyum-panel__fact-value">
                    @if (present($clientSession['details'] ?? null))
                        <code>{{ $clientSession['details'] }}</code>
                    @else
                        <span class="tomyum-panel__muted">—</span>
                    @endif
                </span>
            </div>
        </div>

        @php
            $otdPlugins = $clientSession['tabletPlugins'] ?? [];
            $rawFilters = $clientSession['tabletFilters'] ?? [];
            // 'file': pkms couldn't reach the daemon and read settings.json, which can't say which tablet is
            // plugged in, so every profile is listed (one entry per filter per profile).
            $otdFromFile = ($clientSession['tabletSource'] ?? null) === 'file';
            $otdIpcError = $clientSession['tabletIpcError'] ?? null;
            $otdIpcErrorText = null;
            if ($otdIpcError !== null && preg_match('/^([a-z]+)(-?\d+)$/', $otdIpcError, $m)) {
                $otdIpcErrorText = match ($m[1]) {
                    'af' => $m[2] === '10047' ? 'this Wine build has no AF_UNIX socket support' : "socket() failed (WSA {$m[2]})",
                    'cn' => match ($m[2]) {
                        '10061' => 'connection refused (stale socket, or the daemon isn\'t listening)',
                        '10013' => 'permission denied (different user or sandbox)',
                        '10049', '10022' => 'the socket path isn\'t reachable from this Wine/sandbox',
                        default => "connect failed (WSA {$m[2]})",
                    },
                    'tx' => "sending the request failed (WSA {$m[2]})",
                    'to' => "the daemon didn't answer in time ({$m[2]} bytes received)",
                    'eof' => "the daemon closed the connection ({$m[2]} bytes received)",
                    'nosock' => 'no OTD socket found in /tmp or the daemon\'s TMPDIR',
                    'pipe', 'pipeto' => "Windows named pipe failed ({$m[2]})",
                    'nows2' => 'Winsock unavailable',
                    default => null,
                };
            }
            $otdProfileTablets = [];

            // Group entries that have the exact same filter name, enabled state, kind, and settings
            // (e.g. when settings.json has multiple tablet profiles configured identically).
            $grouped = [];
            foreach ($rawFilters as $f) {
                $fname = $f['name'] ?? 'Filter';
                $tablet = !empty($f['tablet']) ? $f['tablet'] : null;
                if ($tablet !== null && !in_array($tablet, $otdProfileTablets, true)) {
                    $otdProfileTablets[] = $tablet;
                }
                $cleanSettings = array_filter($f['settings'] ?? [], fn ($k) => !str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_KEY);
                ksort($cleanSettings);
                $sig = strtolower($fname)
                    . '|' . (($f['enabled'] ?? false) ? '1' : '0')
                    . '|' . ($f['kind'] ?? 'filter')
                    . '|' . json_encode($cleanSettings);
                if (!isset($grouped[$sig])) {
                    $f['tablets'] = $tablet !== null ? [$tablet] : [];
                    $grouped[$sig] = $f;
                } else {
                    if ($tablet !== null && !in_array($tablet, $grouped[$sig]['tablets'], true)) {
                        $grouped[$sig]['tablets'][] = $tablet;
                    }
                    if (empty($grouped[$sig]['tablet']) && $tablet !== null) {
                        $grouped[$sig]['tablet'] = $tablet;
                    }
                }
            }
            $otdFilters = array_values($grouped);

            // If only bare plugin names were reported without filter objects, synthesize filter entries so they render in the OTD UI.
            if (count($otdFilters) === 0 && count($otdPlugins) > 0) {
                foreach ($otdPlugins as $p) {
                    $otdFilters[] = [
                        'name' => $p['name'],
                        'tier' => $p['tier'] ?? 'unknown',
                        'enabled' => $p['enabled'] ?? true,
                        'settings' => [],
                        'problem' => null,
                        'tablet' => null,
                        'tablets' => [],
                    ];
                }
            }

            $detectedTablet = null;
            foreach ($otdFilters as $f) {
                if (!empty($f['tablet'])) {
                    $detectedTablet = $f['tablet'];
                    break;
                }
            }

            $activeProblems = [];
            foreach ($otdFilters as $f) {
                if (($f['enabled'] ?? false) && !empty($f['problem'])) {
                    $activeProblems[] = ($f['name'] ?? 'Filter') . ': ' . $f['problem'];
                }
            }
            $hasActiveBan = false;
            foreach ($otdFilters as $f) {
                if (($f['enabled'] ?? false) && ($f['tier'] ?? '') === 'banned') {
                    $hasActiveBan = true;
                }
            }
        @endphp

        @if (count($otdFilters) === 0)
            <p class="tomyum-panel__empty">No OpenTabletDriver filters reported (daemon not running or no filters configured).</p>
        @else
            @if ($hasActiveBan || count($activeProblems) > 0)
                <div class="tomyum-panel__status tomyum-panel__status--error" style="margin-bottom: 12px">
                    <span><strong>Policy attention needed:</strong> {{ count($activeProblems) > 0 ? implode('; ', $activeProblems) : ($clientSession['tabletSummary'] ?? 'Banned filter enabled') }}</span>
                </div>
            @endif

            @php
                $installedOnly = [];
                $filterNames = array_map(fn ($f) => strtolower($f['name'] ?? ''), $otdFilters);
                foreach ($otdPlugins as $p) {
                    if (!in_array(strtolower($p['name'] ?? ''), $filterNames, true)) {
                        $installedOnly[] = $p['name'];
                    }
                }
                $showTabletColumn = $otdFromFile && count($otdProfileTablets) > 1;
                $formatSetting = function (string $prop, string $val): string {
                    if ($val === '?') {
                        return 'default';
                    }
                    if (in_array(strtolower($val), ['true', 'false'], true)) {
                        return strtolower($val) === 'true' ? 'on' : 'off';
                    }
                    if (preg_match('/^-?\d+\.0+$/', $val)) {
                        $val = preg_replace('/\.0+$/', '', $val);
                    }
                    return strtolower($prop) === 'frequency' ? $val . ' Hz' : $val;
                };
            @endphp

            <div class="tomyum-panel__otd-head">
                <span class="tomyum-panel__section-title" style="margin: 0">OpenTabletDriver</span>
                @if ($otdFromFile)
                    <span class="tomyum-panel__pill tomyum-panel__pill--warn" title="Read from settings.json because IPC was unreachable">from settings.json</span>
                @else
                    <span class="tomyum-panel__pill tomyum-panel__pill--info" title="Live from the OpenTabletDriver daemon">live</span>
                    @if ($detectedTablet)
                        <span class="tomyum-panel__otd-tablet-pill">{{ $detectedTablet }}</span>
                    @endif
                @endif
            </div>

            @if ($otdFromFile)
                <div class="tomyum-panel__otd-subnote">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>
                        IPC unreachable{{ $otdIpcErrorText ? ' (' . $otdIpcErrorText . ')' : '' }}@if ($otdIpcError) · <code>{{ $otdIpcError }}</code>@endif — showing all profiles from <code>settings.json</code>.
                    </span>
                </div>
            @endif

            <div class="tomyum-panel__otd-list">
                @foreach ($otdFilters as $filter)
                    @php
                        $fname = $filter['name'] ?? 'Filter';
                        $displayName = $friendlyFilterName($fname);
                        $isEnabled = (bool) ($filter['enabled'] ?? false);
                        $fTier = $isEnabled ? ($filter['tier'] ?? 'unknown') : 'disabled';
                        $problem = $isEnabled ? ($filter['problem'] ?? null) : null;
                        if ($problem !== null && str_contains($problem, 'default')) {
                            $problem = null;
                        }
                        $tierPill = match ($fTier) {
                            'banned' => ['banned', 'tomyum-panel__pill--bad'],
                            'limited' => ['allowed with exceptions', 'tomyum-panel__pill--warn'],
                            'own_risk' => ['own risk', 'tomyum-panel__pill--warn'],
                            'legal' => ['legal', 'tomyum-panel__pill--good'],
                            'disabled' => ['off · logged only', ''],
                            default => ['unlisted', ''],
                        };
                        $typeLabel = match ($filter['kind'] ?? null) { 'om' => 'Output mode', 'tool' => 'Tool', default => 'Filter' };
                        $tablets = $filter['tablets'] ?? (!empty($filter['tablet']) ? [$filter['tablet']] : []);
                        $showCardTablets = $otdFromFile || count($otdProfileTablets) > 1;
                        $settings = array_filter($filter['settings'] ?? [], fn ($k) => !str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_KEY);
                        uksort($settings, function ($a, $b) use ($propOrder) {
                            $oa = $propOrder[$a] ?? 999;
                            $ob = $propOrder[$b] ?? 999;
                            return $oa === $ob ? strcmp($a, $b) : ($oa <=> $ob);
                        });
                    @endphp
                    <div class="tomyum-panel__otd-card @if (!$isEnabled) tomyum-panel__otd-card--off @endif">
                        <div class="tomyum-panel__otd-card-top">
                            <div class="tomyum-panel__otd-title-group">
                                <strong class="tomyum-panel__otd-name">{{ $displayName }}</strong>
                                @if ($displayName !== $fname)
                                    <code class="tomyum-panel__otd-raw">{{ $fname }}</code>
                                @endif
                                <span class="tomyum-panel__otd-kind">{{ $typeLabel }}</span>
                                @if ($showCardTablets && count($tablets) > 0)
                                    @foreach ($tablets as $tab)
                                        <span class="tomyum-panel__otd-tablet-pill">{{ $tab }}</span>
                                    @endforeach
                                @endif
                            </div>
                            <div class="tomyum-panel__otd-policy">
                                @if ($problem !== null)
                                    <span class="tomyum-panel__pill tomyum-panel__pill--bad" title="{{ $problem }}">out of policy</span>
                                    <span class="tomyum-panel__hint" style="margin: 0">{{ $problem }}</span>
                                @else
                                    <span class="tomyum-panel__pill {{ $tierPill[1] }}">{{ $tierPill[0] }}</span>
                                @endif
                            </div>
                        </div>
                        @if (count($settings) > 0)
                            <div class="tomyum-panel__otd-grid">
                                @foreach ($settings as $prop => $val)
                                    <div class="tomyum-panel__otd-kv">
                                        <span class="tomyum-panel__otd-k">{{ $friendlyPropLabel((string) $prop) }}</span>
                                        <span class="tomyum-panel__otd-v">{{ $formatSetting((string) $prop, (string) $val) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if (count($installedOnly) > 0)
                <div class="tomyum-panel__otd-installed">
                    <span class="tomyum-panel__muted">Installed plugin folders:</span>
                    @foreach ($installedOnly as $folder)
                        <code>{{ $folder }}</code>
                    @endforeach
                </div>
            @endif
        @endif
    @endif
</div>

<style>
    .tomyum-panel__otd-head {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 4px 0 10px;
    }
    .tomyum-panel__otd-subnote {
        align-items: center;
        background: hsla(var(--hsl-orange-1), 0.08);
        border: 1px solid hsla(var(--hsl-orange-1), 0.35);
        border-radius: 6px;
        color: hsl(var(--hsl-c1));
        display: flex;
        font-size: 12px;
        gap: 8px;
        margin-bottom: 10px;
        padding: 7px 11px;
    }
    .tomyum-panel__otd-subnote > i {
        color: hsl(var(--hsl-orange-1));
    }
    .tomyum-panel__otd-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .tomyum-panel__otd-card {
        background: hsl(var(--hsl-b5));
        border: 1px solid hsl(var(--hsl-b4));
        border-radius: 6px;
        padding: 10px 12px;
    }
    .tomyum-panel__otd-card--off {
        opacity: 0.55;
    }
    .tomyum-panel__otd-card-top {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 8px 12px;
        justify-content: space-between;
    }
    .tomyum-panel__otd-title-group {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 6px 8px;
    }
    .tomyum-panel__otd-name {
        color: #fff;
        font-size: 13px;
    }
    .tomyum-panel__otd-raw {
        color: hsl(var(--hsl-c2));
        font-size: 11px;
    }
    .tomyum-panel__otd-kind {
        background: hsl(var(--hsl-b4));
        border-radius: 4px;
        color: hsl(var(--hsl-c2));
        font-size: 11px;
        padding: 1px 6px;
    }
    .tomyum-panel__otd-tablet-pill {
        background: hsla(var(--hsl-b3), 0.7);
        border: 1px solid hsl(var(--hsl-b4));
        border-radius: 4px;
        color: hsl(var(--hsl-c1));
        font-size: 11px;
        padding: 1px 7px;
    }
    .tomyum-panel__otd-policy {
        align-items: center;
        display: flex;
        gap: 8px;
    }
    .tomyum-panel__otd-grid {
        border-top: 1px solid hsl(var(--hsl-b4));
        display: grid;
        gap: 5px 8px;
        grid-template-columns: repeat(auto-fill, minmax(175px, 1fr));
        margin-top: 8px;
        padding-top: 8px;
    }
    .tomyum-panel__otd-kv {
        align-items: baseline;
        background: hsla(var(--hsl-b3), 0.45);
        border-radius: 4px;
        display: flex;
        font-size: 12px;
        gap: 8px;
        justify-content: space-between;
        padding: 4px 9px;
    }
    .tomyum-panel__otd-k {
        color: hsl(var(--hsl-c2));
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .tomyum-panel__otd-v {
        color: #fff;
        font-family: monospace;
        font-weight: 600;
        white-space: nowrap;
    }
    .tomyum-panel__otd-installed {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        font-size: 12px;
        gap: 6px;
        margin-top: 8px;
    }
</style>
