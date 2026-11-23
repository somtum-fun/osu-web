{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
<form method="POST" action="{{ $action }}" class="tomyum-panel__row">
    @csrf
    <label class="input-container tomyum-panel__field">
        <span class="input-container__label">Name</span>
        <input class="input-text" type="text" name="name" required maxlength="50" value="{{ $mirror['name'] }}" placeholder="catboy.best">
    </label>
    <label class="input-container tomyum-panel__field">
        <span class="input-container__label">Address (https)</span>
        <input class="input-text" type="url" name="url" required value="{{ $mirror['url'] }}" placeholder="https://catboy.best/">
    </label>
    <label class="input-container tomyum-panel__field">
        <span class="input-container__label">Download path</span>
        <input class="input-text" type="text" name="download_path" value="{{ $mirror['download_path'] }}" placeholder="/d/{id}">
    </label>
    <label class="input-container tomyum-panel__field">
        <span class="input-container__label">Requests a minute</span>
        <input class="input-text" type="number" name="requests_per_minute" min="1" max="1200" value="{{ $mirror['requests_per_minute'] }}">
    </label>
    <label class="input-container tomyum-panel__field">
        <span class="input-container__label">Priority (lower first)</span>
        <input class="input-text" type="number" name="priority" min="0" max="1000" value="{{ $mirror['priority'] }}">
    </label>
    <label class="tomyum-panel__field"><input type="checkbox" name="lookups" value="1" @checked($mirror['lookups'])> Lookups</label>
    <label class="tomyum-panel__field"><input type="checkbox" name="downloads" value="1" @checked($mirror['downloads'])> Downloads</label>
    <label class="tomyum-panel__field"><input type="checkbox" name="enabled" value="1" @checked($mirror['enabled'])> On</label>
    <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">{{ $button }}</button></div>
</form>
