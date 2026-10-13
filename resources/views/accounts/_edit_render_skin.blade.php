{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: the o!rdr skin the player's replay renders use (score pages' Render replay, and the Discord bot's; /renderskin there sets the same). --}}
@php
    $renderSkin = App\Http\Controllers\Account\RenderSkinController::status(Auth::user());
    $renderSkinStatus = session('render_skin_status');
@endphp
@if ($renderSkin !== null && count($renderSkin['skins']) > 0)
    <div class="account-edit" id="render-skin">
        <div class="account-edit__section">
            <h2 class="account-edit__section-title">Replay renders</h2>
        </div>

        <div class="account-edit__input-groups">
            <div class="account-edit__input-group">
                <div class="account-edit-entry">
                    <div class="account-edit-entry__label account-edit-entry__label--top-pinned">Skin</div>
                    <div class="account-edit-entry__group">
                        @if ($renderSkinStatus !== null)
                            <p style="color: hsl(var({{ $renderSkinStatus['type'] === 'success' ? '--hsl-lime-1' : '--hsl-red-1' }}))">{{ $renderSkinStatus['message'] }}</p>
                        @endif
                        <p>
                            The skin your replays are rendered with (Render replay on a score's page, or the Discord bot's 🎬 Render and /render).
                            Renders are made by <a href="https://ordr.issou.best/skins" rel="nofollow noopener" target="_blank">o!rdr</a>, with its skins.
                        </p>
                        <form method="POST" action="{{ route('account.render-skin.update') }}" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; max-width: 560px">
                            @csrf
                            @method('PUT')
                            <div class="form-select" style="flex: 1; min-width: 220px">
                            <select class="form-select__input" name="skin" id="render-skin-select">
                                <option value="" data-preview="">Server default</option>
                                @foreach ($renderSkin['skins'] as $skin)
                                    <option
                                        value="{{ $skin['skin'] }}"
                                        data-preview="{{ $skin['preview'] ?? '' }}"
                                        @if ($renderSkin['current']['picked'] && $renderSkin['current']['skin'] === $skin['skin']) selected @endif
                                    >{{ $skin['name'] !== '' ? $skin['name'] : $skin['skin'] }}</option>
                                @endforeach
                            </select>
                            </div>
                            <button class="btn-osu-big btn-osu-big--account-edit" type="submit">
                                <span class="btn-osu-big__content">
                                    <span class="btn-osu-big__left">Save</span>
                                    <span class="btn-osu-big__icon"><i class="fas fa-paint-brush"></i></span>
                                </span>
                            </button>
                        </form>
                        <img id="render-skin-preview" alt="" style="display: none; margin-top: 12px; max-width: 360px; border-radius: 6px">
                        <script>
                            (function () {
                                const select = document.getElementById('render-skin-select');
                                const preview = document.getElementById('render-skin-preview');
                                const show = function () {
                                    const url = select.selectedOptions[0]?.dataset.preview ?? '';
                                    preview.style.display = url === '' ? 'none' : '';
                                    preview.src = url;
                                };
                                select.addEventListener('change', show);
                                show();
                            })();
                        </script>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
