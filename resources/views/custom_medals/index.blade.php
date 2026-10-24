{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: custom medals (Community → Medals): the community's medals, asking for one, and your requests. --}}
@php
    $status = session('custom_medal_status');
    $ok = session('custom_medal_ok');
    $statusPills = ['pending' => 'warn', 'approved' => 'good', 'rejected' => 'bad', 'disabled' => 'bad'];
    $oldRule = old('rule');
@endphp
@extends('master', [
    'titlePrepend' => 'Community medals',
    'canonicalUrl' => route('custom-medals.index'),
    'pageDescription' => 'Community custom medals on '.App\Libraries\TomYumBranding::name().'!: player-created medals earned on osu!(stable) and the '.App\Libraries\TomYumBranding::text('downloads_client_name').' client.',
])

@section('content')
    @component('layout._page_header_v4')
    @endcomponent
    <div class="osu-page osu-page--generic tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-award"></i>Community medals</h2>
            <p class="tomyum-panel__hint">
                Medals made by players. Anyone can ask for one: you pick its name, icon and what a play has to do to earn it,
                and the Medal Team checks it before it's out. It's earned by new plays (osu!stable or TumThai) that meet its rule,
                and shows on profiles under "{{ \App\Libraries\TomYumCustomMedals::GROUPING }}".
            </p>
            @if ($approved->isEmpty())
                <p class="tomyum-panel__empty">None yet. Be the first to ask for one!</p>
            @else
                <div class="tomyum-panel__scroll">
                    <table class="tomyum-panel__table">
                        <tr><th></th><th>Medal</th><th>Category</th><th>Made by</th><th>Earned by</th></tr>
                        @foreach ($approved as $medal)
                            <tr>
                                <td style="width: 64px"><img src="{{ \App\Libraries\TomYumCustomMedals::iconUrl($medal) }}" alt="" width="58" height="64" loading="lazy" style="object-fit: contain"></td>
                                <td>
                                    <strong>{{ $medal->name }}</strong>
                                    <div>{{ $medal->description }}</div>
                                    @if (present($medal->hint))
                                        <div class="tomyum-panel__hint"><i class="fas fa-lightbulb"></i> {{ $medal->hint }}</div>
                                    @endif
                                </td>
                                <td>{{ \App\Libraries\TomYumCustomMedals::CATEGORIES[$medal->category] ?? $medal->category }}</td>
                                <td class="tomyum-panel__nowrap">
                                    @if (isset($makers[$medal->user_id]))
                                        {!! link_to_user($makers[$medal->user_id]) !!}
                                    @endif
                                </td>
                                <td class="tomyum-panel__nowrap">{{ i18n_number_format($holders[$medal->achievement_id] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>

        <div class="tomyum-panel__card" id="request">
            <h2><i class="fas fa-plus"></i>Ask for a medal</h2>
            @if ($status !== null)
                <p class="tomyum-panel__pill tomyum-panel__pill--{{ $ok ? 'good' : 'bad' }}" style="display: block; padding: 8px 12px">{{ $status }}</p>
            @endif
            @if ($user === null)
                <p class="tomyum-panel__hint">Sign in to ask for a medal.</p>
            @else
                <p class="tomyum-panel__hint">
                    Up to {{ \App\Libraries\TomYumCustomMedals::PER_HOUR }} requests an hour and {{ \App\Libraries\TomYumCustomMedals::PER_DAY }} a day. Keep it fun and fair:
                    the Medal Team turns down medals that are impossible, too easy to farm, or not nice.
                </p>
                <form method="POST" action="{{ route('custom-medals.store') }}" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 12px">
                    @csrf
                    <div class="tomyum-panel__row">
                        <label class="input-container tomyum-panel__field">
                            <span class="input-container__label">Name (up to 40 characters)</span>
                            <input class="input-text" type="text" name="name" maxlength="40" required value="{{ old('name') }}">
                        </label>
                        <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 220px">
                            <span class="input-container__label">Category</span>
                            <select class="input-text" name="category">
                                @foreach (\App\Libraries\TomYumCustomMedals::CATEGORIES as $key => $label)
                                    <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <label class="input-container">
                        <span class="input-container__label">Description (what it's for; shown with the medal)</span>
                        <input class="input-text" type="text" name="description" maxlength="255" required value="{{ old('description') }}">
                    </label>
                    <label class="input-container">
                        <span class="input-container__label">Hint (optional: how to get it, for players who haven't yet)</span>
                        <input class="input-text" type="text" name="hint" maxlength="255" value="{{ old('hint') }}">
                    </label>
                    <label class="input-container">
                        <span class="input-container__label">Icon (PNG, JPG or WebP, up to 2 MB; it's fitted to 230×251)</span>
                        <input class="input-text" type="file" name="icon" accept="image/png,image/jpeg,image/webp" required>
                    </label>

                    <div>
                        <div class="input-container__label" style="position: static; padding: 0 0 6px">How it's earned</div>
                        @include('custom_medals._rule_builder', ['rule' => $oldRule, 'manual' => present($oldRule)])
                    </div>

                    <div>
                        <div class="input-container__label" style="position: static; padding: 0 0 6px">On maps that are (none ticked: ranked, approved, qualified or loved)</div>
                        <div style="display: flex; gap: 14px; flex-wrap: wrap">
                            @foreach (\App\Libraries\TomYumCustomMedals::MAP_STATUSES as $value => $label)
                                <label class="tomyum-panel__check">
                                    <input type="checkbox" name="statuses[]" value="{{ $value }}" @checked(in_array((string) $value, (array) old('statuses', []), true))> {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-paper-plane"></i> Send to the Medal Team</button>
                    </div>
                </form>
            @endif
        </div>

        @if ($user !== null && $mine->isNotEmpty())
            <div class="tomyum-panel__card" id="mine">
                <h2><i class="fas fa-list"></i>Your requests</h2>
                <div class="tomyum-panel__scroll">
                    <table class="tomyum-panel__table">
                        <tr><th></th><th>Medal</th><th>Status</th><th></th></tr>
                        @foreach ($mine as $medal)
                            <tr>
                                <td style="width: 64px"><img src="{{ \App\Libraries\TomYumCustomMedals::iconUrl($medal) }}" alt="" width="58" height="64" loading="lazy" style="object-fit: contain"></td>
                                <td>
                                    <strong>{{ $medal->name }}</strong>
                                    <div>{{ $medal->description }}</div>
                                    <code style="font-size: 11px; word-break: break-all">{{ $medal->rule }}</code>
                                </td>
                                <td>
                                    <span class="tomyum-panel__pill tomyum-panel__pill--{{ $statusPills[$medal->status] ?? 'info' }}">{{ \App\Libraries\TomYumCustomMedals::STATES[$medal->status] ?? $medal->status }}</span>
                                    @if (present($medal->decision_reason))
                                        <div class="tomyum-panel__hint">{{ $medal->decision_reason }}</div>
                                    @endif
                                </td>
                                <td class="tomyum-panel__nowrap">
                                    @if ($medal->status === 'pending')
                                        <form method="POST" action="{{ route('custom-medals.withdraw', ['medal' => $medal->id]) }}">
                                            @csrf
                                            <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Take back</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
