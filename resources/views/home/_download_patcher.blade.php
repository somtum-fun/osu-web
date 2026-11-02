{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: the Somtum Patcher for today's osu!(stable), in place of ppy's installer. --}}
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
