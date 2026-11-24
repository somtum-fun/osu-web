{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: osu!(stable) without the launcher: our own stable client's exe (it installs itself), or, while the
     download list has none, the Somtum Patcher for the player's own osu!. --}}
@if (!empty($stableClient))
    <div class="download-page__text download-page__text--download-stable">
        {{ $tyT('stable_client.text') }}
        @if ($launcher !== null || $launcherLinux !== null)
            {{ $tyT('stable_client.launcher_easier') }}
        @endif
    </div>
    <div class="download-page__stable-client">
    @foreach ($stableClient as $stream => $url)
        <a class="btn-osu-big btn-osu-big--download btn-osu-big--download-stable" href="{{ $url }}">
            <div class="btn-osu-big__content">
                <div class="btn-osu-big__left">
                    <div>
                        <div class="btn-osu-big__text-top btn-osu-big__text-top--download">
                            {{ $tyT("stable_client.{$stream}") }}
                        </div>
                        {{ $tyT('stable_client.platforms') }}
                    </div>
                </div>
                <span class="btn-osu-big__icon">
                    <span class="svg-icon svg-icon--download"></span>
                </span>
            </div>
        </a>
    @endforeach
    </div>
@else
    <div class="download-page__text download-page__text--download-stable">
        {!! $tyT('patcher.text') !!}
        @if ($launcher !== null || $launcherLinux !== null)
            {{ $tyT('patcher.launcher_easier') }}
        @endif
    </div>
    <a class="btn-osu-big btn-osu-big--download btn-osu-big--download-stable" href="{{ $patcherUrl }}">
        <div class="btn-osu-big__content">
            <div class="btn-osu-big__left">
                <div>
                    <div class="btn-osu-big__text-top btn-osu-big__text-top--download">
                        {{ App\Libraries\TomYumBranding::text('downloads_patcher_name') }}
                    </div>
                    {{ $tyT('patcher.platforms') }}
                </div>
            </div>
            <span class="btn-osu-big__icon">
                <span class="svg-icon svg-icon--download"></span>
            </span>
        </div>
    </a>
@endif
