{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: one osu!dan course (DanController@show): its rules, maps and everyone who cleared it, first clear first. --}}
@php
    use App\Libraries\TomYumDan;
@endphp
@extends('master', [
    'titlePrepend' => "{$course->name} ({$course->keys}K)",
    'canonicalUrl' => route('dan.show', ['course' => $course->id]),
    'pageDescription' => "{$course->name} ({$course->keys}K) osu!dan course maps, accuracy rules and clears on ".App\Libraries\TomYumBranding::name().'!',
])

@section('content')
    @component('layout._page_header_v4', ['params' => [
        'links' => [
            ['title' => 'Courses', 'url' => route('dan.index')],
            ['active' => true, 'title' => "{$course->name} ({$course->keys}K)", 'url' => route('dan.show', ['course' => $course->id])],
        ],
        'linksBreadcrumb' => true,
        'theme' => 'contests',
    ]])
    @endcomponent

    <div class="osu-page osu-page--generic-compact tomyum-dan">
        <div class="page-extra">
            <h2 class="title title--page-extra">
                {{ $course->name }} ({{ $course->keys }}K)
                @if ($course->is_intro)
                    <span class="tomyum-dan__tag">intro</span>
                @endif
            </h2>
            @if (!$course->active)
                <p class="tomyum-dan__hint">This course isn't offered any more, so it can't be cleared now. Its clears are kept.</p>
            @endif
            @if (present($course->description))
                <p>{{ $course->description }}</p>
            @endif
            <dl class="tomyum-dan__facts">
                <dt>{{ count($course->maps) === 1 ? 'Map' : 'Maps' }}</dt>
                <dd>
                    @forelse ($course->maps as $map)
                        <div>@include('dan._map', ['map' => $map])</div>
                    @empty
                        <span class="tomyum-dan__hint">No maps yet</span>
                    @endforelse
                </dd>
                <dt>Accuracy</dt>
                <dd>{{ TomYumDan::accuracyText($course->min_accuracy) }} or more</dd>
                <dt>Mods allowed</dt>
                <dd>{{ TomYumDan::modNames((int) $course->allowed_mods) }}</dd>
                <dt>Pausing</dt>
                <dd>{{ $course->no_pause ? 'Not allowed: a pause ends the attempt' : 'Allowed' }}</dd>
            </dl>
            <p class="tomyum-dan__hint"><a href="{{ route('dan.index') }}">All courses and the rules</a></p>
        </div>

        <div class="page-extra">
            <h2 class="title title--page-extra">Cleared by {{ i18n_number_format(count($clears)) }} {{ count($clears) === 1 ? 'player' : 'players' }}</h2>
            @if (count($clears) === 0)
                <p class="tomyum-dan__empty">Nobody has cleared this course yet. Be the first!</p>
            @else
                <div class="tomyum-dan__table-wrap">
                    <table class="tomyum-dan__table">
                        <tr>
                            <th>#</th>
                            <th>Player</th>
                            <th>Accuracy</th>
                            <th>Mods</th>
                            <th>First cleared</th>
                        </tr>
                        @foreach ($clears as $clear)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <span class="tomyum-dan__user">
                                        @include('objects._flag_country', ['country' => $clear->user->country_acronym])
                                        {!! link_to_user($clear->user, null, null, ['tomyum-dan__player']) !!}
                                    </span>
                                </td>
                                <td>{{ TomYumDan::accuracyText($clear->accuracy) }}</td>
                                <td>{{ TomYumDan::playModNames((int) $clear->mods) }}</td>
                                <td class="tomyum-dan__nowrap">{!! timeago(Carbon\Carbon::parse($clear->cleared_at, 'UTC')) !!}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
