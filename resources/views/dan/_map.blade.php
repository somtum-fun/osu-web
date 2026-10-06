{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: one map of an osu!dan course, linked to its set's page. Params: $map (from TomYumDan::courses). --}}
@php
    $beatmap = $map->beatmap;
    $set = $beatmap?->beatmapset;
@endphp
@if ($beatmap !== null && $set !== null)
    <a href="{{ route('beatmapsets.show', ['beatmapset' => $set->getKey()]) }}#mania/{{ $beatmap->getKey() }}">{{ $set->artist }} - {{ $set->title }} [{{ $beatmap->version }}]</a>
@else
    <span class="tomyum-dan__hint">Beatmap #{{ $map->beatmap_id }} (not on the site yet)</span>
@endif
