{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: osu!dan courses (Panel\DanController): the list, and making a course. Admins and the NAT. --}}
@php
    use App\Libraries\TomYumDan;
@endphp
@extends('master', ['titlePrepend' => 'osu!dan'])

@section('content')
    @include('panel._header', ['crumbs' => [['title' => 'osu!dan']]])
    <div class="osu-page osu-page--admin tomyum-panel">
        @if (!$available)
            <div class="tomyum-panel__card">
                <h2><i class="fas fa-plug"></i>The plugin isn't on yet</h2>
                <p class="tomyum-panel__hint">
                    The courses live in the game server's tables, which its tomyum.dan plugin makes when it first starts.
                    Turn the plugin on (see its README), then come back here.
                </p>
            </div>
        @else
            <div class="tomyum-panel__card">
                <h2><i class="fas fa-graduation-cap"></i>Courses</h2>
                <p class="tomyum-panel__hint">
                    Players see the active ones on <a href="{{ route('dan.index') }}">the osu!dan page</a>.
                    The game reads changes within a minute.
                </p>
                @if (count($courses) === 0)
                    <p class="tomyum-panel__empty">None yet. Make the first one below.</p>
                @else
                    <div class="tomyum-panel__scroll">
                        <table class="tomyum-panel__table">
                            <tr><th>Course</th><th>Keys</th><th>Order</th><th>Maps</th><th>Accuracy</th><th>Mods</th><th>Clears</th><th></th></tr>
                            @foreach ($courses as $course)
                                <tr>
                                    <td class="panel-dan__course">
                                        <a href="{{ route('panel.dan.show', ['course' => $course->id]) }}">{{ $course->name }}</a>
                                        @if ($course->is_intro)<span class="tomyum-panel__pill tomyum-panel__pill--info">intro</span>@endif
                                        @if (!$course->active)<span class="tomyum-panel__pill tomyum-panel__pill--warn">off</span>@endif
                                        @if (present($course->category))<div class="tomyum-panel__hint">{{ $course->category }}</div>@endif
                                    </td>
                                    <td>{{ $course->keys }}K</td>
                                    <td>{{ $course->sort_order }}</td>
                                    {{-- TomYum: the difficulty names only (the whole title on hover), so a long course stays a short row. --}}
                                    <td>
                                        <div class="panel-dan__maps">
                                            @forelse ($course->maps as $map)
                                                @php
                                                    $beatmap = $map->beatmap;
                                                    $set = $beatmap?->beatmapset;
                                                @endphp
                                                @if ($beatmap !== null && $set !== null)
                                                    <a href="{{ route('beatmapsets.show', ['beatmapset' => $set->getKey()]) }}#mania/{{ $beatmap->getKey() }}" title="{{ $set->artist }} - {{ $set->title }} [{{ $beatmap->version }}]">{{ $beatmap->version }}</a>
                                                @else
                                                    <span class="tomyum-panel__muted">#{{ $map->beatmap_id }} (not on the site yet)</span>
                                                @endif
                                            @empty
                                                <span class="tomyum-panel__muted">No maps</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td>{{ TomYumDan::accuracyText($course->min_accuracy) }}</td>
                                    <td>{{ TomYumDan::modNames((int) $course->allowed_mods) }}@if (!$course->no_pause)<div class="tomyum-panel__hint">pausing allowed</div>@endif</td>
                                    <td>{{ i18n_number_format($clearCounts[(int) $course->id] ?? 0) }}</td>
                                    <td style="text-align: right">
                                        <a class="btn-osu-big btn-osu-big--rounded-thin" href="{{ route('panel.dan.show', ['course' => $course->id]) }}">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif
            </div>

            <div class="tomyum-panel__card">
                <h2><i class="fas fa-plus"></i>Make a course</h2>
                <form method="POST" action="{{ route('panel.dan.store') }}">
                    @csrf
                    @include('panel.dan._form', ['course' => null])
                    <div style="margin-top: 15px">
                        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-check"></i>Make it</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
@endsection
