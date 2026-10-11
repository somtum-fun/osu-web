{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    TomYum: a custom medal's rule, built from choices (mode, conditions, mods, full combo) into the rule
    the game reads (bancho.py's condition language), or written by hand. The rule box is what's sent.
    $rule: the rule to start with; $manual: start in "write it myself".
--}}
@php
    $builderId = 'medal-builder-'.substr(md5((string) microtime(true).random_int(0, 99999)), 0, 8);
@endphp
<div class="js-medal-builder" id="{{ $builderId }}" style="display: flex; flex-direction: column; gap: 12px">
    <div class="js-medal-builder-choices" style="display: flex; flex-direction: column; gap: 12px">
        <label class="input-container input-container--select" style="max-width: 260px">
            <span class="input-container__label">Mode</span>
            <select class="input-text js-medal-mode">
                <option value="">Any mode</option>
                <option value="0">osu!</option>
                <option value="1">osu!taiko</option>
                <option value="2">osu!catch</option>
                <option value="3">osu!mania</option>
            </select>
        </label>

        <div>
            <div class="input-container__label" style="position: static; padding: 0 0 6px">The play has to</div>
            <div class="js-medal-conditions" style="display: flex; flex-direction: column; gap: 8px"></div>
            <button type="button" class="btn-osu-big btn-osu-big--rounded-thin js-medal-add" style="margin-top: 8px">
                <i class="fas fa-plus"></i> Add a condition
            </button>
        </div>

        <label class="tomyum-panel__check">
            <input type="checkbox" class="js-medal-fc"> Full combo
        </label>

        <div>
            <div class="input-container__label" style="position: static; padding: 0 0 6px">Mods</div>
            <div class="js-medal-mods" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 6px"></div>
        </div>
    </div>

    <label class="tomyum-panel__check">
        <input type="checkbox" class="js-medal-manual" name="manual_rule" value="1" @if ($manual ?? false) checked @endif>
        Write the rule myself
    </label>
    <label class="input-container">
        <span class="input-container__label">Rule (what the game checks)</span>
        <textarea class="input-text js-medal-rule" name="rule" rows="3" style="width: 100%; font-family: monospace" @if (!($manual ?? false)) readonly @endif>{{ $rule ?? '' }}</textarea>
    </label>
    <p class="tomyum-panel__hint" style="margin: 0">
        The rule is somtum.fun's medal language (the same as before the move): <code>score.acc</code>, <code>score.sr</code>,
        <code>score.mods</code>, <code>score.nmiss</code>, <code>score.max_combo</code>, <code>score.perfect</code>, <code>score.pp</code>,
        <code>score.grade</code> (D 2 … SS+HD 9), <code>score.bmap.id</code>, <code>score.bmap.set_id</code>, <code>score.bmap.creator</code>,
        <code>score.bmap.artist</code>, <code>score.bmap.title</code>, <code>score.bmap.bpm</code>, <code>score.bmap.ar</code> … and
        <code>mode_vn</code> (0 osu!, 1 taiko, 2 catch, 3 mania), joined with <code>and</code>, <code>or</code>, <code>not</code>.
        Example: <code>mode_vn == 0 and score.acc &gt;= 98 and score.mods &amp; 8</code> (osu!, 98% or more, with Hidden).
    </p>
</div>

