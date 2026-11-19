{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: one pkms scan set's needles (AnticheatToolsController@scanSet, @scanSetSave). --}}
@php
    $isNew = $set === null;
    // After a refused save, what was typed; otherwise the set's needles.
    if (old('needle_value') !== null) {
        $rows = [];
        foreach ((array) old('needle_value') as $i => $value) {
            $rows[] = [
                'tag' => old("needle_tag.{$i}", ''),
                'kind' => old("needle_kind.{$i}", 'text'),
                'value' => (string) $value,
                'enabled' => old("needle_enabled.{$i}") !== null,
            ];
        }
    } else {
        $rows = array_map(fn ($n) => [
            'tag' => $n['tag'] ?? '',
            'kind' => isset($n['hex']) ? 'hex' : 'text',
            'value' => $n['hex'] ?? $n['text'] ?? '',
            'enabled' => $n['enabled'] ?? true,
        ], $set['needles'] ?? []);
    }
    $rows = array_values(array_filter($rows, fn ($r) => $r['value'] !== '' || $r['tag'] !== ''));
    // Room for new needles.
    for ($blank = 0; $blank < 4 && count($rows) < \App\Libraries\TomYumPkmsScanSets::MAX_NEEDLES + 4; $blank++) {
        $rows[] = ['tag' => '', 'kind' => 'text', 'value' => '', 'enabled' => true];
    }
    $title = $isNew ? 'New scan set' : 'Scan set '.$set['name'];
@endphp
@extends('master', ['titlePrepend' => $title])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['url' => route('panel.admin.anticheat').'#pkms-scan-sets', 'title' => 'Anti-cheat'],
        ['title' => $title],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2>
                <i class="fas fa-fingerprint"></i>{{ $title }}
                @if (!$isNew)
                    <span class="tomyum-panel__badge">{{ $set['id'] }}</span>
                    @if ($set['enabled'] ?? false)
                        <span class="tomyum-panel__badge tomyum-panel__badge--success">SERVED</span>
                    @endif
                @endif
            </h2>
            <p class="tomyum-panel__hint">
                Each needle is a string pkms looks for in the game's memory: <strong>text</strong> (Latin-1, as the cheat has it) or <strong>hex</strong> bytes,
                1&ndash;{{ \App\Libraries\TomYumPkmsScanSets::MAX_NEEDLE_BYTES }} bytes each. At most {{ \App\Libraries\TomYumPkmsScanSets::MAX_NEEDLES }} enabled needles and
                {{ \App\Libraries\TomYumPkmsScanSets::MAX_WIRE_BYTES }} bytes in all (each needle counts its length + 1). The <strong>tag</strong> is what staff see when it hits;
                pkms never gets it. Untick <strong>on</strong> to stop scanning for one without losing it. Saving makes a new revision (a new id).
                A short or common string flags innocent players: test on dev first.
            </p>
            <form method="POST" action="{{ route('panel.admin.anticheat.scan-set-save') }}">
                @csrf
                @if ($isNew)
                    <div class="tomyum-panel__row" style="gap: 10px; align-items: end; margin-bottom: 10px">
                        <label style="flex: 0 1 260px">
                            name
                            <input class="input-text" type="text" name="name" value="{{ old('name') }}" required pattern="[A-Za-z0-9_\-]{1,24}" placeholder="main">
                        </label>
                    </div>
                @else
                    <input type="hidden" name="existing" value="{{ $set['name'] }}">
                @endif
                <table class="tomyum-panel__table">
                    <thead>
                        <tr>
                            <th style="width: 40px">on</th>
                            <th style="width: 220px">tag</th>
                            <th style="width: 90px">kind</th>
                            <th>needle</th>
                            <th style="width: 80px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $i => $row)
                            <tr>
                                <td style="text-align: center"><input type="checkbox" name="needle_enabled[{{ $i }}]" value="1" @if ($row['enabled']) checked @endif></td>
                                <td><input class="input-text" type="text" name="needle_tag[{{ $i }}]" value="{{ $row['tag'] }}" maxlength="64" placeholder="what it is"></td>
                                <td>
                                    <select class="input-text" name="needle_kind[{{ $i }}]">
                                        <option value="text" @if ($row['kind'] === 'text') selected @endif>text</option>
                                        <option value="hex" @if ($row['kind'] === 'hex') selected @endif>hex</option>
                                    </select>
                                </td>
                                <td><input class="input-text" type="text" name="needle_value[{{ $i }}]" value="{{ $row['value'] }}" autocomplete="off" spellcheck="false" style="font-family: monospace"></td>
                                <td style="text-align: center">
                                    @if ($row['value'] !== '')
                                        <label><input type="checkbox" name="needle_remove[{{ $i }}]" value="1"> remove</label>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="tomyum-panel__row" style="gap: 10px; align-items: end; margin-top: 10px">
                    <label style="flex: 1 1 300px">
                        note
                        <input class="input-text" type="text" name="note" value="{{ old('note', $set['note'] ?? '') }}" maxlength="200" placeholder="(optional)">
                    </label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save{{ $isNew ? '' : ' as a new revision' }}</button>
                    <a class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" href="{{ route('panel.admin.anticheat') }}#pkms-scan-sets">Back</a>
                </div>
                <p class="tomyum-panel__hint">Four empty rows are for new needles; save to get more.@if ($isNew) A new set starts off: serve it from the anti-cheat page.@endif</p>
            </form>
        </div>
    </div>
@endsection
