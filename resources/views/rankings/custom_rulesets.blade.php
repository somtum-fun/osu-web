{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: a custom lazer ruleset's rankings by Pla-ra pp, or ranked score (TomYumRulesetsController@rankings). --}}
@php
    // This page's link with other filters: the board, the country and the sort stay as they are unless given.
    $rankingsUrl = fn (array $with = []) => route('rankings.custom-rulesets', array_filter([
        'slug' => $ruleset->slug,
        'country' => $country,
        'variant' => $variant === 'vanilla' ? null : $variant,
        'type' => $sort === 'performance' ? null : $sort,
        ...$with,
    ]));
@endphp
@extends('rankings.index', [
    // The rankings page's own layout: the mode bar (this ruleset selected, the others in "…"), Performance and Score tabs, the filters.
    'customRulesetCurrent' => [
        $ruleset->name,
        route('rankings.custom-rulesets', ['slug' => $ruleset->slug]),
        $ruleset->icon_url === null ? null : route('tomyum.rulesets.icon', ['slug' => $ruleset->slug]),
    ],
    'hasFilter' => false,
    // A ruleset staff rank by score has that tab only.
    'linksOverride' => $ruleset->ranksByPp() ? [
        [
            'active' => $sort === 'performance',
            'title' => osu_trans('rankings.stat.performance'),
            'url' => $rankingsUrl(['type' => null]),
        ],
        [
            'active' => $sort === 'score',
            'title' => osu_trans('rankings.stat.ranked_score'),
            'url' => $rankingsUrl(['type' => 'score']),
        ],
        [
            'active' => $sort === 'top_plays',
            'title' => osu_trans('rankings.type.top_plays'),
            'url' => $rankingsUrl(['type' => 'top_plays', 'country' => null]),
        ],
    ] : [
        ['active' => true, 'title' => osu_trans('rankings.stat.ranked_score'), 'url' => $rankingsUrl(['type' => null])],
    ],
    'pageDescription' => $sort === 'top_plays'
        ? "{$ruleset->name} top plays: TumThai's best pp plays."
        : "{$ruleset->name} rankings: TumThai players by ".($sort === 'score' ? 'ranked score' : 'pp').'.',
    'params' => ['mode' => $ruleset->slug, 'type' => null],
    'rulesetSelectorUrlFn' => fn (string $r): string => $sort === 'top_plays'
        ? route('rankings.top-plays', ['mode' => $r])
        : route('rankings', ['mode' => $r, 'type' => 'score']),
    'titlePrepend' => $sort === 'top_plays' ? "{$ruleset->name} top plays" : "{$ruleset->name} rankings",
])

@section('ranking-header')
    <div class="osu-page osu-page--ranking-info">
        <div class="grid-items grid-items--ranking-filter">
            {{-- TomYum: Relax and Autopilot plays have their own rankings. --}}
            <div class="ranking-filter">
                <div class="ranking-filter__title">Board</div>
                <div class="sort sort--ranking-header">
                    <div class="sort__items">
                        {{-- Only the boards it has (Relax/Autopilot when it has those mods). --}}
                        @foreach (array_intersect_key(['vanilla' => 'Vanilla', 'relax' => 'Relax', 'autopilot' => 'Autopilot'], array_flip(App\Libraries\TomYumCustomRulesets::variants($ruleset))) as $key => $label)
                            <a
                                class="{{ class_with_modifiers('sort__item', 'button', ['active' => $variant === $key]) }}"
                                href="{{ $rankingsUrl(['variant' => $key === 'vanilla' ? null : $key]) }}"
                            >{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
            {{-- Top plays are worldwide only, as osu!'s. --}}
            @if ($sort !== 'top_plays')
            <div class="ranking-filter">
                <div class="ranking-filter__title">{{ osu_trans('rankings.countries.title') }}</div>
                <div class="sort sort--ranking-header">
                    <div class="sort__items">
                        <a
                            class="{{ class_with_modifiers('sort__item', 'button', ['active' => $country === null]) }}"
                            href="{{ $rankingsUrl(['country' => null]) }}"
                        >{{ osu_trans('rankings.countries.all') }}</a>
                        @php
                            $viewerCountry = Auth::user()?->country_acronym;
                        @endphp
                        @if ($viewerCountry !== null && $viewerCountry !== '' && $viewerCountry !== $country)
                            <a class="sort__item button" href="{{ $rankingsUrl(['country' => $viewerCountry]) }}">
                                @include('objects._flag_country', ['country' => $viewerCountry])
                            </a>
                        @endif
                        @if ($country !== null)
                            <span class="sort__item button active">@include('objects._flag_country', ['country' => $country]) {{ $country }}</span>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>
        <p class="ranking-page-info" style="opacity: 0.7; margin-top: 10px">
            {{ $ruleset->name }} is a custom osu!lazer ruleset, played in TumThai (install it in TumThai's Settings).
            @if ($sort === 'top_plays')
                These are the best plays by pp (Pla-ra's): each player's best on each ranked map, up to 1000. Pla-ra is new:
                expect its numbers to change as it's tuned.
            @elseif ($sort === 'score')
                Players are ranked by ranked score: their best score on each ranked map, added up.
            @else
                Players are ranked by pp, worked out by Pla-ra from each play's star rating, accuracy and misses: their best pp on
                each ranked map, weighted as osu!'s (the best counts fully, the next 95%, then 90%...). Pla-ra is new: expect its
                numbers to change as it's tuned.
            @endif
            @if ($ruleset->source_url !== null)
                <a href="{{ $ruleset->source_url }}" rel="nofollow noopener" target="_blank">About {{ $ruleset->name }}</a>.
            @endif
        </p>
    </div>
@endsection

@section('scores')
    @if ($scores->total() === 0)
        <p style="padding: 20px; text-align: center; opacity: 0.7">Nobody has a ranked {{ $ruleset->name }} play{{ $country !== null ? ' in this country' : '' }} yet.</p>
    @elseif ($sort === 'top_plays')
        {{-- The rankings' own Top Plays list (ranking-top-plays). --}}
        <div
            class="u-contents js-react"
            data-props="{{ json_encode([
                'first_score_rank' => $scores->firstItem(),
                'scores' => $scores->items(),
            ]) }}"
            data-react="ranking-top-plays"
        ></div>
        @include('layout._react_js', ['src' => 'js/ranking-top-plays.js'])
    @else
        <table class="ranking-page-table">
            <thead>
                <tr>
                    <th></th>
                    <th class="ranking-page-table__heading ranking-page-table__heading--main"></th>
                    @if ($ruleset->ranksByPp())
                        <th class="{{ class_with_modifiers('ranking-page-table__heading', ['focused' => $sort === 'performance']) }}">{{ osu_trans('rankings.stat.performance') }}</th>
                    @endif
                    <th class="{{ class_with_modifiers('ranking-page-table__heading', ['focused' => $sort === 'score']) }}">Ranked score</th>
                    <th class="ranking-page-table__heading">Maps</th>
                    <th class="ranking-page-table__heading">Best play</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $firstItem = $scores->firstItem();
                @endphp
                @foreach ($scores as $index => $row)
                    @php
                        $user = $users[$row['user_id']] ?? null;
                    @endphp
                    @if ($user !== null)
                        <tr class="ranking-page-table__row">
                            <td class="ranking-page-table__column">#{{ i18n_number_format($firstItem + $index) }}</td>
                            <td class="ranking-page-table__column ranking-page-table__column--main">
                                @include('rankings._main_column', [
                                    'object' => $user,
                                    // Their profile on this ruleset (and board).
                                    'userUrl' => route('users.show', ['user' => $user->getKey(), 'mode' => $ruleset->slug]).($variant === 'vanilla' ? '' : "?variant={$variant}"),
                                ])
                            </td>
                            @if ($ruleset->ranksByPp())
                                <td class="{{ class_with_modifiers('ranking-page-table__column', ['dimmed' => $sort !== 'performance']) }}">{{ i18n_number_format(round($row['pp'])) }}pp</td>
                            @endif
                            <td class="{{ class_with_modifiers('ranking-page-table__column', ['dimmed' => $sort !== 'score']) }}">{{ i18n_number_format($row['ranked_score']) }}</td>
                            <td class="ranking-page-table__column ranking-page-table__column--dimmed">{{ i18n_number_format($row['maps']) }}</td>
                            <td class="ranking-page-table__column ranking-page-table__column--dimmed">{{ i18n_number_format($row['best']) }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
