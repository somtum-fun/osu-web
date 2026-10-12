{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: custom medals for the Medal Team (and admins): requests to decide, and the medals that are out. --}}
@extends('master', ['titlePrepend' => 'Custom medals'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.index'), 'title' => 'Staff panel'],
        ['title' => 'Custom medals'],
    ]])
    @php
        $medalsLib = \App\Libraries\TomYumCustomMedals::class;
        $statusNames = $medalsLib::MAP_STATUSES;
    @endphp
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-award"></i>Custom medals</h2>
            <p class="tomyum-panel__hint">
                Players ask for medals on <a href="{{ route('custom-medals.index') }}">Community → Medals</a>. Check the icon, the text
                and the rule (what a play has to do; you can fix it before approving), then approve or reject with a reason the
                player sees. An approved medal is earned by <strong>new</strong> plays only, on ranked, approved, qualified or loved
                maps unless it says otherwise. Taking one out hides it from profiles and stops new unlocks; who has it keeps it.
            </p>
            <div class="tomyum-panel__row" style="gap: 8px">
                @foreach ($medalsLib::STATES as $key => $label)
                    <a class="btn-osu-big btn-osu-big--rounded-thin {{ $state === $key ? '' : 'btn-osu-big--rounded-thin-gray' }}"
                       href="{{ route('panel.medals.index', ['state' => $key]) }}">
                        {{ $label }} ({{ $counts[$key] ?? 0 }})
                    </a>
                @endforeach
            </div>
        </div>

        @if (count($medals->items()) === 0)
            <div class="tomyum-panel__card"><p class="tomyum-panel__empty">Nothing here.</p></div>
        @endif

        @foreach ($medals as $medal)
            @php
                $allowed = $medal->allowed_statuses === null
                    ? 'ranked, approved, qualified or loved'
                    : implode(', ', array_map(fn ($s) => strtolower($statusNames[(int) $s] ?? $s), explode(',', $medal->allowed_statuses)));
            @endphp
            <div class="tomyum-panel__card" id="medal-{{ $medal->id }}">
                <div style="display: flex; gap: 16px; align-items: flex-start; flex-wrap: wrap">
                    <img src="{{ $medalsLib::iconUrl($medal) }}" alt="" width="115" height="126" style="object-fit: contain; background: rgba(0,0,0,.2); border-radius: 8px">
                    <div style="flex: 1 1 320px">
                        <h2 style="margin-top: 0">{{ $medal->name }}</h2>
                        <p>{{ $medal->description }}</p>
                        @if (present($medal->hint))
                            <p class="tomyum-panel__hint"><i class="fas fa-lightbulb"></i> {{ $medal->hint }}</p>
                        @endif
                        <p class="tomyum-panel__hint">
                            {{ $medalsLib::CATEGORIES[$medal->category] ?? $medal->category }}
                            · on {{ $allowed }} maps
                            · asked for by {!! isset($people[$medal->user_id]) ? link_to_user($people[$medal->user_id]) : 'nobody (imported)' !!}
                            {{ $medal->created_at !== null ? '· '.\Carbon\Carbon::parse($medal->created_at)->diffForHumans() : '' }}
                            @if ($medal->decided_by !== null)
                                · decided by {!! isset($people[$medal->decided_by]) ? link_to_user($people[$medal->decided_by]) : '#'.$medal->decided_by !!}
                            @endif
                            @if ($medal->achievement_id !== null)
                                · medal #{{ $medal->achievement_id }}, earned by {{ i18n_number_format($holders[$medal->achievement_id] ?? 0) }}
                            @endif
                        </p>
                        @if (present($medal->decision_reason))
                            <p class="tomyum-panel__hint">Reason: {{ $medal->decision_reason }}</p>
                        @endif
                        <p style="margin-bottom: 0"><code style="word-break: break-all">{{ $medal->rule }}</code></p>
                    </div>
                </div>

                <details style="margin-top: 12px">
                    <summary style="cursor: pointer">Edit</summary>
                    <form method="POST" action="{{ route('panel.medals.update', ['medal' => $medal->id]) }}" style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px">
                        @csrf
                        <input type="hidden" name="return_state" value="{{ $state }}">
                        <div class="tomyum-panel__row">
                            <label class="input-container tomyum-panel__field">
                                <span class="input-container__label">Name</span>
                                <input class="input-text" type="text" name="name" maxlength="40" value="{{ $medal->name }}">
                            </label>
                            <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 200px">
                                <span class="input-container__label">Category</span>
                                <select class="input-text" name="category">
                                    @foreach ($medalsLib::CATEGORIES as $key => $label)
                                        <option value="{{ $key }}" @selected($medal->category === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <label class="input-container">
                            <span class="input-container__label">Description</span>
                            <input class="input-text" type="text" name="description" maxlength="255" value="{{ $medal->description }}">
                        </label>
                        <label class="input-container">
                            <span class="input-container__label">Hint</span>
                            <input class="input-text" type="text" name="hint" maxlength="255" value="{{ $medal->hint }}">
                        </label>
                        <label class="input-container">
                            <span class="input-container__label">Rule</span>
                            <textarea class="input-text" name="rule" rows="3" style="width: 100%; font-family: monospace">{{ $medal->rule }}</textarea>
                        </label>
                        <div style="display: flex; gap: 14px; flex-wrap: wrap">
                            @php $ticked = $medal->allowed_statuses === null ? [] : explode(',', $medal->allowed_statuses); @endphp
                            @foreach ($statusNames as $value => $label)
                                <label class="tomyum-panel__check">
                                    <input type="checkbox" name="statuses[]" value="{{ $value }}" @checked(in_array((string) $value, $ticked, true))> {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Save</button></div>
                    </form>
                </details>

                <div class="tomyum-panel__row" style="margin-top: 12px; gap: 10px; align-items: flex-end">
                    @if ($medal->status !== 'approved')
                        <form method="POST" action="{{ route('panel.medals.approve', ['medal' => $medal->id]) }}">
                            @csrf
                            <input type="hidden" name="return_state" value="{{ $state }}">
                            <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">
                                <i class="fas fa-check"></i> {{ $medal->status === 'pending' ? 'Approve' : 'Put it out' }}
                            </button>
                        </form>
                    @endif
                    @if ($medal->status === 'pending' || $medal->status === 'approved')
                        <form method="POST" action="{{ route($medal->status === 'pending' ? 'panel.medals.reject' : 'panel.medals.disable', ['medal' => $medal->id]) }}"
                              class="tomyum-panel__row" style="gap: 8px; align-items: flex-end; flex: 1 1 320px">
                            @csrf
                            <input type="hidden" name="return_state" value="{{ $state }}">
                            <label class="input-container tomyum-panel__field">
                                <span class="input-container__label">Why (the player sees it)</span>
                                <input class="input-text" type="text" name="reason" maxlength="255">
                            </label>
                            <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">
                                <i class="fas fa-times"></i> {{ $medal->status === 'pending' ? 'Reject' : 'Take out' }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach

        @include('objects._pagination_v2', ['object' => $medals])
    </div>
@endsection
