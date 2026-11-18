<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Jobs;

use App\Libraries\TomYumCustomRulesetScores;
use App\Libraries\TomYumPlara;
use App\Models\Solo\Score;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * TomYum: a custom lazer ruleset's ranked pass gets its pp from Pla-ra (TomYumPlara), then its rankings are worked out
 * again. Pla-ra down or busy: tried again a few times; after that `php artisan tomyum:plara-recalculate --missing`.
 */
class TomYumCustomRulesetPp implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $scoreId)
    {
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(): void
    {
        $score = Score::find($this->scoreId);
        if ($score === null) {
            return;
        }

        $answer = TomYumPlara::apply($score);
        if ($answer === null || ($answer['retry'] ?? false)) {
            $this->release($this->backoff()[min($this->attempts() - 1, 3)]);

            return;
        }

        TomYumCustomRulesetScores::rankingsChanged((int) $score->ruleset_id);
    }
}
