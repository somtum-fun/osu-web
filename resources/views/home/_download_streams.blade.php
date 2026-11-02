{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: TumThai's two streams (?stream=next): TumThai!Next is the same game on tumthai-framework. --}}
@if ($lazerStreams !== [])
    <div class="download-page__text download-page__text--download-portable">
        Stream:
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
            <strong>TumThai!Next is recommended on Android</strong>: it runs smoother and with less delay on phones.
        @else
            <strong>TumThai!Next</strong> is our faster stream: it runs smoother on low-end PCs, and has the newest performance options.
        @endif
        Same account, beatmaps and scores.
        @if ($lazerPlatform === 'windows_x64')
            Switch streams any time in the game (Settings → General → Updates) or the Somtum Launcher.
        @elseif ($lazerPlatform === 'android')
            Want the other one? Download it here.
        @else
            Switch streams any time in the Somtum Launcher, or download the other one here.
        @endif
    </div>
@endif
