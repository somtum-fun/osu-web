{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: promote a new pkms build in one step (AnticheatToolsController@promote). --}}
<div class="tomyum-panel__card" id="pkms-promote">
    <h2><i class="fas fa-level-up-alt"></i>Promote a pkms build</h2>
    <p class="tomyum-panel__hint">
        For a new pkms release: the build is set to <strong>enabled</strong> with its published <code>pkms.dll</code> SHA-256, and every build enabled
        before it goes to <strong>grace</strong> for the days picked, then it's revoked. Players mid-session finish their map instead of being dropped.
        The registry as it was is saved first (<code>storage/app/pkms-versions-backups</code>). TomYum picks it up within a minute.
    </p>
    <form method="POST" action="{{ route('panel.admin.anticheat.promote') }}" class="tomyum-panel__row" style="gap: 10px; align-items: end; flex-wrap: wrap"
          onsubmit="return confirm('Enable this build and put the builds enabled now into grace?')">
        @csrf
        <label style="flex: 1 1 160px">
            build id
            <input class="input-text" type="text" name="build_id" placeholder="pkms/0.9.11" required pattern="pkms/[\w.+\-]{1,64}">
        </label>
        <label style="flex: 3 1 320px">
            pkms.dll SHA-256 <span class="tomyum-panel__hint" style="margin: 0">(optional; several separated by spaces)</span>
            <input class="input-text" type="text" name="dll_sha256" placeholder="64 hex characters" autocomplete="off" spellcheck="false">
        </label>
        <label style="flex: 0 0 120px">
            grace for
            <select class="input-text" name="grace_days">
                <option value="1" selected>1 day</option>
                <option value="2">2 days</option>
                <option value="3">3 days</option>
                <option value="7">7 days</option>
            </select>
        </label>
        <label style="flex: 1 1 160px">
            note
            <input class="input-text" type="text" name="note" placeholder="(optional)">
        </label>
        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-level-up-alt"></i>Promote</button>
    </form>
</div>
