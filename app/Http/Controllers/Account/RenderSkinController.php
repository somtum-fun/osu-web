<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Libraries\TomYumAdmin;
use App\Models\User;

/**
 * TomYum: the o!rdr skin a player's replay renders use (account settings; /renderskin on Discord sets the same).
 */
class RenderSkinController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware('auth');
    }

    /** The player's skin and o!rdr's skins to pick from, for the settings page; null when the game server can't say. */
    public static function status(User $user): ?array
    {
        try {
            $admin = (new TomYumAdmin($user))->withTimeout(5);
            $current = $admin->get("/users/{$user->getKey()}/render-skin");
            $skins = $admin->get('/render-skins');
        } catch (\Throwable $e) {
            return null;
        }
        if (!$current->successful() || !$skins->successful()) {
            return null;
        }

        return ['current' => $current->json(), 'skins' => $skins->json() ?? []];
    }

    public function update()
    {
        // Their own skin (uploaded on o!rdr's site, by its ID or link) over one of o!rdr's.
        $skin = presence(get_string(request('custom_skin'))) ?? presence(get_string(request('skin')));
        $response = (new TomYumAdmin(\Auth::user()))->put('/users/'.\Auth::id().'/render-skin', ['skin' => $skin]);

        return redirect(route('account.edit').'#render-skin')->with('render_skin_status', $response->successful()
            ? ['type' => 'success', 'message' => 'Your replay renders now use '.($response->json('name') ?? $skin ?? 'the default skin').'.']
            : ['type' => 'error', 'message' => $response->json('error') ?? 'The skin couldn\'t be saved.']);
    }
}
