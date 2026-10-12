{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master')

@section('content')
@component('layout._page_header_v4')
@endcomponent

<div class="osu-page osu-page--generic-compact">
    <div class="download-page">
        <div class="download-page__header">
            <div class="download-page__banner">
                @if (App\Libraries\TomYumBranding::downloadVideoUrl() !== null) {{-- TomYum --}}
                <video class="download-page__banner-video" src="{{ App\Libraries\TomYumBranding::downloadVideoUrl() }}" autoplay muted loop playsinline>
                </video>
                @else
                    <div class="download-page__banner-logo"></div>
                @endif
                @if ($showLazer) {{-- TomYum: Admin panel > Branding --}}
                <div class="download-page__banner-content download-page__banner-content--main">
                    <div class="download-page__tagline">
                        <span class="download-page__tagline-1">{{ osu_trans('home.download.tagline_1') }}</span>
                        <span class="download-page__tagline-2">{{ osu_trans('home.download.tagline_2') }}</span>
                    </div>
                    @if ($launcher !== null)
                    {{-- TomYum: Somtum Launcher first on Windows: TumThai, stable and the old clients, kept up to date. --}}
                    <a
                        class="btn-osu-big btn-osu-big--download"
                        href="{{ $launcher['url'] }}"
                    >
                        <div class="btn-osu-big__content">
                            <div class="btn-osu-big__left">
                                {{ osu_trans('home.download.download') }}
                                <div>
                                    <div class="btn-osu-big__text-top btn-osu-big__text-top--download">
                                        Somtum Launcher
                                    </div>
                                    {{ osu_trans('home.download.for_os', ['os' => $lazerPlatformName]) }}
                                </div>
                                <div class="btn-osu-big__text-bottom btn-osu-big__text-bottom--download-version">
                                    {{ $launcher['version'] }}
                                    @if (isset($launcher['size']))
                                        ({{ App\Libraries\TomYumDownloads::size($launcher['size']) }})
                                    @endif
                                </div>
                            </div>
                            <span class="btn-osu-big__icon">
                                <span class="svg-icon svg-icon--download"></span>
                            </span>
                        </div>
                    </a>
                    <div class="download-page__text download-page__text--download-launcher">
                        Recommended. One app for {{ App\Libraries\TomYumBranding::text('downloads_client_name') }}, osu!(stable) and the 2008-2015 clients, and it keeps them up to date.
                        @if (isset($launcher['portable']['url']))
                            No install wanted? <a href="{{ $launcher['portable']['url'] }}">Get the portable version</a>
                            ({{ App\Libraries\TomYumDownloads::size($launcher['portable']['size'] ?? null) }}).
                        @endif
                    </div>
                    <div class="download-page__text download-page__text--download-portable">
                        Just {{ App\Libraries\TomYumBranding::text('downloads_client_name') }}?
                        <a href="{{ $lazerUrl }}">Download the installer</a>
                        @if (isset($lazer['size']))
                            ({{ App\Libraries\TomYumDownloads::size($lazer['size']) }})
                        @endif
                        @if ($lazerPortable !== null)
                            or <a href="{{ $lazerPortable['url'] }}">the portable zip</a>
                            ({{ App\Libraries\TomYumDownloads::size($lazerPortable['size'] ?? null) }})
                        @endif
                    </div>
                    @elseif ($launcherLinux !== null && isset($launcherLinux['url']))
                    {{-- TomYum: the Linux launcher first on Linux too (TumThai, osu!(stable) through osu-winello, the old clients). --}}
                    <a
                        class="btn-osu-big btn-osu-big--download"
                        href="{{ $launcherLinux['url'] }}"
                    >
                        <div class="btn-osu-big__content">
                            <div class="btn-osu-big__left">
                                {{ osu_trans('home.download.download') }}
                                <div>
                                    <div class="btn-osu-big__text-top btn-osu-big__text-top--download">
                                        Somtum Launcher
                                    </div>
                                    {{ osu_trans('home.download.for_os', ['os' => $lazerPlatformName]) }}
                                </div>
                                <div class="btn-osu-big__text-bottom btn-osu-big__text-bottom--download-version">
                                    {{ $launcherLinux['version'] ?? '' }}
                                    @if (isset($launcherLinux['size']))
                                        ({{ App\Libraries\TomYumDownloads::size($launcherLinux['size']) }})
                                    @endif
                                </div>
                            </div>
                            <span class="btn-osu-big__icon">
                                <span class="svg-icon svg-icon--download"></span>
                            </span>
                        </div>
                    </a>
                    <div class="download-page__text download-page__text--download-launcher">
                        Recommended. One app for {{ App\Libraries\TomYumBranding::text('downloads_client_name') }}, osu!(stable) (through
                        <a href="https://github.com/NelloKudo/osu-winello">osu-winello</a>) and the 2008-2015 clients.
                        Download it, then run <code>python3 somtum-linux-{{ $launcherLinux['version'] ?? '' }} install</code>.
                    </div>
                    <div class="download-page__text download-page__text--download-portable">
                        Just {{ App\Libraries\TomYumBranding::text('downloads_client_name') }}?
                        <a href="{{ $lazerUrl }}">Download the AppImage</a>
                        @if (isset($lazer['size']))
                            ({{ App\Libraries\TomYumDownloads::size($lazer['size']) }})
                        @endif
                    </div>
                    @else
                    <a
                        class="btn-osu-big btn-osu-big--download"
                        href="{{ $lazerUrl }}"
                    >
                        <div class="btn-osu-big__content">
                            <div class="btn-osu-big__left">
                                {{ osu_trans('home.download.download') }}
                                <div>
                                    <div class="btn-osu-big__text-top btn-osu-big__text-top--download">
                                        {{ App\Libraries\TomYumBranding::text('downloads_client_name') }}
                                    </div>
                                    @if (App\Libraries\TomYumBranding::downloadsLazerUrl() === null)
                                        {{ osu_trans('home.download.for_os', ['os' => $lazerPlatformName]) }}
                                    @endif
                                </div>
                                <div class="btn-osu-big__text-bottom btn-osu-big__text-bottom--download-version">
                                    {{ $version }}
                                    @if (isset($lazer['size']))
                                        ({{ App\Libraries\TomYumDownloads::size($lazer['size']) }})
                                    @endif
                                </div>
                            </div>
                            <span class="btn-osu-big__icon">
                                <span class="svg-icon svg-icon--download"></span>
                            </span>
                        </div>
                    </a>
                    @if (App\Libraries\TomYumBranding::downloadsLazerUrl() === null && $lazerPortable !== null)
                        {{-- TomYum: the same build without installing it. --}}
                        <div class="download-page__text download-page__text--download-portable">
                            No install needed?
                            <a href="{{ $lazerPortable['url'] }}">Download the portable zip</a>
                            ({{ App\Libraries\TomYumDownloads::size($lazerPortable['size'] ?? null) }})
                        </div>
                    @endif
                    @if (App\Libraries\TomYumBranding::downloadsLazerUrl() === null && str_starts_with($lazerPlatform, 'macos_'))
                        {{-- TomYum: the Mac app isn't signed with an Apple developer key, so macOS asks once before opening it. --}}
                        <div class="download-page__text download-page__text--download-portable">
                            Unzip it and move TumThai to Applications. The first time, right-click TumThai and choose Open
                            (or System Settings → Privacy &amp; Security → Open Anyway). It doesn't update itself yet:
                            download the new version from here when there is one.
                        </div>
                    @endif
                    @endif
                    {{-- TomYum: the launcher patches osu!(stable) itself, so the Somtum Patcher on its own is folded
                         away (set up by hand it may not work as expected). Windows and Linux only. --}}
                    @if ($showStable && ($lazerPlatform === 'windows_x64' || str_starts_with($lazerPlatform, 'linux_')))
                        <details class="download-page__manual">
                            <summary class="download-page__manual-title">
                                Patching osu!(stable) by hand?
                                <span class="fas fa-chevron-down download-page__manual-chevron"></span>
                            </summary>
                            @include('home._download_patcher')
                        </details>
                    @endif
                    @if (App\Libraries\TomYumBranding::downloadsLazerUrl() === null)
                        <div class="download-page__other-platforms">
                            @include('objects._basic_select_options', ['modifiers' => 'download', 'selectOptions' => $selectOptions])
                        </div>
                    @endif
                </div>
                @endif
            </div>
            {{-- TomYum: this server's patcher for today's osu!(stable), in place of ppy's installer. --}}
            {{-- TomYum: with the main card hidden (Admin panel > Branding), the patcher stands on its own. --}}
            @if ($showStable && !$showLazer)
            <div class="download-page__banner-tail">
                <div class="download-page__banner-content download-page__banner-content--tail">
                    @include('home._download_patcher')
                </div>
            </div>
            @endif
        </div>

        @if ($showLegacy)
            @include('home._download_legacy')
        @endif

        <div class="download-page__guide">
            <iframe
                class="download-page__video u-embed-wide"
                src="https://youtube.com/embed/videoseries?list={{ $GLOBALS['cfg']['osu']['urls']['youtube-tutorial-playlist'] }}"
            ></iframe>
            <div class="download-page__guide-content">
                <div class="download-page__steps">
                    <div class="download-page__step">
                        <span class="download-page__step-number">1</span>
                        <div class="download-page__text download-page__text--title">
                            {{ osu_trans("home.download.steps.download.title") }}
                        </div>
                        <div class="download-page__text download-page__text--description">
                            {{ osu_trans("home.download.steps.download.description") }}
                        </div>
                        {!! img2x([
                            'class' => 'download-page__example',
                            'src' => '/images/layout/download-step-1.jpg',
                        ]) !!}
                    </div>
                    <div class="download-page__step">
                        <span class="download-page__step-number">2</span>
                        <div class="download-page__text download-page__text--title">
                            {{ osu_trans('home.download.steps.register.title') }}
                        </div>
                        <div class="download-page__text download-page__text--description">
                            {{ osu_trans('home.download.steps.register.description') }}
                        </div>
                        {!! img2x([
                            'class' => 'download-page__example',
                            'src' => '/images/layout/download-step-2.png',
                        ]) !!}
                    </div>
                    <div class="download-page__step">
                        <span class="download-page__step-number">3</span>
                        <div class="download-page__text download-page__text--title">
                            {{ osu_trans("home.download.steps.beatmaps.title") }}
                        </div>
                        <div class="download-page__text download-page__text--description">
                            {!! osu_trans('home.download.steps.beatmaps.description._', [
                                'browse' => link_to(
                                    route('beatmapsets.index'),
                                    osu_trans('home.download.steps.beatmaps.description.browse')
                                )
                            ]) !!}
                        </div>
                        {!! img2x([
                            'class' => 'download-page__example',
                            'src' => '/images/layout/download-step-3.jpg',
                        ]) !!}
                    </div>
                </div>

                @if ($GLOBALS['cfg']['services']['enchant']['id'] !== null)
                    <div class="download-page__help">
                        {!! osu_trans('home.download.help._', [
                            'support_button' => link_to(
                                '#',
                                osu_trans('home.download.help.support_button'),
                                [
                                    'class' => 'js-enchant--show',
                                    'role' => 'button',
                                ],
                            ),
                            'help_forum_link' => link_to(
                                route('forum.forums.show', ['forum' => $GLOBALS['cfg']['osu']['forum']['help_forum_id']]),
                                osu_trans('home.download.help.help_forum_link')
                            )
                        ]) !!}
                    </div>

                    @include('objects._enchant')
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
