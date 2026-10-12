<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Libraries\TomYumAssets;
use App\Libraries\TomYumCustomMedals;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Illuminate\Console\Command;

/**
 * TomYum: brings bancho.py's custom medals over (for the move from bancho.py): its `achievements` past
 * the built-in ones (their rules are the same language, so they work as they are), their icons, and
 * who unlocked them. Load bancho.py's database next to the website's first (same MySQL server), and
 * keep its .data/assets/medals folder (client/ and pending/) where this can read it. Player ids are
 * taken to be the same as on bancho.py (the account move keeps them); players who aren't here are
 * skipped. Running it again only adds what's new.
 */
class TomYumCustomMedalsImport extends Command
{
    protected $signature = 'tomyum:custom-medals-import
        {--database=banchopy : bancho.py\'s database (on the same server)}
        {--icons= : bancho.py\'s .data/assets/medals folder (with client/ and pending/)}
        {--first=84 : the first custom medal id on bancho.py (its built-in ones come before)}
        {--by= : the staff member recorded as approving them (user id)}
        {--dry-run : only say what would happen}';

    protected $description = 'Imports bancho.py\'s custom medals, their icons and their unlocks.';

    public function handle(): int
    {
        $database = (string) $this->option('database');
        if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            $this->error('The database name is letters, digits and underscores.');

            return 1;
        }
        $icons = rtrim((string) $this->option('icons'), '/');
        if ($icons === '' || !is_dir($icons)) {
            $this->error('Give --icons, bancho.py\'s .data/assets/medals folder.');

            return 1;
        }
        $by = User::find(get_int($this->option('by')));
        if ($by === null) {
            $this->error('Give --by, the user id of the staff member recorded as approving them.');

            return 1;
        }
        $dry = (bool) $this->option('dry-run');
        $assets = new TomYumAssets($by);

        $rows = DB::select("SELECT * FROM `{$database}`.`achievements` WHERE id >= ? ORDER BY id", [get_int($this->option('first')) ?? 84]);
        $this->info(count($rows).' custom medals on bancho.py.');
        $imported = 0;

        foreach ($rows as $row) {
            if (TomYumCustomMedals::table()->where('bancho_id', $row->id)->exists()) {
                $this->line("  #{$row->id} {$row->name}: already here.");
                continue;
            }

            $name = mb_substr(trim($row->name), 0, 40);
            $description = mb_substr(trim($row->desc), 0, 255);
            $category = array_key_exists((string) $row->category, TomYumCustomMedals::CATEGORIES) ? $row->category : 'hush-hush';
            $status = in_array($row->status, ['pending', 'approved', 'rejected'], true) ? $row->status : 'pending';
            $note = [];
            if ($name !== trim($row->name)) {
                $note[] = 'name shortened to 40 characters';
            }
            if (($problem = TomYumCustomMedals::checkRule((string) $row->cond)) !== null) {
                $note[] = "rule can't be read ({$problem})";
                if ($status === 'approved') {
                    $status = 'pending';
                }
            }

            $slug = preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string) $row->file) === 1 ? $row->file : 'custom-'.bin2hex(random_bytes(8));
            $iconFile = collect(["{$icons}/client/{$row->file}.png", "{$icons}/pending/{$row->file}.png"])->first(fn ($path) => is_file($path));
            $this->line("  #{$row->id} {$name} ({$status})".(count($note) > 0 ? ': '.implode('; ', $note) : '').($iconFile === null ? ' [no icon]' : ''));
            if ($dry) {
                continue;
            }

            if ($iconFile !== null) {
                [$normal, $retina, $problem] = TomYumCustomMedals::iconsFromBytes((string) file_get_contents($iconFile));
                if ($problem === null && ($problem = $assets->saveBytes("medals/client/{$slug}.png", $normal, $retina)) !== null) {
                    $this->warn("    The icon couldn't be saved: {$problem}");
                }
            }

            $submitter = User::find((int) $row->submitted_by);
            $id = TomYumCustomMedals::table()->insertGetId([
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'hint' => presence(mb_substr(trim((string) ($row->hint ?? '')), 0, 255)),
                'category' => $category,
                'rule' => (string) $row->cond,
                'allowed_statuses' => presence((string) ($row->allowed_beatmap_statuses ?? '')),
                'status' => $status === 'approved' ? 'pending' : $status,
                'user_id' => $submitter?->getKey(),
                'decision_reason' => $status === 'rejected' ? presence((string) ($row->rejection_reason ?? '')) : null,
                'bancho_id' => $row->id,
                'created_at' => $row->submitted_at ?? Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            if ($status === 'approved') {
                $medal = TomYumCustomMedals::table()->find($id);
                if (($problem = TomYumCustomMedals::approve($medal, $by)) !== null) {
                    $this->warn("    Not approved: {$problem}");
                    continue;
                }
                $this->unlocks($database, (int) $row->id, TomYumCustomMedals::ID_FLOOR + $id);
            }
            $imported++;
        }

        $this->info($dry ? 'Dry run: nothing changed.' : "Imported {$imported}.");

        return 0;
    }

    /** bancho.py's unlocks of a medal, on the website and in TomYum (which then doesn't give it again). */
    private function unlocks(string $database, int $banchoId, int $achievementId): void
    {
        // The importer may have given a player a new id (--remap): its map says which, when it's there.
        $tomyum = $GLOBALS['cfg']['database']['connections']['tomyum']['database'] ?? 'tomyum';
        $mapped = DB::select('SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?', [$tomyum, 'bpy_import_user_map']) !== [];
        $userId = $mapped ? 'COALESCE(m.new_id, ua.userid)' : 'ua.userid';
        $join = $mapped ? "LEFT JOIN `{$tomyum}`.`bpy_import_user_map` m ON m.old_id = ua.userid" : '';
        $unlocks = DB::select(
            "SELECT {$userId} AS userid, ua.unlocked_at FROM `{$database}`.`user_achievements` ua {$join} JOIN phpbb_users u ON u.user_id = {$userId} WHERE ua.achid = ?",
            [$banchoId],
        );
        foreach ($unlocks as $unlock) {
            $at = $unlock->unlocked_at ?? Carbon::now();
            DB::table('osu_user_achievements')->insertOrIgnore(['user_id' => $unlock->userid, 'achievement_id' => $achievementId, 'date' => $at]);
            DB::table('tomyum.user_achievements')->insertOrIgnore(['UserId' => $unlock->userid, 'AchievementId' => $achievementId, 'UnlockedAt' => $at]);
        }
        if (count($unlocks) > 0) {
            DB::table('osu_achievements')->where('achievement_id', $achievementId)->update(['achieved_count' => count($unlocks)]);
            $this->line('    '.count($unlocks).' players had it.');
        }
    }
}
