{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
<footer class="no-print {{ class_with_modifiers('footer', $modifiers ?? []) }}">
    @if ($withLinks ?? true)
        <div class="footer__row">
            @foreach (footer_legal_links() as $action => $link)
                <a class="footer__link" href="{{ $link }}">
                    {{ osu_trans("layout.footer.legal.{$action}") }}
                </a>
            @endforeach
        </div>
    @endif
    {{-- TomYum: not ppy's site (Admin panel > Branding). --}}
    @if (present($brandFooter = App\Libraries\TomYumBranding::text('footer')))
        {{-- TomYum: and how ppy or anyone else asks us to take something down. --}}
        <div class="footer__row footer__row--notice">
            <span>{{ $brandFooter }} <a class="footer__link--notice" href="{{ route('legal', ['locale' => app()->getLocale(), 'path' => 'Copyright']) }}">Takedown requests</a></span>
        </div>
    @endif

    <div class="js-sync-height--target" data-sync-height-id="permanent-fixed-footer"></div>
</footer>
