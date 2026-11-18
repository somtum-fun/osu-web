<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Console\Commands;

use App\Libraries\TomYumCustomRulesets;
use App\Libraries\TomYumCustomRulesetScores;
use App\Libraries\TomYumPlara;
use App\Models\Solo\Score;
use Illuminate\Console\Command;

/**
 * TomYum: works out custom lazer rulesets' ranked passes' pp again with Pla-ra: after a new Pla-ra version, a ruleset
 * pinned again, or for plays whose pp couldn't be worked out when they were played (--missing).
 */
class TomYumPlaraRecalculate extends Command
{
    protected $signature = 'tomyum:plara-recalculate
        {--ruleset= : Only this custom ruleset (its slug)}
        {--missing : Only plays without pp yet}';

    protected $description = "Works out custom lazer rulesets' pp again (Pla-ra).";

    public function handle()
    {
        $rulesets = TomYumCustomRulesets::enabled();
        if (($slug = presence($this->option('ruleset'))) !== null) {
            $ruleset = TomYumCustomRulesets::findBySlug($slug);
            if ($ruleset === null) {
                $this->error("No enabled custom ruleset {$slug}.");

                return 1;
            }
            $rulesets = [$ruleset->id => $ruleset];
        }

        $done = $none = $failed = 0;
        $reasons = [];
        $query = Score::whereIn('ruleset_id', array_keys($rulesets))->where('passed', true)->where('ranked', true);
        if ($this->option('missing')) {
            $query->whereNull('pp');
        }

        $query->chunkById(200, function ($scores) use (&$done, &$none, &$failed, &$reasons) {
            foreach ($scores as $score) {
                $answer = TomYumPlara::apply($score);
                if ($answer === null || ($answer['retry'] ?? false)) {
                    $failed++;
                    $this->warn("Score {$score->getKey()}: ".($answer['reason'] ?? "Pla-ra couldn't be reached"));
                } elseif (($answer['pp'] ?? null) === null) {
                    $none++;
                    $reasons[$answer['reason'] ?? '?'] = ($reasons[$answer['reason'] ?? '?'] ?? 0) + 1;
                } else {
                    $done++;
                }
            }
        });

        foreach (array_keys($rulesets) as $id) {
            TomYumCustomRulesetScores::rankingsChanged($id);
        }

        $this->info("{$done} plays with pp, {$none} without, {$failed} not worked out (try again).");
        foreach ($reasons as $reason => $count) {
            $this->line("  {$count}× {$reason}");
        }

        return $failed > 0 ? 1 : 0;
    }
}
