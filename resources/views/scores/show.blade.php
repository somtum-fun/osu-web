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
