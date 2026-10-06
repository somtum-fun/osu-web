{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    TomYum: an osu!dan course's fields, for making one ($course null) or changing one. A form that
    wasn't right comes back with what was typed (old()).
--}}
@php
    use App\Libraries\TomYumDan;

    $value = fn (string $field, $default) => old($field, $course === null ? $default : $course->{$field});
    $checked = fn (string $field, bool $default) => old('_sent') !== null ? old($field) !== null : ($course === null ? $default : (bool) $course->{$field});
    $mods = old('_sent') !== null
        ? array_sum(array_map('intval', array_unique((array) old('mods', []))))
        : ($course === null ? TomYumDan::DEFAULT_MODS : (int) $course->allowed_mods);
    $maps = old('maps', $course === null ? '' : implode("\n", array_map(fn ($map) => $map->beatmap_id, $course->maps)));
@endphp
<input type="hidden" name="_sent" value="1">
{{-- TomYum: the fields on a grid (bem/panel-dan.less): the same height, the same room between the rows. --}}
<div class="panel-dan__form">
    <label class="input-container panel-dan__name">
        <span class="input-container__label">Name (e.g. 7th Dan, Intro-1st)</span>
        <input class="input-text" type="text" name="name" maxlength="100" required value="{{ $value('name', '') }}">
    </label>
    <label class="input-container">
        <span class="input-container__label">Group (optional)</span>
        <input class="input-text" type="text" name="category" maxlength="32" placeholder="reform" value="{{ $value('category', '') }}">
    </label>
    <label class="input-container">
        <span class="input-container__label">Keys</span>
        <input class="input-text" type="number" name="keys" min="1" max="18" required value="{{ $value('keys', 4) }}">
    </label>
    <label class="input-container">
        <span class="input-container__label">Order</span>
        <input class="input-text" type="number" name="sort_order" min="-100000" max="100000" value="{{ $value('sort_order', 0) }}">
    </label>
    <label class="input-container">
        <span class="input-container__label">Accuracy (%)</span>
        <input class="input-text" type="number" name="min_accuracy" min="0" max="100" step="0.01" required value="{{ $value('min_accuracy', 96) }}">
    </label>
    <p class="tomyum-panel__hint panel-dan__wide">
        A higher order is a higher dan among the courses with the same keys: a player's highest clear is the one with the highest order.
        Intro courses usually need 95%, the others 96%.
    </p>
    <label class="input-container panel-dan__wide">
        <span class="input-container__label">Maps: links or ids, one per line</span>
        <textarea class="input-text" name="maps" rows="3" required placeholder="https://osu.example.com/beatmapsets/10000000#mania/10000001">{{ $maps }}</textarea>
    </label>
    <p class="tomyum-panel__hint panel-dan__wide">
        The course's beatmaps (difficulties), in the order they're played. Each map must be on the site already (import or upload its set
        in the BN panel) and be an osu!mania map with this many keys. Set the sets to Loved.
    </p>
    <label class="input-container panel-dan__wide">
        <span class="input-container__label">Description (optional, on the dan page)</span>
        <input class="input-text" type="text" name="description" maxlength="500" value="{{ $value('description', '') }}">
    </label>
    <div class="panel-dan__checks panel-dan__wide">
        <span class="panel-dan__checks-label">Mods allowed:</span>
        @foreach (TomYumDan::MODS as [$bit, $acronym, $modName])
            <label class="tomyum-panel__check" title="{{ $modName }}">
                <input type="checkbox" name="mods[]" value="{{ $bit }}" @checked(($mods & $bit) !== 0)> {{ $acronym }}
            </label>
        @endforeach
    </div>
    <p class="tomyum-panel__hint panel-dan__wide">DanReform allows MR, HD, FI, FL, HR, DT and NC. With NC allowed, the DT that comes with it is fine.</p>
    <div class="panel-dan__checks panel-dan__wide">
        <label class="tomyum-panel__check"><input type="checkbox" name="is_intro" value="1" @checked($checked('is_intro', false))> Intro course</label>
        <label class="tomyum-panel__check"><input type="checkbox" name="no_pause" value="1" @checked($checked('no_pause', true))> No pausing</label>
        <label class="tomyum-panel__check"><input type="checkbox" name="active" value="1" @checked($checked('active', true))> Active (shown and playable; off keeps the clears)</label>
    </div>
</div>
