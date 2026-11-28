{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: TumThai's releases as client builds (TomYumLazerBuilds), the plays each started. Cards, not tables: readable on a phone. --}}
@php
    $names = ['lazer' => 'TumThai', 'lazer_next' => 'TumThai!Next'];
    $total = $overview['matched_week'] + $overview['unmatched_week'];
    $short = fn (?string $hash) => $hash === null ? '-' : substr($hash, 0, 8).'…';
@endphp
@extends('master', ['titlePrepend' => 'TumThai builds'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'TumThai builds'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-code-branch"></i>TumThai builds</h2>
            <p class="tomyum-panel__hint">
                Every TumThai and TumThai!Next release is registered here when it's published (with the fingerprint of its
                game file). Each play says which file it came from, so a play is matched to its release. The newest
                {{ $overview['current'] }} releases of each are <b>current</b>; older ones are <b>outdated</b> (players update
                a little after a release).
            </p>
            <p class="tomyum-panel__hint">
                @if ($enforced)
                    <b>Checked:</b> plays from an unknown or outdated build are refused.
                @else
                    <b>Watching only:</b> nothing is refused yet. Plays from an unknown build (a self-built or another lazer
                    client) are still allowed and counted below.
                @endif
                The fingerprint is sent by the game itself, so it tells builds apart but isn't proof.
            </p>
            <p>
                <span class="tomyum-panel__badge">LAST 7 DAYS</span>
                <span class="tomyum-panel__badge">{{ number_format($overview['matched_week']) }} PLAYS FROM A RELEASE</span>
                <span class="tomyum-panel__badge">{{ number_format($overview['unmatched_week']) }} FROM AN UNKNOWN BUILD</span>
                @if ($total > 0)
                    <span class="tomyum-panel__badge">{{ round(100 * $overview['matched_week'] / $total) }}% MATCHED</span>
                @endif
            </p>
        </div>

        @foreach ($overview['streams'] as $key => $builds)
            <div class="tomyum-panel__card">
                <h2>{{ $names[$key] ?? $key }}</h2>
                @if (count($builds) === 0)
                    <p class="tomyum-panel__empty">No releases registered yet.</p>
                @endif
            </div>

            @foreach ($builds as $build)
                <div class="tomyum-panel__card">
                    <h2 style="word-break: break-word">
                        {{ $build['version'] }}
                        @if ($build['status'] === 'current')
                            <span class="tomyum-panel__pill">current</span>
                        @else
                            <span class="tomyum-panel__pill tomyum-panel__pill--bad">outdated</span>
                        @endif
                        @if ($build['private'])
                            <span class="tomyum-panel__badge" title="Not on the changelog page yet">NOT ON CHANGELOG</span>
                        @endif
                        @if (!$build['counts'])
                            <span class="tomyum-panel__badge" title="Plays on this build give no pp or ranking">NO PP</span>
                        @endif
                    </h2>
                    <p>
                        <span class="tomyum-panel__badge">{{ number_format($build['plays_week']) }} PLAYS THIS WEEK</span>
                        <span class="tomyum-panel__badge">{{ number_format($build['plays']) }} IN ALL</span>
                    </p>
                    <p class="tomyum-panel__hint" style="word-break: break-all">
                        Released {{ $build['date']?->format('Y-m-d H:i') ?? '-' }} UTC ·
                        last play {{ $build['last_play'] ?? 'never' }} ·
                        build #{{ $build['build_id'] }}<br>
                        Windows/Linux: <code title="{{ $build['hash'] }}">{{ $short($build['hash']) }}</code> ·
                        Android: <code title="{{ $build['android_hash'] }}">{{ $short($build['android_hash']) }}</code>
                        @if ($build['macos_hash'] !== null)
                            · macOS: <code title="{{ $build['macos_hash'] }}">{{ $short($build['macos_hash']) }}</code>
                        @endif
                    </p>
                </div>
            @endforeach
        @endforeach
    </div>
@endsection
