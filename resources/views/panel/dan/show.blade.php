{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: one osu!dan course (Panel\DanController@show): change it, remove it, and its latest attempts. --}}
@php
    use App\Libraries\TomYumDan;
@endphp
@extends('master', ['titlePrepend' => "osu!dan: {$course->name}"])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.dan.index'), 'title' => 'osu!dan'],
        ['title' => "{$course->name} ({$course->keys}K)"],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-graduation-cap"></i>{{ $course->name }} ({{ $course->keys }}K)</h2>
            <p class="tomyum-panel__hint">
                {{ i18n_number_format($clears) }} {{ $clears === 1 ? 'player has' : 'players have' }} cleared it.
                <a href="{{ route('dan.show', ['course' => $course->id]) }}">Its page</a>.
            </p>
            <form method="POST" action="{{ route('panel.dan.update', ['course' => $course->id]) }}">
                @csrf
                @include('panel.dan._form', ['course' => $course])
                <div style="margin-top: 15px">
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-check"></i>Save</button>
                </div>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-history"></i>Latest attempts</h2>
            @if ($attempts->isEmpty())
                <p class="tomyum-panel__empty">None yet.</p>
            @else
                <div class="tomyum-panel__scroll">
                    <table class="tomyum-panel__table">
                        <tr><th>When</th><th>Player</th><th>Result</th><th>Accuracy</th><th>Mods</th><th>Client</th><th>Score</th></tr>
                        @foreach ($attempts as $attempt)
                            @php
                                $player = $users[$attempt->user_id] ?? null;
                            @endphp
                            <tr>
                                <td style="white-space: nowrap">{!! timeago(Carbon\Carbon::parse($attempt->created_at, 'UTC')) !!}</td>
                                <td>
                                    @if ($player !== null)
                                        <a href="{{ route('users.show', ['user' => $player->getKey()]) }}">{{ $player->username }}</a>
                                    @else
                                        #{{ $attempt->user_id }}
                                    @endif
                                </td>
                                <td>
                                    <span class="tomyum-panel__pill tomyum-panel__pill--{{ $attempt->result === 'clear' ? 'good' : ($attempt->result === 'failed' ? 'info' : 'bad') }}">
                                        {{ TomYumDan::RESULTS[$attempt->result] ?? $attempt->result }}
                                    </span>
                                </td>
                                <td>{{ TomYumDan::accuracyText($attempt->accuracy) }}</td>
                                <td class="tomyum-panel__nowrap">@include('panel._mods', ['mods' => (int) $attempt->mods])</td>
                                <td class="tomyum-panel__nowrap">{{ $attempt->client_version }}</td>
                                <td>{{ $attempt->score_id }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-trash"></i>Remove the course</h2>
            @if ($clears > 0)
                <p class="tomyum-panel__hint">Players have cleared it, so it can only be switched off (untick "Active" above), which keeps their clears.</p>
            @else
                <p class="tomyum-panel__hint">Nobody has cleared it. Removing it also removes its attempts.</p>
                <form method="POST" action="{{ route('panel.dan.destroy', ['course' => $course->id]) }}"
                    onsubmit="return confirm({{ json_encode('Remove the course '.$course->name.'?') }})">
                    @csrf
                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Remove</button>
                </form>
            @endif
        </div>
    </div>
@endsection
