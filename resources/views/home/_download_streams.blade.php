{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: TumThai's two streams (?stream=next): TumThai!Next is the same game on tumthai-framework. --}}
@if ($lazerStreams !== [])
    <div class="download-page__text download-page__text--download-portable">
        {{ $tyT('streams.label') }}
        @foreach ($lazerStreams as $id => $stream)
            @if ($id === $lazerStream)
                <strong>{{ $stream['name'] }}</strong>
            @else
                <a href="{{ $stream['url'] }}">{{ $stream['name'] }}</a>
            @endif
            @if (!$loop->last)
                ·
            @endif
        @endforeach
        <br>
        @if ($lazerPlatform === 'android')
            {!! $tyT('streams.android') !!}
        @else
            {!! $tyT('streams.other') !!}
        @endif
        {{ $tyT('streams.same') }}
        @if ($lazerPlatform === 'windows_x64')
            {{ $tyT('streams.switch_windows') }}
        @elseif ($lazerPlatform === 'android')
            {{ $tyT('streams.switch_android') }}
        @else
            {{ $tyT('streams.switch_other') }}
        @endif
    </div>
@endif
