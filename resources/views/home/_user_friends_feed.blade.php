{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: friends' best plays of the last week (App\Libraries\TomYumFriendsFeed). --}}
<div class="user-home__friends-feed">
    <h2 class="user-home__left-title">Friends activity</h2>
    <div class="friends-feed">
        @foreach ($friendsFeed as $item)
            @php
                $score = $item['score'];
                $beatmap = $score->beatmap;
                // (a Relax/Autopilot play's page redirects to its own)
                $scoreUrl = route('scores.show', ['score' => $score->getKey()]);
            @endphp
            <a class="friends-feed__item" href="{{ $scoreUrl }}">
                <span class="friends-feed__avatar" style="background-image: url('{{ $score->user->user_avatar }}');"></span>
                <span class="friends-feed__main">
                    <span class="friends-feed__line">
                        <strong>{{ $score->user->username }}</strong>
                        @if ($item['first'])
                            <span class="friends-feed__tag friends-feed__tag--first">🥇 #1</span>
                        @endif
                        @if ($item['variant'] !== null)
                            <span class="friends-feed__tag">{{ $item['variant'] }}</span>
                        @endif
                    </span>
                    <span class="friends-feed__map u-ellipsis-overflow">
                        {{ $beatmap->beatmapset->getDisplayArtist(auth()->user()) }} - {{ $beatmap->beatmapset->getDisplayTitle(auth()->user()) }} [{{ $beatmap->version }}]
                        @if ($item['mods'] !== [])
                            +{{ implode('', $item['mods']) }}
                        @endif
                    </span>
                </span>
                <span class="friends-feed__numbers">
                    <span class="friends-feed__pp">{{ i18n_number_format(round($score->pp)) }}pp</span>
                    <span class="friends-feed__time">{!! timeago($score->ended_at) !!}</span>
                </span>
            </a>
        @endforeach
    </div>
</div>
