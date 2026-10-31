{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: sign-up is in the game only (users/create, /register and legacy clients' p/register show this). --}}
@php
    $faqUrl = wiki_url('FAQ', 'en').'#registration';
@endphp
@extends('master', ['titlePrepend' => 'Create an account'])

@section('content')
    @include('layout._page_header_v4')

    {{-- TomYum: laid out like the Rules pages (tomyum-legal). --}}
    <div class="osu-page osu-page--generic">
        <div class="tomyum-legal">
            <h1 class="tomyum-legal__title">Accounts can't be made on the website</h1>

            <div class="tomyum-legal__body tomyum-legal__body--spaced">
                <p>
                    Account registration can't be done here. Please read
                    <a href="{{ $faqUrl }}">How do I make an account?</a> in the FAQ.
                </p>
            </div>
        </div>
    </div>
@endsection
