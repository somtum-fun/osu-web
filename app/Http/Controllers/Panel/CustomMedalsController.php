<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Libraries\TomYumCustomMedals;
use App\Models\User;
use Auth;
use Request;

/**
 * TomYum: custom medals for the Medal Team (and admins): players' requests to approve or reject (with
 * their rule, which can be edited first), and the medals that are out (edit, or take out).
 */
class CustomMedalsController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseMedals(Auth::user()));

            return $next($request);
        });
    }

    public static function canUseMedals(?User $user): bool
    {
        return TomYumCustomMedals::isTeam($user);
    }

    public function index()
    {
        $state = Request::input('state');
        if (!array_key_exists($state, TomYumCustomMedals::STATES)) {
            $state = 'pending';
        }
        $medals = TomYumCustomMedals::table()->where('status', $state)
            ->orderBy($state === 'pending' ? 'id' : 'decided_at', $state === 'pending' ? 'asc' : 'desc')
            ->paginate(20)->withQueryString();
        $people = User::whereIn('user_id', collect($medals->items())->flatMap(fn ($m) => [$m->user_id, $m->decided_by])->filter()->unique())
            ->get()->keyBy('user_id');

        return ext_view('panel.medals.index', [
            'counts' => TomYumCustomMedals::table()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status'),
            'holders' => TomYumCustomMedals::holders(collect($medals->items())->pluck('achievement_id')->filter()->all()),
            'medals' => $medals,
            'people' => $people,
            'state' => $state,
        ]);
    }

    public function update(string $medal)
    {
        $model = $this->find($medal);
        $problem = TomYumCustomMedals::edit($model, Request::all());
        if ($problem === null) {
            $this->staffLog('medal.edit', $model->id, "Edited the custom medal \"{$model->name}\"");
        }

        return $this->back($model, $problem ?? 'Saved.', $problem === null);
    }

    public function approve(string $medal)
    {
        $model = $this->find($medal);
        $problem = TomYumCustomMedals::approve($model, Auth::user());
        if ($problem === null) {
            $this->staffLog('medal.approve', $model->id, "Approved the custom medal \"{$model->name}\"");
        }

        return $this->back($model, $problem ?? "\"{$model->name}\" is out: new plays that meet its rule earn it.", $problem === null);
    }

    public function reject(string $medal)
    {
        $model = $this->find($medal);
        $reason = (string) Request::input('reason');
        $problem = TomYumCustomMedals::reject($model, Auth::user(), $reason);
        if ($problem === null) {
            $this->staffLog('medal.reject', $model->id, "Rejected the custom medal \"{$model->name}\"".(present($reason) ? ": {$reason}" : ''));
        }

        return $this->back($model, $problem ?? "Rejected \"{$model->name}\".", $problem === null);
    }

    public function disable(string $medal)
    {
        $model = $this->find($medal);
        $reason = (string) Request::input('reason');
        $problem = TomYumCustomMedals::disable($model, Auth::user(), $reason);
        if ($problem === null) {
            $this->staffLog('medal.disable', $model->id, "Took out the custom medal \"{$model->name}\"".(present($reason) ? ": {$reason}" : ''));
        }

        return $this->back($model, $problem ?? "\"{$model->name}\" is taken out (who has it keeps it).", $problem === null);
    }

    private function find(string $id): object
    {
        return TomYumCustomMedals::table()->where('id', get_int($id) ?? 0)->first() ?? abort(404);
    }

    private function back(object $medal, string $message, bool $ok)
    {
        return redirect(route('panel.medals.index', ['state' => $ok ? Request::input('return_state', $medal->status) : $medal->status]).'#medal-'.$medal->id)
            ->with('panel_status', ['type' => $ok ? 'success' : 'error', 'message' => $message, 'link' => null]);
    }
}
