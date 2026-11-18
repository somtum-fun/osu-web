<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;

/**
 * TomYum: the admin panel's "Recalculate" for Pla-ra: runs `tomyum:plara-recalculate` (every custom ruleset, or only
 * the plays without pp) on the queue, as it can take a while.
 */
class TomYumPlaraRecalculate implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(public bool $missingOnly)
    {
    }

    public function handle(): void
    {
        Artisan::call('tomyum:plara-recalculate', $this->missingOnly ? ['--missing' => true] : []);
    }
}
