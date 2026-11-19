<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\Solo\Score;
use App\Models\TomYumCustomRuleset;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * TomYum: Pla-ra, custom lazer rulesets' stars and pp (TomYum's services/TomYum.Plara sidecar, which runs the pinned
 * ruleset's own code). A custom ruleset's ranked pass gets its pp from it (TomYumCustomRulesetPp, queued when the
 * score is stored); scores.pp stays null for a play without pp (failed, a mod without pp, judgements that don't fit
 * the map), and those count by score only.
 */
class TomYumPlara
{
    /**
     * Asks Pla-ra for the score's pp and stores it (null: none). Returns the answer ({pp, stars, version, reason,
     * retry}), or null when the score isn't a custom ruleset's or Pla-ra can't be reached (try again later).
     */
    public static function apply(Score $score): ?array
    {
        $answer = static::calculate($score);
        if ($answer === null || ($answer['retry'] ?? false)) {
            return $answer;
        }

        $pp = isset($answer['pp']) ? (float) $answer['pp'] : null;
        // Straight to the table: a score's model events (the index of osu!'s own boards) are nothing to do with it.
        DB::table('scores')->where('id', $score->getKey())->update(['pp' => $pp]);
        $score->pp = $pp;

        return $answer;
    }

    /**
     * For the admin panel's pp libraries: what the sidecar runs ({version, osu_game, profiles}, null when it can't be
     * reached), and each enabled custom ruleset's ranked passes with and without pp.
     */
    public static function status(): array
    {
        try {
            $response = Http::timeout(3)->acceptJson()->get(rtrim($GLOBALS['cfg']['tomyum']['plara_url'], '/').'/version');
            $running = $response->successful() ? $response->json() : null;
        } catch (ConnectionException) {
            $running = null;
        }

        $rulesets = TomYumCustomRulesets::enabled();
        $counts = $rulesets === [] ? [] : DB::table('scores')
            ->whereIn('ruleset_id', array_keys($rulesets))
            ->where('passed', true)
            ->where('ranked', true)
            ->groupBy('ruleset_id')
            ->selectRaw('ruleset_id, COUNT(pp) AS with_pp, SUM(pp IS NULL) AS without_pp')
            ->get()
            ->keyBy('ruleset_id');

        $profiles = array_column($running['profiles'] ?? [], null, 'assembly');
        $rows = [];
        foreach ($rulesets as $id => $ruleset) {
            $rows[] = [
                'ruleset' => $ruleset,
                'profile' => $profiles[$ruleset->assembly] ?? null,
                'with_pp' => (int) ($counts[$id]->with_pp ?? 0),
                'without_pp' => (int) ($counts[$id]->without_pp ?? 0),
            ];
        }

        return ['running' => $running, 'rulesets' => $rows];
    }

    /**
     * For the beatmap page's pp calculator: what a pass of the map in a custom ruleset would be worth, with the mods
     * and misses, at each accuracy ({stars, objects, results: [{accuracy, pp}], mods: {acronym: multiplier}, version,
     * reason, retry}); null when Pla-ra can't be reached.
     */
    public static function ppCalc(TomYumCustomRuleset $ruleset, int $beatmapId, array $mods, array $accuracies, int $misses): ?array
    {
        try {
            $response = Http::timeout(60)->acceptJson()->post(rtrim($GLOBALS['cfg']['tomyum']['plara_url'], '/').'/pp-calc', [
                'assembly' => $ruleset->assembly,
                'download_url' => $ruleset->download_url,
                'sha256' => $ruleset->sha256,
                'beatmap_id' => $beatmapId,
                'mods' => $mods,
                'accuracies' => $accuracies,
                'misses' => $misses,
            ]);
        } catch (ConnectionException $e) {
            log_error($e);

            return null;
        }

        return $response->successful() ? $response->json() : null;
    }

    /** Pla-ra's answer for the score, without storing it; null when it isn't a custom ruleset's or can't be asked. */
    public static function calculate(Score $score): ?array
    {
        $ruleset = TomYumCustomRulesets::find($score->ruleset_id);
        if ($ruleset === null) {
            return null;
        }

        $data = $score->data;
        try {
            $response = Http::timeout(120)->acceptJson()->post(rtrim($GLOBALS['cfg']['tomyum']['plara_url'], '/').'/pp', [
                'assembly' => $ruleset->assembly,
                'download_url' => $ruleset->download_url,
                'sha256' => $ruleset->sha256,
                'beatmap_id' => $score->beatmap_id,
                'mods' => json_decode(json_encode($data->mods)),
                'statistics' => (object) array_filter($data->statistics->attributes ?? [], fn ($v) => $v > 0),
                'maximum_statistics' => (object) array_filter($data->maximumStatistics->attributes ?? [], fn ($v) => $v > 0),
                'accuracy' => (float) $score->accuracy,
                'passed' => (bool) $score->passed,
            ]);
        } catch (ConnectionException $e) {
            log_error($e);

            return null;
        }

        return $response->successful() ? $response->json() : null;
    }
}
