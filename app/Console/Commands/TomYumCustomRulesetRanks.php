<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Console\Commands;

use App\Libraries\TomYumCustomRulesets;
use App\Libraries\TomYumCustomRulesetScores;
use Carbon\CarbonImmutable;
use DB;
use Illuminate\Console\Command;

/**
 * TomYum: each player's global rank (by pp) and pp on every custom lazer ruleset's boards today (or, with --days, on
 * each of the last days, as the plays up to then had it), for their profile's rank graph and its pp view.
 */
class TomYumCustomRulesetRanks extends Command
{
    protected $signature = 'tomyum:custom-ruleset-ranks {--days=1 : How many days back to work out (90 fills a new graph)}';

    protected $description = "Keeps players' daily rank on custom lazer rulesets' boards.";

    public function handle()
    {
        $days = max(1, min(90, (int) $this->option('days')));
        $today = CarbonImmutable::now()->startOfDay();
        $stored = 0;

        foreach (TomYumCustomRulesets::enabled() as $ruleset) {
            foreach (TomYumCustomRulesets::variants($ruleset) as $variant) {
                for ($back = $days - 1; $back >= 0; $back--) {
                    $day = $today->subDays($back);
                    // As the plays until the end of that day had it (today: until now).
                    $ranked = TomYumCustomRulesetScores::rankedBefore($ruleset, $variant, $back === 0 ? null : $day->addDay());
                    if ($ranked === []) {
                        continue;
                    }
                    $rows = [];
                    foreach ($ranked as $index => $player) {
                        $rows[] = ['user_id' => $player['user_id'], 'ruleset_id' => $ruleset->id, 'variant' => $variant, 'date' => $day->toDateString(), 'rank' => $index + 1, 'pp' => $player['pp']];
                    }
                    foreach (array_chunk($rows, 1000) as $chunk) {
                        DB::table('tomyum_custom_ruleset_rank_history')->upsert($chunk, ['user_id', 'ruleset_id', 'variant', 'date'], ['rank', 'pp']);
                    }
                    $stored += count($rows);
                }
            }
        }

        // Ninety days are shown: older ones go.
        DB::table('tomyum_custom_ruleset_rank_history')->where('date', '<', $today->subDays(90)->toDateString())->delete();
        $this->info("Stored {$stored} ranks.");
    }
}
