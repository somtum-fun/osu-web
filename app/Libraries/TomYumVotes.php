<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Libraries;

use App\Models\User;
use DB;
use Illuminate\Database\QueryException;

/**
 * TomYum: what voting gives (the tomyum.votes plugin's table, tomyum.plugin_votes). A vote adds days
 * of osu!direct (direct_until), not supporter: the game's osu!direct at login (the plugin) and the
 * website's osu!direct button (here). Nothing else of supporter's.
 */
class TomYumVotes
{
    /** When the player's voted osu!direct ends, or null when they have none left. */
    public static function directUntil(?User $user): ?\Carbon\Carbon
    {
        if ($user === null) {
            return null;
        }

        // Once per request (the page and its JSON both ask); not static: Octane keeps statics between requests.
        $attributes = request()->attributes;
        $key = 'tomyum_votes_direct:'.$user->getKey();
        if (!$attributes->has($key)) {
            try {
                $until = DB::table('tomyum.plugin_votes')
                    ->where('user_id', $user->getKey())
                    ->where('direct_until', '>', now())
                    ->value('direct_until');
            } catch (QueryException) {
                // No votes yet (the plugin makes the table on the first vote) or an older table.
                $until = null;
            }
            $attributes->set($key, $until === null ? null : \Carbon\Carbon::parse($until, 'UTC'));
        }

        return $attributes->get($key);
    }

    /** Whether the player can use osu!direct: supporters, and voters with time left. */
    public static function hasDirect(?User $user): bool
    {
        return $user !== null && ($user->isSupporter() || static::directUntil($user) !== null);
    }
}
