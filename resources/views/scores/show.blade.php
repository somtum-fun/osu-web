{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    $username = $score->user === null || $score->user->trashed() ? osu_trans('users.deleted') : $score->user->username;
    $title = osu_trans('scores.show.title', [
        'username' => $username,
        'title' => $score->beatmap->beatmapset->getDisplayTitle(auth()->user()),
        'version' => $score->beatmap->version,
    ]);

    // TomYum: link embeds (Discord, X, ...) show the play: grade, pp, accuracy, combo, mods, stars and the map's cover.
    $embedParts = ['Rank '.($scoreJson['rank'] ?? 'F')];
    if (isset($scoreJson['pp'])) {
        $embedParts[] = i18n_number_format(round((float) $scoreJson['pp'])).'pp';
    }
    $embedParts[] = i18n_number_format(100 * (float) ($scoreJson['accuracy'] ?? 0), null, null, 2).'%';
    $embedParts[] = i18n_number_format((int) ($scoreJson['max_combo'] ?? 0)).'x';
    $embedMods = implode('', array_column($scoreJson['mods'] ?? [], 'acronym'));
    if ($embedMods !== '') {
        $embedParts[] = '+'.$embedMods;
    }
    $embedRuleset = App\Libraries\TomYumCustomRulesets::find($score->ruleset_id)?->name
        ?? osu_trans('beatmaps.mode.'.(App\Enums\Ruleset::tryFrom((int) $score->ruleset_id)?->legacyName() ?? 'osu'));
    $embedStars = $scoreJson['beatmap']['difficulty_rating'] ?? null;
    $embedParts[] = ($embedStars === null ? '' : i18n_number_format((float) $embedStars, null, null, 2).'★ ').$embedRuleset;
    if (($scoreJson['rank_global'] ?? null) !== null) {
        $embedParts[] = '#'.i18n_number_format((int) $scoreJson['rank_global']).' on the map';
    }
    $embedCovers = $scoreJson['beatmapset']['covers'] ?? [];
    Request::instance()->attributes->set('opengraph', [
        'title' => $title,
        'description' => implode(' · ', $embedParts),
        'image' => $embedCovers['list@2x'] ?? $embedCovers['card'] ?? null,
    ]);
@endphp
@extends('master', [
    'titlePrepend' => $title,
])

@section('content')
    <div class="js-react u-contents" data-react="scores-show"></div>

    <script id="json-show" type="application/json">
        {!! json_encode($scoreJson) !!}
    </script>

    {{-- TomYum: a custom lazer ruleset's play: its ruleset (the header's icon and beatmap link). --}}
    @if (($customRuleset = App\Libraries\TomYumCustomRulesets::find($score->ruleset_id)) !== null)
        <script id="json-tomyum-custom-ruleset" type="application/json">
            {!! json_encode(array_intersect_key(App\Libraries\TomYumCustomRulesets::toApi($customRuleset), array_flip(['slug', 'name', 'icon']))) !!}
        </script>
    @endif

    <script id="json-raw" type="application/json">
        {!! json_encode($score->getAttributes()) !!}
    </script>

    @include('layout._react_js', ['src' => 'js/scores-show.js'])
@endsection
