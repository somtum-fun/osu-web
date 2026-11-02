{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: osu!(stable) from 2008 to 2015, patched for this server (dl.blueskychan.dev/legacy). --}}
{{-- Folded away: the Somtum Launcher installs and updates these, so most players never need this list. --}}
<details class="download-page__legacy" id="legacy">
    <summary class="download-page__legacy-head">
        <div>
            <div class="download-page__text download-page__text--title">{{ $tyT('legacy.title') }}</div>
            <div class="download-page__text download-page__text--description">
                {{ $tyT('legacy.subtitle') }}
            </div>
        </div>
        <span class="download-page__legacy-toggle">
            <span class="download-page__legacy-toggle-show">{{ $tyT('legacy.show') }}</span>
            <span class="download-page__legacy-toggle-hide">{{ $tyT('legacy.hide') }}</span>
            <span class="fas fa-chevron-down download-page__legacy-chevron"></span>
        </span>
    </summary>
    <div class="download-page__legacy-body">
    <div class="download-page__text download-page__text--description">
        {!! $tyT('legacy.text') !!}
    </div>
    {{-- TomYum: no hook any more (unmodified builds can't pass the anti-cheat, pkms); missing builds are asked for. --}}
    <div class="download-page__text download-page__text--description">
        @if (osu_url('dev') !== null)
            {!! $tyT('legacy.ask_discord', [
                'discord' => link_to(osu_url('dev'), $tyT('legacy.discord')),
                'forum' => link_to(route('forum.forums.show', 4), $tyT('legacy.forum')),
            ]) !!}
        @else
            {!! $tyT('legacy.ask', ['forum' => link_to(route('forum.forums.show', 4), $tyT('legacy.forum'))]) !!}
        @endif
    </div>

    @if (count($legacyYears) === 0)
        <div class="download-page__text">
            {!! $tyT('legacy.unavailable', ['link' => link_to($dlUrl.'/legacy/', $tyT('legacy.file_host'))]) !!}
        </div>
    @endif

    @foreach ($legacyYears as $year => $builds)
        <details class="download-page__legacy-year" @if ($loop->first) open @endif>
            <summary class="download-page__legacy-year-title">
                {{ $year }}
                <span class="download-page__legacy-count">{{ count($builds) }}</span>
            </summary>
            <div class="download-page__legacy-builds">
                @foreach ($builds as $build)
                    <div class="download-page__legacy-build">
                        <div class="download-page__legacy-info">
                            <div class="download-page__legacy-name">{{ $build['build'] }}</div>
                            @if (present($build['note'] ?? null))
                                <div class="download-page__text download-page__text--description">{{ $build['note'] }}</div>
                            @endif
                        </div>
                        <a class="btn-osu-big btn-osu-big--rounded-thin" href="{{ $build['url'] }}">
                            {{ osu_trans('home.download.download') }}
                            <span class="download-page__legacy-size">{{ App\Libraries\TomYumDownloads::size($build['size'] ?? null) }}</span>
                        </a>
                    </div>
                @endforeach
            </div>
        </details>
    @endforeach

    <div class="download-page__text download-page__text--description download-page__legacy-footer">
        {!! $tyT('legacy.footer', [
            'titanic' => link_to('https://osu.titanic.sh/download/', 'Titanic'),
            'email' => link_to('mailto:'.App\Libraries\TomYumBranding::email('dmca'), App\Libraries\TomYumBranding::email('dmca')),
        ]) !!}
    </div>
    </div>
</details>
<script>
    // A #legacy link (the FAQ's, the launcher's) opens the list.
    if (location.hash === '#legacy') document.getElementById('legacy').open = true;
</script>
