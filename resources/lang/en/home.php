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
            'main' => 'Yet another osu! private server', // TomYum
            'sub' => 'lazer, stable and 2008–2015 clients, one leaderboard', // TomYum
        ],

        // TomYum: the "why play here" section under the hero.
        'about' => [
            'title' => 'Why play on :brand?',
            'lazer' => [
                'title' => 'Lazer support',
                'text' => 'TumThai, our lazer client for Windows, Linux, macOS and Android, with multiplayer, spectating and replays.',
            ],
            'stable' => [
                'title' => 'Stable and old clients',
                'text' => 'osu!(stable) through the Somtum Launcher, plus 75 clients from 2008 to 2015. One account, one leaderboard.',
            ],
            'modes' => [
                'title' => 'Relax, Autopilot and more',
                'text' => 'Relax and Autopilot with their own pp and rankings, plus custom rulesets, osu!dan and the daily challenge.',
            ],
            'fair' => [
                'title' => 'Free and fair',
                'text' => 'Free to play, always. Anti-cheat on every client and replay checks keep the rankings fair.',
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

        // TomYum: the download page's own text (resources/views/home/download.blade.php and its partials).
        // Lines with HTML are printed raw: :client, :patcher and :brand come in escaped.
        'tomyum' => [
            'title' => 'Download :client, the Somtum Launcher and old osu! clients',
            'description' => 'Download Somtum Launcher, the :client client (lazer), :patcher for osu!(stable), and patched 2008–2015 legacy clients for :brand!',

            'launcher' => [
                'recommended' => 'Recommended. One app for :client, TumThai!Next, osu!(stable) and the 2008-2015 clients, and it keeps them up to date.',
                'recommended_linux' => 'Recommended. One app for :client, TumThai!Next, osu!(stable) (through :winello) and the 2008-2015 clients. Download it, then run :command.',
                'portable' => 'No install wanted? :link (:size).',
                'portable_link' => 'Get the portable version',
            ],

            'lazer' => [
                'just' => 'Just :client?',
                'installer' => 'Download the installer',
                'appimage' => 'Download the AppImage',
                'or_zip' => 'or :link',
                'zip' => 'the portable zip',
                'no_install' => 'No install needed?',
                'zip_download' => 'Download the portable zip',
                'macos' => 'Unzip it and move :client to Applications. The first time, right-click :client and choose Open (or System Settings → Privacy &amp; Security → Open Anyway).',
                'macos_no_update' => 'It doesn\'t update itself yet: download the new version from here when there is one.',
            ],

            'streams' => [
                'label' => 'Stream:',
                'android' => '<strong>TumThai!Next is recommended on Android</strong>: it runs smoother and with less delay on phones.',
                'other' => '<strong>TumThai!Next</strong> is our faster stream: it runs smoother on low-end PCs, and has the newest performance options.',
                'same' => 'Same account, beatmaps and scores.',
                'switch_windows' => 'Switch streams any time in the game (Settings → General → Updates) or the Somtum Launcher.',
                'switch_android' => 'Want the other one? Download it here.',
                'switch_other' => 'Switch streams any time in the Somtum Launcher, or download the other one here.',
            ],

            'patcher' => [
                'manual' => 'Rather not use the launcher for osu!(stable)?',
                'text' => 'Start your own osu!(stable) with the :patcher to play on :brand.',
                'launcher_easier' => 'The Somtum Launcher does the same and keeps it up to date, so it\'s the easier way.',
                'platforms' => 'Windows and Linux (no macOS)',
            ],

            'legacy' => [
                'title' => 'Legacy osu! clients (2008-2015)',
                'subtitle' => 'The Somtum Launcher installs and updates these for you. Download one yourself here.',
                'show' => 'Show',
                'hide' => 'Hide',
                'text' => 'Old osu!(stable) builds, rebuilt to play on :brand: today\'s beatmaps load, Relax and Autopilot scores submit, and the updater stays off. Unzip one anywhere and run osu!.exe. They need Windows and .NET Framework 3.5 (a few 2015 builds use .NET Framework 4).',
                'ask_discord' => 'Can\'t find the build you want? Ask for it in our :discord or on the :forum.',
                'ask' => 'Can\'t find the build you want? Ask for it on the :forum.',
                'discord' => 'Discord server',
                'forum' => 'forum',
                'unavailable' => 'The client list isn\'t available right now. You can find every build on :link.',
                'file_host' => 'the file host',
                'footer' => 'The original builds come from :titanic, as do most of the notes. osu! is a registered trademark of ppy Pty Ltd. These clients are unofficial and unsupported by ppy, and are offered as is. To have something taken down, email :email.',
            ],

            'steps' => [
                'title' => 'Playing in three steps',
                'install' => 'Install',
                'install_text' => 'Download the Somtum Launcher above and open it. It installs :client, osu!(stable) and the old clients for you, and keeps them up to date. On macOS, download :client instead, and on Android, TumThai!Next.',
                'account' => 'Make an account',
                'account_text' => 'Sign up in the game, not on this website: start the game and choose to create an account. Then sign in here with the same name and password. :link',
                'account_link' => 'More about accounts',
                'beatmaps' => 'Get beatmaps',
                'beatmaps_text' => 'Download maps in the game, or :listing here. Stuck? The :faq and the :errors may help.',
                'listing' => 'browse the beatmap listing',
                'faq' => 'FAQ',
                'errors' => 'list of error messages',
            ],

            'antivirus' => [
                'title' => 'Antivirus or Windows blocking a download?',
                'text' => 'Our downloads aren\'t code-signed (that would cost us upwards of 300€ a year), so Windows may say "Windows protected your PC" (click <strong>More info</strong>, then <strong>Run anyway</strong>), and antiviruses may block or remove the Launcher, the Patcher, <code>pkms.dll</code>, :client or the old clients by mistake. Add an exception (whitelist) for their folders, only for files from this page.',
                'link' => 'How to add an exception',
            ],

            'smart_app_control' => [
                'title' => 'Windows says "Part of this app has been blocked"?',
                'text' => 'Recent Windows security updates block unsigned apps by default through Smart App Control, and this affects every somtum build: :client, the old clients and stable. To play, search for <strong>Smart App Control</strong> in the Windows search bar and open the first result (or <strong>Windows Security</strong>, <strong>App &amp; browser control</strong>, <strong>Smart App Control settings</strong>), then change it from <strong>On</strong> to <strong>Off</strong>. It protects your whole PC, and on some PCs it\'s hard to turn back on later, so read the link below first. This may not work on a work or school PC that you don\'t own.',
                'link' => 'More about Smart App Control',
            ],
        ],

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
