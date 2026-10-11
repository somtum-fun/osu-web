<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Libraries\TomYumCustomMedals;
use App\Models\User;
use Auth;
use Request;

/**
 * TomYum: custom medals (Community → Medals): the community's medals, and asking for one
 * (App\Libraries\TomYumCustomMedals; the Medal Team decides in the staff panel).
 */
class CustomMedalsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth', ['except' => ['index']]);
        parent::__construct();
    }

    public function index()
    {
        $user = Auth::user();
        $approved = TomYumCustomMedals::table()->where('status', 'approved')->orderBy('category')->orderBy('name')->get();
        $mine = $user === null ? collect() : TomYumCustomMedals::table()->where('user_id', $user->getKey())->orderByDesc('id')->get();
        $makers = User::whereIn('user_id', $approved->pluck('user_id')->filter()->unique())->get()->keyBy('user_id');

        return ext_view('custom_medals.index', [
            'approved' => $approved,
            'holders' => TomYumCustomMedals::holders($approved->pluck('achievement_id')->all()),
            'makers' => $makers,
            'mine' => $mine,
            'user' => $user,
        ]);
    }

    public function store()
    {
        [$id, $problem] = TomYumCustomMedals::submit(Auth::user(), Request::all(), Request::file('icon'));

        return redirect(route('custom-medals.index').'#request')
            ->with('custom_medal_status', $problem ?? 'Sent! The Medal Team will look at it soon; you\'ll see what they decide here.')
            ->with('custom_medal_ok', $problem === null)
            ->withInput($problem === null ? [] : Request::except('icon'));
    }

    public function withdraw(string $medal)
    {
        $problem = TomYumCustomMedals::withdraw(Auth::user(), get_int($medal) ?? 0);

        return redirect(route('custom-medals.index').'#mine')
            ->with('custom_medal_status', $problem ?? 'Request taken back.')
            ->with('custom_medal_ok', $problem === null);
    }
}
