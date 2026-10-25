{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}

<ul class="game-mode">
    @foreach (App\Models\Beatmap::MODES as $ruleset => $_rulesetId)
        <li>
            <a
                class="{{ class_with_modifiers('game-mode-link', ['active' => $ruleset === $currentRuleset]) }}"
                href="{{ $urlFn($ruleset) }}"
            >
                <span
                    class="fal fa-extra-mode-{{ $ruleset }}"
                    title="{{ osu_trans("beatmaps.mode.{$ruleset}") }}"
                ></span>
                @if (isset($defaultRuleset) && $ruleset === $defaultRuleset)
                    <span
                      class="game-mode-link__icon"
                      title="{{ osu_trans('users.show.edit.default_playmode.is_default_tooltip') }}"
                    >
                      <span class="fas fa-star"></span>
                    </span>
                @endif
            </a>
        </li>
    @endforeach
    {{-- TomYum: the custom ruleset the page is for ($customRulesetCurrent: [name, url, icon]), as the selected mode. --}}
    @if (!empty($customRulesetCurrent))
        <li>
            <a class="game-mode-link game-mode-link--active" href="{{ $customRulesetCurrent[1] }}" title="{{ $customRulesetCurrent[0] }}">
                @if ($customRulesetCurrent[2] !== null)
                    <img src="{{ $customRulesetCurrent[2] }}" alt="{{ $customRulesetCurrent[0] }}" style="width: 1.25em; height: 1.25em; object-fit: contain; vertical-align: middle">
                @else
                    <span class="fas fa-puzzle-piece"></span>
                @endif
            </a>
        </li>
    @endif
    {{-- TomYum: custom lazer rulesets (a page that has them passes $customRulesetLinks: [name, url]). --}}
    @if (!empty($customRulesetLinks))
        <li>
            {{-- The site's own popup menu (simple-menu), opened by the "…" like the other modes' links. --}}
            <details class="game-mode-link" style="position: relative">
                <summary style="cursor: pointer; list-style: none" title="Custom lazer rulesets, played in TumThai">
                    <span class="fas fa-ellipsis-h"></span>
                </summary>
                <div class="simple-menu" style="position: absolute; top: calc(100% + 8px); right: 0">
                    @foreach ($customRulesetLinks as $link)
                        @php
                            [$name, $url] = $link;
                            $icon = $link[2] ?? null;
                        @endphp
                        <a class="simple-menu__item" href="{{ $url }}" style="display: flex; align-items: center; gap: 8px">
                            @if ($icon !== null)
                                <img src="{{ $icon }}" alt="" loading="lazy" style="width: 18px; height: 18px; object-fit: contain">
                            @else
                                <span class="fas fa-puzzle-piece" style="width: 18px; text-align: center"></span>
                            @endif
                            {{ $name }}
                        </a>
                    @endforeach
                </div>
            </details>
        </li>
    @endif
</ul>
