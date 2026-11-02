{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    // TomYum: the page's text is in home.download.tomyum (en and th). Lines with HTML are printed raw, so the
    // names going into them are escaped here; $tyT is used by the partials too.
    $tyClientName = App\Libraries\TomYumBranding::text('downloads_client_name');
    $tyPatcherName = App\Libraries\TomYumBranding::text('downloads_patcher_name');
    $tyT = fn (string $key, array $replace = []) => osu_trans("home.download.tomyum.{$key}", $replace + [
        'brand' => e(App\Libraries\TomYumBranding::name()),
        'client' => e($tyClientName),
        'patcher' => e($tyPatcherName),
    ]);
@endphp
@extends('master', [
    'canonicalUrl' => route('download'),
    'titleOverride' => osu_trans('home.download.tomyum.title', ['client' => $tyClientName]),
    'pageDescription' => osu_trans('home.download.tomyum.description', [
        'client' => $tyClientName,
        'patcher' => $tyPatcherName,
        'brand' => App\Libraries\TomYumBranding::name(),
    ]),
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
                    {{-- TomYum: the page's heading (h1) for search engines; it looks the same. --}}
                    <h1 class="download-page__tagline">
                        <span class="download-page__tagline-1">{{ osu_trans('home.download.tagline_1') }}</span>
                        <span class="download-page__tagline-2">{{ osu_trans('home.download.tagline_2') }}</span>
                    </h1>
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
                        {!! $tyT('launcher.recommended') !!}
                        @if (isset($launcher['portable']['url']))
                            {!! $tyT('launcher.portable', [
                                'link' => link_to($launcher['portable']['url'], $tyT('launcher.portable_link')),
                                'size' => e(App\Libraries\TomYumDownloads::size($launcher['portable']['size'] ?? null)),
                            ]) !!}
                        @endif
                    </div>
                    <div class="download-page__text download-page__text--download-portable">
                        {!! $tyT('lazer.just', ['client' => e($lazerName)]) !!}
                        <a href="{{ $lazerUrl }}">{{ $tyT('lazer.installer') }}</a>
                        @if (isset($lazer['size']))
                            ({{ App\Libraries\TomYumDownloads::size($lazer['size']) }})
                        @endif
                        @if ($lazerPortable !== null)
                            {!! $tyT('lazer.or_zip', ['link' => link_to($lazerPortable['url'], $tyT('lazer.zip'))]) !!}
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
                        {!! $tyT('launcher.recommended_linux', [
                            'winello' => link_to('https://github.com/NelloKudo/osu-winello', 'osu-winello'),
                            'command' => '<code>'.e('python3 somtum-linux-'.($launcherLinux['version'] ?? '').' install').'</code>',
                        ]) !!}
                    </div>
                    <div class="download-page__text download-page__text--download-portable">
                        {!! $tyT('lazer.just', ['client' => e($lazerName)]) !!}
                        <a href="{{ $lazerUrl }}">{{ $tyT('lazer.appimage') }}</a>
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
                            {{ $tyT('lazer.no_install') }}
                            <a href="{{ $lazerPortable['url'] }}">{{ $tyT('lazer.zip_download') }}</a>
                            @if (isset($lazerPortable['size']))
                                ({{ App\Libraries\TomYumDownloads::size($lazerPortable['size']) }})
                            @endif
                        </div>
                    @endif
                    @include('home._download_streams')
                    @if ($perPlatform && str_starts_with($lazerPlatform, 'macos_'))
                        {{-- TomYum: the Mac app isn't signed with an Apple developer key, so macOS asks once before opening it. --}}
                        <div class="download-page__text download-page__text--download-portable">
                            {!! $tyT('lazer.macos') !!}
                            @if (!$ownClient)
                                {{ $tyT('lazer.macos_no_update') }}
                            @endif
                        </div>
                    @endif
                    @endif
                    {{-- TomYum: the launcher patches osu!(stable) itself, so the Somtum Patcher on its own is folded
                         away. Windows and Linux only. --}}
                    @if ($showStable && ($lazerPlatform === 'windows_x64' || str_starts_with($lazerPlatform, 'linux_')))
                        <details class="download-page__manual">
                            <summary class="download-page__text download-page__manual-title">
                                {{ $tyT('patcher.manual') }}
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
                    {{ $tyT('steps.title') }}
                </div>
                <div class="download-page__steps">
                    <div class="download-page__step">
                        <span class="download-page__step-number">1</span>
                        <div class="download-page__text download-page__text--title">
                            {{ $tyT('steps.install') }}
                        </div>
                        <div class="download-page__text download-page__text--description">
                            {!! $tyT('steps.install_text') !!}
                        </div>
                    </div>
                    <div class="download-page__step">
                        <span class="download-page__step-number">2</span>
                        <div class="download-page__text download-page__text--title">
                            {{ $tyT('steps.account') }}
                        </div>
                        <div class="download-page__text download-page__text--description">
                            {!! $tyT('steps.account_text', ['link' => link_to(wiki_url('FAQ').'#registration', $tyT('steps.account_link'))]) !!}
                        </div>
                    </div>
                    <div class="download-page__step">
                        <span class="download-page__step-number">3</span>
                        <div class="download-page__text download-page__text--title">
                            {{ $tyT('steps.beatmaps') }}
                        </div>
                        <div class="download-page__text download-page__text--description">
                            {!! $tyT('steps.beatmaps_text', [
                                'listing' => link_to(route('beatmapsets.index'), $tyT('steps.listing')),
                                'faq' => link_to(wiki_url('FAQ'), $tyT('steps.faq')),
                                'errors' => link_to(wiki_url('FAQ').'#errors', $tyT('steps.errors')),
                            ]) !!}
                        </div>
                    </div>
                </div>

                {{-- TomYum: our downloads aren't code-signed, so Windows and antiviruses may stop them. --}}
                <div class="download-page__help">
                    <span class="fas fa-shield-alt"></span>
                    <strong>{{ $tyT('antivirus.title') }}</strong>
                    {!! $tyT('antivirus.text') !!}
                    <a href="{{ wiki_url('Help_centre/Common_problems') }}#antivirus">{{ $tyT('antivirus.link') }}</a>
                </div>

                {{-- TomYum: Smart App Control blocks unsigned apps outright, and exceptions don't get past it. --}}
                <div class="download-page__help">
                    <span class="fas fa-shield-alt"></span>
                    <strong>{{ $tyT('smart_app_control.title') }}</strong>
                    {!! $tyT('smart_app_control.text') !!}
                    <a href="{{ wiki_url('Help_centre/Common_problems') }}#smart-app-control">{{ $tyT('smart_app_control.link') }}</a>
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