<script>
    (() => {
        const root = document.getElementById(@json($builderId));
        const fields = [
            ['score.acc', 'Accuracy (%)', 'num'],
            ['score.sr', 'Star rating (with mods)', 'num'],
            ['score.max_combo', 'Max combo', 'num'],
            ['score.nmiss', 'Misses', 'num'],
            ['score.pp', 'pp', 'num'],
            ['score.score', 'Score', 'num'],
            ['score.grade', 'Grade', 'grade'],
            ['score.bmap.id', 'Beatmap id', 'num'],
            ['score.bmap.set_id', 'Beatmap set id', 'num'],
            ['score.bmap.creator', 'Mapper', 'text'],
            ['score.bmap.artist', 'Artist', 'text'],
            ['score.bmap.title', 'Title', 'text'],
            ['score.bmap.version', 'Difficulty name', 'text'],
            ['score.bmap.diff', 'Map stars (no mods)', 'num'],
            ['score.bmap.bpm', 'BPM', 'num'],
            ['score.bmap.ar', 'AR', 'num'],
            ['score.bmap.od', 'OD', 'num'],
            ['score.bmap.cs', 'CS', 'num'],
            ['score.bmap.hp', 'HP', 'num'],
            ['score.bmap.total_length', 'Length (seconds)', 'num'],
        ];
        const grades = [['9', 'SS (Hidden)'], ['8', 'SS'], ['7', 'S (Hidden)'], ['6', 'S'], ['5', 'A'], ['4', 'B'], ['3', 'C'], ['2', 'D']];
        const ops = {num: [['>=', 'at least'], ['<=', 'at most'], ['==', 'exactly'], ['!=', 'not'], ['>', 'more than'], ['<', 'less than']],
            text: [['==', 'is'], ['!=', 'isn\'t']], grade: [['>=', 'at least'], ['<=', 'at most'], ['==', 'exactly']]};
        const mods = [['NF', 1, 'No Fail'], ['EZ', 2, 'Easy'], ['HD', 8, 'Hidden'], ['HR', 16, 'Hard Rock'], ['SD', 32, 'Sudden Death'],
            ['DT', 64, 'Double Time (or NC)'], ['HT', 256, 'Half Time'], ['FL', 1024, 'Flashlight'], ['SO', 4096, 'Spun Out'],
            ['PF', 16384, 'Perfect'], ['RX', 128, 'Relax'], ['AP', 8192, 'Autopilot'], ['TD', 4, 'Touch Device']];

        const list = root.querySelector('.js-medal-conditions');
        const rule = root.querySelector('.js-medal-rule');
        const manual = root.querySelector('.js-medal-manual');
        const choices = root.querySelector('.js-medal-builder-choices');
        const option = (value, text) => {
            const o = document.createElement('option');
            o.value = value;
            o.textContent = text;
            return o;
        };
        const quote = (text) => '"' + text.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"';

        const modBox = root.querySelector('.js-medal-mods');
        for (const [acronym, bit, name] of mods) {
            const label = document.createElement('label');
            label.className = 'input-container input-container--select';
            label.innerHTML = '<span class="input-container__label"></span>';
            label.firstChild.textContent = `${acronym} · ${name}`;
            const select = document.createElement('select');
            select.className = 'input-text js-medal-mod';
            select.dataset.bit = bit;
            select.append(option('', 'Either'), option('need', 'Needed'), option('no', 'Not allowed'));
            label.append(select);
            modBox.append(label);
        }

        const addRow = () => {
            const row = document.createElement('div');
            row.className = 'js-medal-row';
            row.style.cssText = 'display: flex; gap: 8px; flex-wrap: wrap; align-items: center';
            const field = document.createElement('select');
            field.className = 'input-text js-medal-field';
            field.style.flex = '1 1 200px';
            for (const [expr, label] of fields) field.append(option(expr, label));
            const op = document.createElement('select');
            op.className = 'input-text js-medal-op';
            op.style.flex = '0 1 130px';
            const valueBox = document.createElement('span');
            valueBox.style.flex = '1 1 160px';
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn-osu-big btn-osu-big--rounded-thin';
            remove.title = 'Remove';
            remove.innerHTML = '<i class="fas fa-times"></i>';
            remove.addEventListener('click', () => { row.remove(); build(); });

            const refresh = () => {
                const type = fields.find((f) => f[0] === field.value)[2];
                op.replaceChildren(...ops[type].map(([v, t]) => option(v, t)));
                let input;
                if (type === 'grade') {
                    input = document.createElement('select');
                    for (const [v, t] of grades) input.append(option(v, t));
                } else {
                    input = document.createElement('input');
                    input.type = type === 'num' ? 'number' : 'text';
                    if (type === 'num') input.step = 'any';
                    input.placeholder = type === 'num' ? 'a number' : 'text';
                }
                input.className = 'input-text js-medal-value';
                input.style.width = '100%';
                input.addEventListener('input', build);
                valueBox.replaceChildren(input);
                build();
            };
            field.addEventListener('change', refresh);
            op.addEventListener('change', build);
            row.append(field, op, valueBox, remove);
            list.append(row);
            refresh();
        };

        const build = () => {
            if (manual.checked) return;
            const parts = [];
            const mode = root.querySelector('.js-medal-mode').value;
            if (mode !== '') parts.push(`mode_vn == ${mode}`);
            for (const row of list.querySelectorAll('.js-medal-row')) {
                const expr = row.querySelector('.js-medal-field').value;
                const type = fields.find((f) => f[0] === expr)[2];
                const op = row.querySelector('.js-medal-op').value;
                const raw = row.querySelector('.js-medal-value').value.trim();
                if (raw === '') continue;
                if (type === 'num' && !/^-?\d+(\.\d+)?$/.test(raw)) continue;
                parts.push(`${expr} ${op} ${type === 'text' ? quote(raw) : raw}`);
            }
            if (root.querySelector('.js-medal-fc').checked) parts.push('score.perfect');
            let need = 0, no = 0;
            for (const select of root.querySelectorAll('.js-medal-mod')) {
                if (select.value === 'need') need |= Number(select.dataset.bit);
                if (select.value === 'no') no |= Number(select.dataset.bit);
            }
            if (need !== 0) parts.push(`score.mods & ${need} == ${need}`);
            if (no !== 0) parts.push(`score.mods & ${no} == 0`);
            rule.value = parts.join(' and ');
        };

        root.querySelector('.js-medal-add').addEventListener('click', addRow);
        root.querySelector('.js-medal-mode').addEventListener('change', build);
        root.querySelector('.js-medal-fc').addEventListener('change', build);
        for (const select of root.querySelectorAll('.js-medal-mod')) select.addEventListener('change', build);
        const syncManual = () => {
            rule.readOnly = !manual.checked;
            choices.style.opacity = manual.checked ? '0.45' : '';
            choices.style.pointerEvents = manual.checked ? 'none' : '';
            if (!manual.checked) build();
        };
        manual.addEventListener('change', syncManual);
        syncManual();
        if (!manual.checked && rule.value === '') addRow();
    })();
</script>
