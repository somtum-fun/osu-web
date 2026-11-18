<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Libraries\TomYumCustomRulesetScores;
use App\Libraries\TomYumCustomRulesets;

/**
 * TomYum: the custom lazer rulesets staff turned on, with their pinned files (GET /api/v2/tomyum/rulesets). TumThai
 * reads it (signed in) for its Settings list, its downloads and the id a ruleset's plays submit with.
 *
 * @group Custom rulesets
 */
class TomYumRulesetsController extends Controller
{
    /** /rankings/rulesets/{slug}: a custom ruleset's players by ranked score, worldwide or in one country. */
    public function rankings(?string $slug = null)
    {
        $rulesets = array_values(TomYumCustomRulesets::enabled());
        if ($rulesets === []) {
            abort(404, 'There are no custom rulesets here yet.');
        }
        if ($slug === null) {
            return redirect(route('rankings.custom-rulesets', ['slug' => $rulesets[0]->slug]));
        }
        $ruleset = TomYumCustomRulesets::findBySlug($slug) ?? abort(404, 'No such ruleset.');

        $country = presence(get_string(request('country')));
        $country = $country !== null && preg_match('/^[A-Za-z]{2}$/', $country) === 1 ? strtoupper($country) : null;
        $page = max(1, min(200, get_int(request('page')) ?? 1));
        $variant = TomYumCustomRulesets::variant(get_string(request('variant')));

        // Top plays (type=top_plays): the best pp plays, 100 a page, as the rankings' own Top Plays.
        // (None for a ruleset ranked by score: its pp isn't shown.)
        if (get_string(request('type')) === 'top_plays' && !$ruleset->ranksByPp()) {
            return redirect(route('rankings.custom-rulesets', array_filter(['slug' => $ruleset->slug, 'variant' => $variant === 'vanilla' ? null : $variant])));
        }
        if (get_string(request('type')) === 'top_plays') {
            $page = min($page, 10);
            $ids = TomYumCustomRulesetScores::topPlays($ruleset, $variant);
            $pageIds = array_slice($ids, ($page - 1) * 100, 100);
            $found = \App\Models\Solo\Score::with(['user.country', 'user.team', 'beatmap.beatmapset'])->whereIn('id', $pageIds)->get()->keyBy('id');
            $items = [];
            foreach ($pageIds as $id) {
                $score = $found[$id] ?? null;
                if ($score !== null && $score->user !== null && $score->beatmap?->beatmapset !== null) {
                    $items[] = json_item($score, new \App\Transformers\ScoreTransformer(), ['beatmap', 'beatmapset', 'user.country', 'user.team']);
                }
            }

            return ext_view('rankings.custom_rulesets', [
                'country' => null,
                'ruleset' => $ruleset,
                'rulesets' => $rulesets,
                'scores' => new \Illuminate\Pagination\LengthAwarePaginator(
                    $items,
                    count($ids),
                    100,
                    $page,
                    ['path' => route('rankings.custom-rulesets', ['slug' => $ruleset->slug]), 'query' => array_filter(['variant' => $variant === 'vanilla' ? null : $variant, 'type' => 'top_plays'])],
                ),
                'sort' => 'top_plays',
                'users' => [],
                'variant' => $variant,
            ]);
        }

        // By pp unless asked for ranked score (type=score).
        $sort = TomYumCustomRulesetScores::sort(get_string(request('type')), $ruleset);
        $rankings = TomYumCustomRulesetScores::rankings($ruleset, $country, $page, $variant, $sort);
        $users = \App\Models\User::with('country')->whereIn('user_id', array_column($rankings['rows'], 'user_id'))->get()->keyBy('user_id');

        $scores = new \Illuminate\Pagination\LengthAwarePaginator(
            $rankings['rows'],
            $rankings['total'],
            TomYumCustomRulesetScores::RANKINGS_PAGE,
            $page,
            ['path' => route('rankings.custom-rulesets', ['slug' => $ruleset->slug]), 'query' => array_filter(['country' => $country, 'variant' => $variant === 'vanilla' ? null : $variant, 'type' => $sort === 'score' ? 'score' : null])],
        );

        return ext_view('rankings.custom_rulesets', [
            'country' => $country,
            'ruleset' => $ruleset,
            'rulesets' => $rulesets,
            'scores' => $scores,
            'sort' => $sort,
            'users' => $users,
            'variant' => $variant,
        ]);
    }

    /**
     * A custom ruleset's icon (rulesets.info's), served from here: TumThai loads images only from its own server's
     * domain. Kept a day; PNG, JPEG, GIF or WebP up to 1 MB.
     */
    public function icon(string $slug)
    {
        $ruleset = TomYumCustomRulesets::findBySlug($slug) ?? abort(404);
        $url = $ruleset->icon_url ?? abort(404);

        $icon = \Cache::remember('tomyum:ruleset-icon:'.md5($url), 86400, function () use ($url) {
            try {
                $response = \Http::timeout(10)->withHeaders(['User-Agent' => 'TomYum (osu-web)'])->get($url);
            } catch (\Throwable) {
                return null;
            }
            $type = strtolower(explode(';', $response->header('Content-Type'))[0]);
            if (!$response->successful() || !in_array($type, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)
                || strlen($response->body()) > 1024 * 1024) {
                return null;
            }

            return ['type' => $type, 'body' => base64_encode($response->body())];
        });
        if ($icon === null) {
            \Cache::forget('tomyum:ruleset-icon:'.md5($url));
            abort(404);
        }

        return response(base64_decode($icon['body']), 200, [
            'Content-Type' => $icon['type'],
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Get Own Custom Ruleset Standing
     *
     * Your standing in a [custom ruleset](#ruleset-custom-rulesets): your rank (global and country), ranked score,
     * accuracy and play count there. (TumThai's user card. For the whole user, see [Get Own Data](#get-own-data) with
     * the ruleset's slug.)
     *
     * @urlParam slug string required The custom ruleset's slug. Example: sentakki
     * @queryParam variant `vanilla` (default), `relax` or `autopilot`. No-example
     */
    public function me(string $slug)
    {
        $ruleset = TomYumCustomRulesets::findBySlug($slug) ?? abort(404);
        $user = \Auth::user() ?? abort(401);

        return response()->json(TomYumCustomRulesetScores::standing(
            $ruleset,
            $user,
            TomYumCustomRulesets::variant(get_string(request('variant'))),
        ));
    }

    /**
     * Get Custom Rulesets
     *
     * The [custom rulesets](#ruleset-custom-rulesets) staff have turned on: each one's `id` (the `ruleset_id` its
     * scores have), `slug` (what endpoints take in place of a ruleset name), `name`, `description`, icon, version,
     * and the ruleset's DLL (`download_url`, `sha256`, `size`) that TumThai installs.
     *
     * @response {"rulesets":[{"id":10,"slug":"sentakki","name":"sentakki","assembly":"osu.Game.Rulesets.Sentakki","description":null,"icon_url":null,"icon":null,"source_url":null,"version":"2026.1020.0","download_url":"https://...","sha256":"...","size":1048576}]}
     */
    public function index()
    {
        return response()->json([
            'rulesets' => array_values(array_map(
                fn ($ruleset) => TomYumCustomRulesets::toApi($ruleset),
                TomYumCustomRulesets::enabled(),
            )),
        ]);
    }
}
