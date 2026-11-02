<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

return [
    'landing' => [
        'download' => "Let's connect!", // TomYum
        'online' => '<strong>:players</strong> currently online in <strong>:games</strong> games',
        'peak' => 'Peak, :count online users',
        'players' => '<strong>:count</strong> registered players',
        'title' => ':brand: free osu! private server with lazer and stable support', // TomYum
        'see_more_news' => 'see more news',

        'slogan' => [
            'main' => 'A free private server for osu!', // TomYum
            'sub' => 'lazer, stable and 2008–2015 clients, one leaderboard', // TomYum
        ],

        // TomYum: the "why play here" section under the hero.
        'about' => [
            'title' => 'Why play on :brand?',
            'lazer' => [
                'title' => 'Lazer support',
                'text' => 'Play with TumThai, our lazer client for Windows, Linux, macOS and Android. Multiplayer, spectating and replays all work, and it updates itself.',
            ],
            'stable' => [
                'title' => 'Stable and old clients',
                'text' => 'Play osu!(stable) through the Somtum Launcher or the Somtum Patcher, or pick one of 75 clients from 2008 to 2015. Every client shares one account and one leaderboard.',
            ],
            'modes' => [
                'title' => 'Relax, Autopilot and more',
                'text' => 'Relax and Autopilot with their own pp and rankings, custom rulesets, osu!dan courses, the daily challenge and medals the community asked for.',
            ],
            'fair' => [
                'title' => 'Free and fair',
                'text' => 'Free to play, always. Our anti-cheat checks every client, and replays are checked after you play, so everyone plays on the same terms.',
            ],
            'origin' => 'We started in Thailand as the country\'s second well-known osu! private server, and now we welcome players worldwide. Our community is mainly English-speaking, and our name comes from som tum, the Thai papaya salad.',
            'links' => [
                'why' => 'Why :brand?',
                'history' => 'Our history',
                'discord' => 'Join our Discord',
            ],
        ],
    ],

    'search' => [
        'advanced_link' => 'Advanced search',
        'button' => 'Search',
        'empty_result' => 'Nothing found!',
        'keyword_required' => 'A search keyword is required',
        'placeholder' => 'type to search',
        'title' => 'search',

        'artist_track' => [
            'more_simple' => 'See more featured artist track search results',
        ],
        'beatmapset' => [
            'login_required' => 'Sign in to search beatmaps',
            'more' => ':count more beatmap search results',
            'more_simple' => 'See more beatmap search results',
            'title' => 'Beatmaps',
        ],

        'forum_post' => [
            'all' => 'All forums',
            'link' => 'Search the forum',
            'login_required' => 'Sign in to search the forum',
            'more_simple' => 'See more forum search results',
            'title' => 'Forum',

            'label' => [
                'forum' => 'search in forums',
                'forum_children' => 'include subforums',
                'include_deleted' => 'include deleted posts',
                'topic_id' => 'topic #',
                'username' => 'author',
            ],
        ],

        'mode' => [
            'all' => 'all',
            'artist_track' => 'featured artist track',
            'beatmapset' => 'beatmap',
            'forum_post' => 'forum',
            'team' => 'team',
            'user' => 'player',
            'wiki_page' => 'wiki',
        ],

        'team' => [
            'login_required' => 'Sign in to search teams',
            'more_simple' => 'See more team search results',
        ],

        'user' => [
            'login_required' => 'Sign in to search users',
            'more' => ':count more player search results',
            'more_simple' => 'See more player search results',
            'more_hidden' => 'Player search is limited to :max players. Try refining search query.',
            'title' => 'Players',
        ],

        'wiki_page' => [
            'link' => 'Search the wiki',
            'more_simple' => 'See more wiki search results',
            'title' => 'Wiki',
        ],
    ],

    'download' => [
        'action_lazer_info' => 'check this page for more information',
        'download' => 'Download',
        'for_os' => 'for :os',
        'macos-fallback' => 'macOS users',
        'mirror' => 'mirror',
        'or' => 'or',
        'os_version_or_later' => ':os_version or later',
        'other_os' => 'other platforms',
        'quick_start_guide' => 'quick start guide',
        'stable_text' => 'if you\'re looking for the older one',
        'tagline_1' => 'let\'s get you',
        'tagline_2' => 'started!',
        'video-guide' => 'video guide',

        'help' => [
            '_' => 'if you have a problem starting the game or registering for an account, :help_forum_link or :support_button.',
            'help_forum_link' => 'check the help forum',
            'support_button' => 'contact support',
        ],

        'os' => [
            'windows' => 'for Windows',
            'macos' => 'for macOS',
            'linux' => 'for Linux',
        ],
        'steps' => [
            'register' => [
                'title' => 'get an account',
                'description' => 'follow the prompts when starting the game to sign in or make a new account',
            ],
            'download' => [
                'title' => 'install the game',
                'description' => 'click the button above to download the installer, then run it!',
            ],
            'beatmaps' => [
                'title' => 'get beatmaps',
                'description' => [
                    '_' => ':browse the vast library of user-created beatmaps and start playing!',
                    'browse' => 'browse',
                ],
            ],
        ],
    ],

    'user' => [
        'title' => 'dashboard',
        'news' => [
            'title' => 'News',
            'error' => 'Error loading news, try refreshing the page?...',
        ],
        'header' => [
            'stats' => [
                'friends' => 'Online Friends',
                'games' => 'Games',
                'online' => 'Online Users',
            ],
        ],
        'beatmaps' => [
            'daily_challenge' => 'Daily Challenge Beatmap',
            'new' => 'New Ranked Beatmaps',
            'popular' => 'Popular Beatmaps',
            'by_user' => 'by :user',
            'resets' => 'resets :ends',
        ],
        'buttons' => [
            'download' => "Let's connect!", // TomYum
            'support' => 'Support :brand',
            'store' => 'store',
        ],
        'livestream' => [
            'title' => 'Featured Livestream',
        ],
        'show' => [
            'admin' => [
                'page' => 'Open admin console',
            ],
        ],
    ],
];
