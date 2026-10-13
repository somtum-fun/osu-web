<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers;

use App\Libraries\TomYumAdmin;
use App\Libraries\TomYumModeScores;

/**
 * TomYum: replay renders (o!rdr, through the game server, which the Discord bot's Render button and /render
 * use too) from score pages: ask for one with the player's skin, then follow it until the video's there.
 */
class TomYumRendersController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware('auth');
    }

    /** POST /scores/{score}/render: a website score, or a Relax/Autopilot play (numbered from TomYumModeScores::ID_OFFSET). */
    public function store($scoreId)
    {
        $id = get_int($scoreId) ?? abort(404);
        $tomyumId = TomYumModeScores::tomyumId($id);
        $response = (new TomYumAdmin(\Auth::user()))->post('/renders', [
            'scoreKey' => $tomyumId === null ? "web:{$id}" : "mode:{$tomyumId}",
            'requestedBy' => \Auth::id(),
        ]);
        abort_unless($response->successful(), 503, 'The game server is unavailable.');

        return $response->json();
    }

    /** GET /renders/{render}: where a render is (polled by the score page). */
    public function show($renderId)
    {
        $response = (new TomYumAdmin(\Auth::user()))->get('/renders/'.(get_int($renderId) ?? abort(404)));
        abort_unless($response->successful(), 404);

        return $response->json();
    }
}
