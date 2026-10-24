{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    TomYum: osu!dan (DanController@index): the rules, the courses by key count with their maps and how
    many cleared them, and the latest clears (bem/tomyum-dan.less).
--}}
@php
    use App\Libraries\TomYumDan;
@endphp
@extends('master', [
    'canonicalUrl' => route('dan.index'),
    'pageDescription' => 'osu!dan (osu dan / DanReform) osu!mania courses, rules and clears on '.App\Libraries\TomYumBranding::name().'!',
])

@section('content')
    @component('layout._page_header_v4', ['params' => [
        'links' => [['active' => true, 'title' => 'Courses', 'url' => route('dan.index')]],
        'theme' => 'contests',
    ]])
    @endcomponent

    <div class="osu-page osu-page--generic-compact tomyum-dan">
        <div class="page-extra">
            <h2 class="title title--page-extra">osu!dan</h2>
            <div class="tomyum-dan__about">
                <div>
                    <p>
                        osu!dan is a ladder of osu!mania courses, from the intro courses up to the hardest dans.
                        Each course is a long map (a marathon) that tests everything at once. Clear one and it shows
                        on your profile; then try the next.
                    </p>
                    <p>
                        The courses are <a href="https://danreform.com" rel="noopener" target="_blank">DanReform</a>'s.
                        Thank you to the DanReform team for making them.
                    </p>
                </div>
                <div>
                    <h3 class="tomyum-dan__subtitle">The rules</h3>
                    <ul class="tomyum-dan__rules">
                        <li>Play in <strong>osu!stable</strong>: one of our patched clients from the <a href="{{ route('download') }}">download page</a>, or today's osu!. osu!dan is osu!stable only for now: osu!lazer doesn't submit scores on dan maps (stay tuned).</li>
                        <li>Play in <strong>osu!mania</strong>. When you start a course map, BanchoBot spectates you to check the play.</li>
                        <li><strong>Don't pause.</strong> A pause ends the attempt; press Retry to start a new one.</li>
                        <li>Reach the course's <strong>accuracy</strong>: usually 96%, or 95% for intro courses.</li>
                        <li>Use only the course's <strong>mods</strong>: usually Mirror, Hidden, Fade In, Flashlight, Hard Rock, Double Time and Nightcore. No Fail, Easy and Half Time don't count.</li>
                    </ul>
                    <p class="tomyum-dan__hint">
                        In osu!stable, a play that breaks a rule is still saved as a normal score; it just isn't a clear.
                        In osu!stable, type <code>!dan</code> to BanchoBot to see your dans.
                    </p>
                </div>
            </div>
        </div>

        @if (count($coursesByKeys) === 0)
            <div class="page-extra">
                <p class="tomyum-dan__empty">There are no courses yet. Check back soon!</p>
            </div>
        @endif

        @foreach ($coursesByKeys as $keys => $courses)
            <div class="page-extra">
                <h2 class="title title--page-extra">{{ $keys }}K courses</h2>
                <div class="tomyum-dan__table-wrap">
                    <table class="tomyum-dan__table tomyum-dan__table--courses">
                        <tr>
                            <th>Course</th>
                            <th>Map</th>
                            <th>Accuracy</th>
                            <th>Mods</th>
                            <th>Cleared by</th>
                        </tr>
                        @foreach ($courses as $course)
                            @php
                                $clears = $clearCounts[(int) $course->id] ?? 0;
                            @endphp
                            <tr>
                                {{-- TomYum: data-label names a cell on a phone, where each course is a block, not a row. --}}
                                <td class="tomyum-dan__course-cell">
                                    <a class="tomyum-dan__course" href="{{ route('dan.show', ['course' => $course->id]) }}">{{ $course->name }}</a>
                                    @if ($course->is_intro)
                                        <span class="tomyum-dan__tag">intro</span>
                                    @endif
                                    @if (present($course->description))
                                        <div class="tomyum-dan__hint">{{ $course->description }}</div>
                                    @endif
                                </td>
                                <td class="tomyum-dan__maps">
                                    @forelse ($course->maps as $map)
                                        <div>@include('dan._map', ['map' => $map])</div>
                                    @empty
                                        <span class="tomyum-dan__hint">No maps yet</span>
                                    @endforelse
                                </td>
                                <td data-label="Accuracy">{{ TomYumDan::accuracyText($course->min_accuracy) }}</td>
                                <td data-label="Mods">
                                    {{ TomYumDan::modNames((int) $course->allowed_mods) }}
                                    @if (!$course->no_pause)
                                        <div class="tomyum-dan__hint">pausing allowed</div>
                                    @endif
                                </td>
                                <td data-label="Cleared by">
                                    <a href="{{ route('dan.show', ['course' => $course->id]) }}">{{ i18n_number_format($clears) }} {{ $clears === 1 ? 'player' : 'players' }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        @endforeach

        @if (count($recentClears) > 0)
            <div class="page-extra">
                <h2 class="title title--page-extra">Latest clears</h2>
                <ol class="tomyum-dan__clears">
                    @foreach ($recentClears as $clear)
                        @php
                            $course = $coursesById[(int) $clear->course_id] ?? null;
                        @endphp
                        @continue($course === null)
                        <li class="tomyum-dan__clear">
                            <span class="tomyum-dan__user">
                                @include('objects._flag_country', ['country' => $clear->user->country_acronym])
                                {!! link_to_user($clear->user, null, null, ['tomyum-dan__player']) !!}
                            </span>
                            cleared
                            <a href="{{ route('dan.show', ['course' => $course->id]) }}">{{ $course->name }} ({{ $course->keys }}K)</a>
                            with {{ TomYumDan::accuracyText($clear->accuracy) }}
                            <span class="tomyum-dan__hint">{!! timeago(Carbon\Carbon::parse($clear->cleared_at, 'UTC')) !!}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </div>
@endsection
