<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers;

use App;
use App\Libraries\CurrentStats;
use App\Libraries\MenuContent;
use App\Libraries\Search\AllSearch;
use App\Libraries\Search\QuickSearch;
use App\Libraries\TomYumBranding;
use App\Libraries\TomYumDownloads;
use App\Models\BeatmapDownload;
use App\Models\Beatmapset;
use App\Models\Forum\Post;
use App\Models\Multiplayer\Room;
use App\Models\NewsPost;
use App\Models\User;
use App\Models\UserDonation;
use App\Transformers\MenuImageTransformer;
use App\Transformers\NewsPostTransformer;
use App\Transformers\UserCompactTransformer;
use Auth;
use Carbon\CarbonImmutable;
use DB;
use DeviceDetector\DeviceDetector;
use Illuminate\Database\QueryException;
use Request;

/**
 * @group Home
 */
class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth', [
            'only' => [
                'downloadQuotaCheck',
                'quickSearch',
            ],
        ]);

        $this->middleware('require-scopes:public', ['only' => 'search']);

        parent::__construct();
    }

    public function bbcodePreview()
    {
        $post = new Post(['post_text' => Request::input('text')]);

        return $post->bodyHTML();
    }

    /**
     * @group Undocumented
     */
    public function downloadQuotaCheck()
    {
        return [
            'quota_used' => BeatmapDownload::where('user_id', Auth::user()->user_id)->count(),
        ];
    }

    public function getDownload()
    {
        // TomYum: TumThai (lazer) is built for Windows, Linux, macOS and Android.
        $lazerPlatformNames = [
            'windows_x64' => 'Windows (x64)',
            'macos_arm64' => 'macOS (Apple Silicon)',
            'macos_x64' => 'macOS (Intel)',
            'linux_x64' => 'Linux (x64)',
            'linux_arm64' => 'Linux (ARM64)',
            'android' => 'Android',
        ];

        $platform = get_string(request('platform'));
        if (!array_key_exists($platform, $lazerPlatformNames)) {
            $deviceDetector = new DeviceDetector(\Request::header('User-Agent') ?? '');
            $deviceDetector->parse();
            $platform = match ($deviceDetector->getOs('family')) {
                'GNU/Linux' => 'linux_x64',
                'Android' => 'android',
                // TomYum: browsers on Apple Silicon still say "Intel", so Macs get the Apple Silicon build; Intel is in the list.
                'Mac' => 'macos_arm64',
                default => 'windows_x64',
            };
        }

        $manifest = TomYumDownloads::manifest();
        $lazer = $manifest['lazer'][$platform] ?? null;
        // TomYum: on Windows the main button is the installer; the portable zip is a link under it.
        $lazerPortable = $manifest['lazer']["{$platform}_portable"] ?? null;
        // TomYum: Somtum Launcher (the manifest's "self"): on Windows it's the main button, with TumThai on its
        // own under it. Not when staff link TumThai somewhere of their own (Admin panel > Branding).
        // Its installer when the list has one (the plain exe before that), and its portable zip.
        $launcherSelf = $manifest['self']['windows_x64_setup'] ?? $manifest['self']['windows_x64'] ?? null;
        $launcher = $platform === 'windows_x64' && TomYumBranding::downloadsLazerUrl() === null && isset($launcherSelf['url'])
            ? [...$launcherSelf, 'version' => $manifest['self']['version'] ?? null, 'portable' => $manifest['self']['windows_x64_portable'] ?? null]
            : null;
        // TomYum: the Linux launcher, a line under TumThai's Linux button.
        $launcherLinux = $platform === 'linux_x64' && TomYumBranding::downloadsLazerUrl() === null
            ? ($manifest['self']['linux_x64'] ?? null)
            : null;

        $items = [];
        foreach ($lazerPlatformNames as $key => $value) {
            $items[] = ['id' => $key, 'text' => $value];
        }

        $selectOptions = [
            'currentItem' => ['id' => $platform, 'text' => osu_trans('home.download.other_os')],
            'items' => $items,
            'modifiers' => 'download',
            'type' => 'download',
        ];

        return ext_view('home.download', [
            'dlUrl' => TomYumDownloads::baseUrl(),
            'hook' => $manifest['hook'] ?? null,
            'launcher' => $launcher,
            'launcherLinux' => $launcherLinux,
            'lazer' => $lazer,
            'lazerUrl' => TomYumBranding::downloadsLazerUrl() ?? $lazer['url'] ?? TomYumDownloads::baseUrl().'/lazer/',
            'lazerPlatform' => $platform,
            'lazerPlatformName' => $lazerPlatformNames[$platform],
            'lazerPortable' => $lazerPortable,
            'legacyYears' => TomYumDownloads::legacyByYear($manifest),
            'patcherUrl' => TomYumBranding::downloadsPatcherUrl() ?? $manifest['patcher']['url'] ?? TomYumDownloads::PATCHER_URL,
            // TomYum: what the page shows (Admin panel > Branding).
            'showLazer' => TomYumBranding::enabled('downloads_show_lazer'),
            'showStable' => TomYumBranding::enabled('downloads_show_stable'),
            'showLegacy' => TomYumBranding::enabled('downloads_show_legacy'),
            'selectOptions' => $selectOptions,
            // A link of the staff's own isn't the file host's build: no version to show.
            'version' => TomYumBranding::downloadsLazerUrl() === null ? ($manifest['lazer']['version'] ?? null) : null,
        ]);
    }

    // TomYum: the server lists to vote on, the player's own votes and the top voters. The counts are
    // the tomyum.votes plugin's (tomyum.plugin_votes), which it creates on the first vote.
    public function vote()
    {
        // Switched off (Admin panel > Branding): no vote page.
        if (!TomYumBranding::voteEnabled()) {
            abort(404);
        }
        $config = $GLOBALS['cfg']['tomyum']['votes'];
        $user = Auth::user();
        $myVotes = null;
        $topVoters = [];

        try {
            if ($user !== null) {
                $myVotes = (int) DB::table('tomyum.plugin_votes')->where('user_id', $user->getKey())->value('votes');
            }

            $counts = DB::table('tomyum.plugin_votes')
                ->orderByDesc('votes')
                ->orderBy('first_voted_at')
                ->limit(10)
                ->pluck('votes', 'user_id');
            $users = User::default()->whereIn('user_id', $counts->keys())->get()->keyBy('user_id');
            foreach ($counts as $userId => $votes) {
                if (isset($users[$userId])) {
                    $topVoters[] = ['user' => $users[$userId], 'votes' => (int) $votes];
                }
            }
        } catch (QueryException) {
            // no votes yet
        }

        return ext_view('home.vote', [
            'daysPerVote' => $config['days_per_vote'],
            'myVotes' => $myVotes,
            'sites' => TomYumBranding::voteSites(),
            'topVoters' => $topVoters,
            'user' => $user,
        ]);
    }

    public function index()
    {
        $host = Request::getHttpHost();
        $subdomain = substr($host, 0, strpos($host, '.'));

        if ($subdomain === 'store') {
            return ujs_redirect(route('store.products.index'));
        }

        $newsLimit = Auth::check() ? NewsPost::DASHBOARD_LIMIT + 1 : NewsPost::LANDING_LIMIT;
        $news = NewsPost::default()->limit($newsLimit)->get();

        if (Auth::check()) {
            $menuImages = json_collection(MenuContent::activeImages(), new MenuImageTransformer());
            $newBeatmapsets = Beatmapset::latestRanked();
            $popularBeatmapsetsByRuleset = Beatmapset::popularByRuleset();

            $dailyChallenge = Room::dailyChallengeFor(CarbonImmutable::now());

            // TomYum: no livestreams (the page is off), so no featured stream either.
            $featuredStream = null;

            return ext_view('home.user', compact(
                'menuImages',
                'newBeatmapsets',
                'news',
                'popularBeatmapsetsByRuleset',
                'dailyChallenge',
                'featuredStream',
            ));
        } else {
            $news = json_collection($news, new NewsPostTransformer());

            return ext_view('home.landing', ['stats' => new CurrentStats(), 'news' => $news]);
        }
    }

    public function messageUser($user)
    {
        return ujs_redirect(route('chat.index', ['sendto' => $user]));
    }

    public function opensearch()
    {
        return ext_view('home.opensearch', null, 'opensearch')->header('Cache-Control', 'max-age=86400');
    }

    /** TomYum: the web app manifest, with this server's name and favicons (TomYumBranding). */
    public function webmanifest()
    {
        $name = \App\Libraries\TomYumBranding::name();
        $svg = TomYumBranding::faviconType() === 'image/svg+xml';
        $icon = fn (int $size) => [
            'src' => TomYumBranding::favicon("android-chrome-{$size}x{$size}.png"),
            'sizes' => $svg ? 'any' : "{$size}x{$size}",
            'type' => TomYumBranding::faviconType(),
        ];

        return response()->json([
            'name' => $name,
            'short_name' => $name,
            'icons' => $svg ? [$icon(512)] : [$icon(192), $icon(512)],
            'theme_color' => '#ffffff',
            'background_color' => '#ffffff',
            'display' => 'standalone',
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'max-age=3600']);
    }

    /** TomYum: the name drawn as the logo (?white=1 for the menu bar); its address changes with the name. */
    public function brandLogo()
    {
        return response(TomYumBranding::textLogoSvg(Request::boolean('white')), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** TomYum: the name's first letter as the favicon; its address changes with the name. */
    public function brandFavicon()
    {
        return response(TomYumBranding::letterFaviconSvg(), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function quickSearch()
    {
        $quickSearch = new QuickSearch(Request::all(), ['user' => auth()->user()]);
        $searches = $quickSearch->searches();

        $result = [];

        if ($quickSearch->hasQuery()) {
            foreach ($searches as $mode => $search) {
                if ($search === null) {
                    continue;
                }
                $result[$mode]['total'] = $search->count();
                if (QuickSearch::MODES[$mode]['size'] !== 0) {
                    $transformer = QuickSearch::MODES[$mode]['transformer'];
                    $transformerInstance = new $transformer['class']();
                    $data = $search->data();
                    // TomYum: players come with their osu! statistics (the list player cards' level)
                    if ($transformerInstance instanceof UserCompactTransformer) {
                        $transformerInstance->setMode('osu');
                        $data->loadMissing(UserCompactTransformer::listIncludesPreload('osu'));
                    }
                    $result[$mode]['items'] = json_collection(
                        $data,
                        $transformerInstance,
                        $transformer['includes'],
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Search
     *
     * Searches users and wiki pages.
     *
     * ---
     *
     * ### Response Format
     *
     * Field     | Type                       | Description
     * --------- | -------------------------- | -----------
     * user      | SearchResult&lt;User>?     | For `all` or `user` mode. Only first 100 results are accessible
     * wiki_page | SearchResult&lt;WikiPage>? | For `all` or `wiki_page` mode
     *
     * #### SearchResult&lt;T>
     *
     * Field | Type    | Description
     * ----- | ------- | -----------
     * data  | T[]     | |
     * total | integer | |
     *
     * @queryParam mode string Either `all`, `user`, or `wiki_page`. Default is `all`. Example: all
     * @queryParam query Search keyword. Example: hello
     * @queryParam page Search result page. Ignored for mode `all`. Example: 1
     */
    public function search()
    {
        $currentUser = Auth::user();
        $allSearch = new AllSearch(Request::all(), ['user' => $currentUser]);

        switch ($allSearch->getMode()) {
            case 'artist_track':
                return ujs_redirect(route('artists.tracks.index', ['query' => $allSearch->getRawQuery()]));
            case 'beatmapset':
                return ujs_redirect(route('beatmapsets.index', ['q' => $allSearch->getRawQuery()]));
        }

        $isSearchPage = true;

        if (is_api_request()) {
            return response()->json($allSearch->toJson());
        }

        $fields = $currentUser?->isModerator() ?? false ? [] : ['includeDeleted' => null];

        return ext_view('home.search', compact('allSearch', 'fields', 'isSearchPage'));
    }

    public function setLocale()
    {
        $newLocale = get_valid_locale(Request::input('locale')) ?? $GLOBALS['cfg']['app']['fallback_locale'];
        App::setLocale($newLocale);

        if (Auth::check()) {
            Auth::user()->update([
                'user_lang' => $newLocale,
            ]);
        }

        return ext_view('layout.ujs_full_reload', [], 'js')
            ->withCookie(cookie()->forever('locale', $newLocale));
    }

    public function supportTheGame()
    {
        $user = auth()->user();

        if ($user !== null) {
            // current status
            $expiration = $user->osu_subscriptionexpiry?->addDays(1);
            $current = $expiration?->isFuture() ?? false;

            static $lengthSumFn = fn ($p) => $p['length'] * ($p['cancel'] ? -1 : 1);
            // purchased
            $tagPurchases = $user->supporterTagPurchases;
            $dollars = $tagPurchases->sum('amount');
            $duration = $tagPurchases->sum($lengthSumFn);

            // gifted
            $gifted = $tagPurchases->where('target_user_id', '<>', $user->user_id);
            $giftedDollars = $gifted->sum('amount');
            $giftedDuration = $gifted->sum($lengthSumFn);

            $giftedUsers = [];
            foreach ($gifted as $gift) {
                $giftedUsers[$gift->target_user_id] =
                    ($giftedUsers[$gift->target_user_id] ?? 0)
                    + ($gift->cancel ? -1 : 1);
            }
            $giftedUsers = count(array_filter($giftedUsers, fn ($count) => $count > 0));

            $supporterStatus = [
                // current status
                'current' => $current,
                'expiration' => $expiration,
                // purchased
                'dollars' => currency($dollars, 2, false),
                'duration' => $duration,
                // gifted
                'giftedDollars' => currency($giftedDollars, 2, false),
                'giftedDuration' => $giftedDuration,
                'giftedUsers' => $giftedUsers,
            ];

            if ($current) {
                $lastTagPurchaseDate = UserDonation::where('target_user_id', $user->user_id)
                    ->orderBy('timestamp', 'desc')
                    ->pluck('timestamp')
                    ->first();

                $lastTagPurchaseDate ??= $expiration->copy()->subMonths(1);

                $total = max(1, $lastTagPurchaseDate->diffInDays($expiration));
                $used = max(1, $lastTagPurchaseDate->diffInDays());

                $supporterStatus['remainingPercent'] = 100 - round($used / $total * 100, 2);
            }
        }

        $pageLayout = [
            // why support
            // TomYum: where this server's money goes (osu!'s Featured Artists, official tournaments and
            // bounty program aren't ours); voting gives supporter for free.
            'support-reasons' => [
                'type' => 'group',
                'section' => 'why-support',
                'items' => [
                    'team' => [
                        'icons' => ['fas fa-users'],
                        // TomYum: Admin panel > Branding.
                        'description' => e(TomYumBranding::text('support_team')),
                    ],
                    'infra' => [
                        'icons' => ['fas fa-server'],
                    ],
                    'ads' => [
                        'icons' => ['fas fa-ad', 'fas fa-slash'],
                    ],
                    'vote' => [
                        'icons' => ['fas fa-vote-yea'],
                        'link' => route('vote'),
                    ],
                ],
            ],

            // supporter perks

            // There are 5 perk rendering types: image, image-flipped, hero, group and image-group.
            // image, image-flipped, hero each show an individual perk (with image) while group and image-group show groups of perks (the latter with images)
            'perks' => [
                // TomYum: somtum.fun's own perks first (somtum.fun/support).
                [
                    'type' => 'group',
                    'items' => [
                        'discord_role' => [
                            'icons' => ['fab fa-discord'],
                        ],
                        'unlimited_name_changes' => [
                            'icons' => ['fas fa-magic'],
                        ],
                        'fresh_start' => [
                            'icons' => ['fas fa-broom'],
                        ],
                        'dev_team' => [
                            'icons' => ['fas fa-comments'],
                        ],
                        'much_more' => [
                            'icons' => ['fas fa-gift'],
                        ],
                    ],
                ],
                // TomYum: pictures are our own screenshots (images/layout/supporter/somtum); perks
                // we have none for are text only, and osu!'s Speedy Downloads and Skinnables aren't ours.
                [
                    'type' => 'image',
                    'name' => 'osu_direct',
                    'image' => 'somtum/osu_direct.webp',
                    'icons' => ['fas fa-search'],
                ],
                [
                    'type' => 'image',
                    'variant' => 'flipped',
                    'name' => 'friend_ranking',
                    'image' => 'somtum/friend_ranking.webp',
                    'icons' => ['fas fa-list-alt'],
                ],
                [
                    'type' => 'image',
                    'name' => 'mod_filtering',
                    'image' => 'somtum/mod_filtering.webp',
                    'icons' => ['fas fa-tasks'],
                ],
                [
                    'type' => 'group',
                    'items' => [
                        'country_ranking' => [
                            'icons' => ['fas fa-globe-asia'],
                        ],
                        'beatmap_filters' => [
                            'icons' => ['fas fa-filter'],
                        ],
                        'customisation' => [
                            'icons' => ['fas fa-image'],
                        ],
                    ],
                ],
                [
                    'type' => 'group',
                    'items' => [
                        'yellow_fellow' => [
                            'icons' => ['fas fa-fire'],
                        ],
                        'auto_downloads' => [
                            'icons' => ['fas fa-download'],
                        ],
                        'more_beatmaps' => [
                            'icons' => ['fas fa-file-upload'],
                            'translation_options' => [
                                'base' => $GLOBALS['cfg']['osu']['beatmapset']['upload_allowed'],
                                'bonus' => $GLOBALS['cfg']['osu']['beatmapset']['upload_bonus_per_ranked'],
                                'bonus_max' => $GLOBALS['cfg']['osu']['beatmapset']['upload_bonus_per_ranked_max'],
                                'supporter_base' => $GLOBALS['cfg']['osu']['beatmapset']['upload_allowed_supporter'],
                                'supporter_bonus' => $GLOBALS['cfg']['osu']['beatmapset']['upload_bonus_per_ranked_supporter'],
                                'supporter_bonus_max' => $GLOBALS['cfg']['osu']['beatmapset']['upload_bonus_per_ranked_max_supporter'],
                            ],
                        ],
                        // TomYum: no early access (osu!'s TestFlight) here.
                    ],
                ],
                [
                    'type' => 'group',
                    'items' => [
                        'more_favourites' => [
                            'icons' => ['fas fa-star'],
                            'translation_options' => [
                                'normally' => $GLOBALS['cfg']['osu']['beatmapset']['favourite_limit'],
                                'supporter' => $GLOBALS['cfg']['osu']['beatmapset']['favourite_limit_supporter'],
                            ],
                        ],
                        'more_friends' => [
                            'icons' => ['fas fa-user-friends'],
                            'translation_options' => [
                                'normally' => $GLOBALS['cfg']['osu']['user']['max_friends'],
                                'supporter' => $GLOBALS['cfg']['osu']['user']['max_friends_supporter'],
                            ],
                        ],
                        'friend_filtering' => [
                            'icons' => ['fas fa-medal'],
                        ],
                    ],
                ],
            ],
        ];

        // TomYum: the reasons Admin panel > Branding hides (an empty "Run by Volunteers" text, the vote
        // page switched off).
        if (TomYumBranding::text('support_team') === '') {
            unset($pageLayout['support-reasons']['items']['team']);
        }
        if (!TomYumBranding::voteEnabled()) {
            unset($pageLayout['support-reasons']['items']['vote']);
        }

        return ext_view('home.support-the-game', [
            'supporterStatus' => $supporterStatus ?? [],
            'data' => $pageLayout,
        ]);
    }

    public function testflight()
    {
        return ext_view('home.testflight');
    }
}
