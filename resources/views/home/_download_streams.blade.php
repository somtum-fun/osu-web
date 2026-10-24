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
        <strong>TumThai!Next</strong> is TumThai's performance stream, on <code>tumthai-framework</code>:
        @if ($lazerPlatform === 'android')
            a 60–240 Hz display refresh rate, a 500–8000 Hz input/audio rate, Android's low-latency AAudio output and a latency counter.
        @else
            a 500–8000 Hz input/audio/update rate, display refresh rate control, Vulkan and a latency counter.
        @endif
        Same account, beatmaps and scores.
        @if ($lazerPlatform === 'windows_x64')
            Switch streams any time in the game (Settings → General → Updates) or the Somtum Launcher.
        @else
            Switch streams any time in the Somtum Launcher, or download the other one here.
        @endif
    </div>
@endif
