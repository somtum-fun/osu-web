{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', [
    'canonicalUrl' => route('download'),
    'pageDescription' => 'Download Somtum Launcher, the '.App\Libraries\TomYumBranding::text('downloads_client_name').' client (lazer), '.App\Libraries\TomYumBranding::text('downloads_patcher_name').' for osu!(stable), and patched 2008–2015 legacy clients for '.App\Libraries\TomYumBranding::name().'!',
])

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
                        Recommended. One app for {{ App\Libraries\TomYumBranding::text('downloads_client_name') }}, TumThai!Next, osu!(stable) and the 2008-2015 clients, and it keeps them up to date.
                        @if (isset($launcher['portable']['url']))
                            No install wanted? <a href="{{ $launcher['portable']['url'] }}">Get the portable version</a>
                            ({{ App\Libraries\TomYumDownloads::size($launcher['portable']['size'] ?? null) }}).
                        @endif
                    </div>
                    <div class="download-page__text download-page__text--download-portable">
                        Just {{ $lazerName }}?
                        <a href="{{ $lazerUrl }}">Download the installer</a>
                        @if (isset($lazer['size']))
                            ({{ App\Libraries\TomYumDownloads::size($lazer['size']) }})
                        @endif
                        @if ($lazerPortable !== null)
                            or <a href="{{ $lazerPortable['url'] }}">the portable zip</a>
                            ({{ App\Libraries\TomYumDownloads::size($lazerPortable['size'] ?? null) }})
                        @endif
                    </div>
                    @include('home._download_streams')
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
                        Recommended. One app for {{ App\Libraries\TomYumBranding::text('downloads_client_name') }}, TumThai!Next, osu!(stable) (through
                        <a href="https://github.com/NelloKudo/osu-winello">osu-winello</a>) and the 2008-2015 clients.
                        Download it, then run <code>python3 somtum-linux-{{ $launcherLinux['version'] ?? '' }} install</code>.
                    </div>
                    <div class="download-page__text download-page__text--download-portable">
                        Just {{ $lazerName }}?
                        <a href="{{ $lazerUrl }}">Download the AppImage</a>
                        @if (isset($lazer['size']))
                            ({{ App\Libraries\TomYumDownloads::size($lazer['size']) }})
                        @endif
                    </div>
                    @include('home._download_streams')
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
                                        {{ $lazerName }}
                                    </div>
                                    @if ($perPlatform)
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
                    @if ($perPlatform && $lazerPortable !== null)
                        {{-- TomYum: the same build without installing it. --}}
                        <div class="download-page__text download-page__text--download-portable">
                            No install needed?
                            <a href="{{ $lazerPortable['url'] }}">Download the portable zip</a>
                            @if (isset($lazerPortable['size']))
                                ({{ App\Libraries\TomYumDownloads::size($lazerPortable['size']) }})
                            @endif
                        </div>
                    @endif
                    @include('home._download_streams')
                    @if ($perPlatform && str_starts_with($lazerPlatform, 'macos_'))
                        {{-- TomYum: the Mac app isn't signed with an Apple developer key, so macOS asks once before opening it. --}}
                        <div class="download-page__text download-page__text--download-portable">
                            Unzip it and move {{ App\Libraries\TomYumBranding::text('downloads_client_name') }} to Applications. The first time, right-click {{ App\Libraries\TomYumBranding::text('downloads_client_name') }} and choose Open
                            (or System Settings → Privacy &amp; Security → Open Anyway).
                            @if (!$ownClient)
                                It doesn't update itself yet: download the new version from here when there is one.
                            @endif
                        </div>
                    @endif
                    @endif
                    {{-- TomYum: the launcher patches osu!(stable) itself, so the Somtum Patcher on its own is folded
                         away (set up by hand it may not work as expected). Windows and Linux only. --}}
                    @if ($showStable && ($lazerPlatform === 'windows_x64' || str_starts_with($lazerPlatform, 'linux_')))
                        <details class="download-page__manual">
                            <summary class="download-page__text download-page__manual-title">
                                Patching osu!(stable) by hand?
                                <span class="fas fa-chevron-down download-page__manual-chevron"></span>
                            </summary>
                            @include('home._download_patcher')
                        </details>
                    @endif
                    @if ($perPlatform)
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

        {{-- TomYum: our own three steps, in place of ppy's osu!academy video and their screenshots. --}}
        <div class="download-page__guide">
            <div class="download-page__guide-content">
                <div class="download-page__text download-page__text--title download-page__guide-title">
                    Playing in three steps
                </div>
                <div class="download-page__steps">
                    <div class="download-page__step">
                        <span class="download-page__step-number">1</span>
                        <div class="download-page__text download-page__text--title">
                            Install
                        </div>
                        <div class="download-page__text download-page__text--description">
                            Download the Somtum Launcher above and open it. It installs
                            {{ App\Libraries\TomYumBranding::text('downloads_client_name') }}, osu!(stable) and the old clients for you,
                            and keeps them up to date. On macOS or Android, download
                            {{ App\Libraries\TomYumBranding::text('downloads_client_name') }} instead.
                        </div>
                    </div>
                    <div class="download-page__step">
                        <span class="download-page__step-number">2</span>
                        <div class="download-page__text download-page__text--title">
                            Make an account
                        </div>
                        <div class="download-page__text download-page__text--description">
                            Sign up in the game, not on this website: start the game and choose to create an account.
                            Then sign in here with the same name and password.
                            <a href="{{ wiki_url('FAQ') }}#registration">More about accounts</a>
                        </div>
                    </div>
                    <div class="download-page__step">
                        <span class="download-page__step-number">3</span>
                        <div class="download-page__text download-page__text--title">
                            Get beatmaps
                        </div>
                        <div class="download-page__text download-page__text--description">
                            Download maps in the game, or <a href="{{ route('beatmapsets.index') }}">browse the beatmap listing</a>
                            here. Stuck? The <a href="{{ wiki_url('FAQ') }}">FAQ</a> and the
                            <a href="{{ wiki_url('FAQ') }}#errors">list of error messages</a> may help.
                        </div>
                    </div>
                </div>

                {{-- TomYum: our downloads aren't code-signed, so Windows and antiviruses may stop them. --}}
                <div class="download-page__help">
                    <span class="fas fa-shield-alt"></span>
                    <strong>Antivirus or Windows blocking a download?</strong>
                    Our downloads aren't code-signed (that would cost us upwards of 300€ a year), so Windows may say
                    "Windows protected your PC" (click <strong>More info</strong>, then <strong>Run anyway</strong>),
                    and antiviruses may block or remove the Launcher, the Patcher, <code>pkms.dll</code>,
                    {{ App\Libraries\TomYumBranding::text('downloads_client_name') }} or the old clients by mistake.
                    Add an exception (whitelist) for their folders, or turn real-time protection off just while you install.
                    Only for files from this page.
                    <a href="{{ wiki_url('Help_centre/Common_problems') }}#antivirus">How to add an exception</a>
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
