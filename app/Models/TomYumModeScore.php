<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\CommentableInterface;

/**
 * TomYum: a Relax/Autopilot play (kept by TomYum, shown at /scores/rx/{id}) as far as the website needs it: for its
 * comments. Made when its page is first shown.
 *
 * @property Beatmap|null $beatmap
 * @property int $beatmap_id
 * @property int $id
 * @property int $ruleset_id
 * @property User|null $user
 * @property int $user_id
 */
class TomYumModeScore extends Model implements CommentableInterface
{
    use Traits\CommentableDefaults;

    public $incrementing = false;

    protected $table = 'tomyum_mode_scores';

    public function beatmap()
    {
        return $this->belongsTo(Beatmap::class, 'beatmap_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function commentLocked(): bool
    {
        return false;
    }

    public function commentableTitle()
    {
        return Solo\Score::commentableTitleFor($this->user, $this->beatmap);
    }

    public function notificationCover()
    {
        return $this->beatmap?->beatmapset?->coverURL('card');
    }

    public function url()
    {
        return route('scores.tomyum', ['score' => $this->getKey()]);
    }
}
