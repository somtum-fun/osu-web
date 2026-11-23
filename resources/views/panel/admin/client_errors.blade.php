{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: errors TumThai reported (TomYum /_admin/client-errors), one row per kind of error. --}}
@php
    $pages = $errors === null ? 1 : max(1, (int) ceil($errors['total'] / max(1, $errors['perPage'])));
    $query = fn (array $extra) => route('panel.admin.client-errors', array_filter(array_merge(['status' => $status === 'all' ? 'all' : null, 'page' => $page > 1 ? $page : null], $extra)));
@endphp
@extends('master', ['titlePrepend' => 'TumThai errors'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'TumThai errors'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-bug"></i>TumThai errors</h2>
            <p class="tomyum-panel__hint">
                Crashes and errors the game sent in (TumThai and TumThai!Next, from their error notices). The same error
                counts up instead of adding a row. Marking one fixed hides it until someone hits it again.
            </p>
            <p>
                <a class="btn-osu-big btn-osu-big--rounded-thin {{ $status === 'open' ? '' : 'btn-osu-big--panel-secondary' }}" href="{{ route('panel.admin.client-errors') }}">Open</a>
                <a class="btn-osu-big btn-osu-big--rounded-thin {{ $status === 'all' ? '' : 'btn-osu-big--panel-secondary' }}" href="{{ route('panel.admin.client-errors', ['status' => 'all']) }}">All</a>
                @if ($errors !== null)
                    <span class="tomyum-panel__badge">{{ $errors['total'] }} {{ $status === 'all' ? 'IN ALL' : 'OPEN' }}</span>
                @endif
            </p>
        </div>

        @if ($errors === null)
            <div class="tomyum-panel__card">
                <p class="tomyum-panel__empty">The game server didn't answer.</p>
            </div>
        @elseif (count($errors['reports']) === 0)
            <div class="tomyum-panel__card">
                <p class="tomyum-panel__empty">{{ $status === 'all' ? 'No errors reported yet.' : 'No open errors.' }}</p>
            </div>
        @else
            @foreach ($errors['reports'] as $report)
                <div class="tomyum-panel__card">
                    <h2 style="word-break: break-word">
                        <code>{{ $report['exceptionType'] }}</code>
                        <span class="tomyum-panel__badge">{{ number_format($report['count']) }}×</span>
                        @if ($report['resolvedAt'] !== null)
                            <span class="tomyum-panel__badge">FIXED</span>
                        @endif
                    </h2>
                    <p style="word-break: break-word">{{ $report['message'] }}</p>
                    @if ($report['context'] !== null && $report['context'] !== $report['message'])
                        <p class="tomyum-panel__hint" style="word-break: break-word">{{ $report['context'] }}</p>
                    @endif
                    <div class="tomyum-panel__facts">
                        <div class="tomyum-panel__fact">
                            <span class="tomyum-panel__kicker">Client</span>
                            <span class="tomyum-panel__fact-value">{{ $report['client'] }}</span>
                        </div>
                        <div class="tomyum-panel__fact">
                            <span class="tomyum-panel__kicker">Versions</span>
                            <span class="tomyum-panel__fact-value">{{ $report['firstVersion'] === $report['lastVersion'] ? $report['lastVersion'] : $report['firstVersion'].' → '.$report['lastVersion'] }}</span>
                        </div>
                        <div class="tomyum-panel__fact">
                            <span class="tomyum-panel__kicker">Platform (last)</span>
                            <span class="tomyum-panel__fact-value">{{ $report['platform'] !== '' ? $report['platform'] : '?' }}</span>
                        </div>
                        <div class="tomyum-panel__fact">
                            <span class="tomyum-panel__kicker">Last player</span>
                            <span class="tomyum-panel__fact-value">
                                @if ($report['lastUserId'] !== null)
                                    <a href="{{ route('panel.admin.users.show', ['user' => $report['lastUserId']]) }}">{{ $report['lastUsername'] ?? '#'.$report['lastUserId'] }}</a>
                                @else
                                    signed out
                                @endif
                            </span>
                        </div>
                        <div class="tomyum-panel__fact">
                            <span class="tomyum-panel__kicker">First seen</span>
                            <span class="tomyum-panel__fact-value">{!! timeago(parse_time_to_carbon($report['firstSeenAt'])) !!}</span>
                        </div>
                        <div class="tomyum-panel__fact">
                            <span class="tomyum-panel__kicker">Last seen</span>
                            <span class="tomyum-panel__fact-value">{!! timeago(parse_time_to_carbon($report['lastSeenAt'])) !!}</span>
                        </div>
                    </div>
                    <details>
                        <summary>Stack trace</summary>
                        <pre style="white-space: pre-wrap; word-break: break-word; max-height: 400px; overflow: auto">{{ $report['stackTrace'] }}</pre>
                    </details>
                    <p>
                        @foreach (($report['resolvedAt'] === null ? ['resolve' => ['fa-check', 'Mark fixed']] : ['reopen' => ['fa-undo', 'Open again']]) + ['delete' => ['fa-trash', 'Delete']] as $action => [$icon, $label])
                            <form method="POST" action="{{ route('panel.admin.client-errors.action', ['id' => $report['id'], 'action' => $action]) }}" style="display: inline"
                                @if ($action === 'delete') onsubmit="return confirm('Delete this error report for good?')" @endif>
                                @csrf
                                <input type="hidden" name="status" value="{{ $status }}">
                                <input type="hidden" name="page" value="{{ $page }}">
                                <button class="btn-osu-big btn-osu-big--rounded-thin {{ $action === 'resolve' ? '' : 'btn-osu-big--panel-secondary' }}" type="submit"><i class="fas {{ $icon }}"></i>{{ $label }}</button>
                            </form>
                        @endforeach
                    </p>
                </div>
            @endforeach

            @if ($pages > 1)
                <div class="tomyum-panel__card">
                    @if ($page > 1)
                        <a class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" href="{{ $query(['page' => $page - 1 > 1 ? $page - 1 : null]) }}">Newer</a>
                    @endif
                    <span class="tomyum-panel__hint">Page {{ $page }} of {{ $pages }}</span>
                    @if ($page < $pages)
                        <a class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" href="{{ $query(['page' => $page + 1]) }}">Older</a>
                    @endif
                </div>
            @endif
        @endif
    </div>
@endsection
