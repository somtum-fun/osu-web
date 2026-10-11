<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\User;
use Carbon\Carbon;
use DB;
use Illuminate\Http\UploadedFile;

/**
 * TomYum: custom medals. A player asks for one (its name, description, hint, category, icon and an
 * unlock rule); the Medal Team (or an admin) approves or rejects it; an approved one gets an
 * osu_achievements row (numbered from ID_FLOOR, in the "Community" group) and the game server
 * awards it to new plays that meet its rule (stable at submission, lazer after processing).
 *
 * Rules are bancho.py's achievement conditions (somtum.fun's medals were made there): a small part
 * of Python over one play, like `score.perfect and mode_vn == 0 and not score.mods & 1`. The game
 * server reads them (TomYum's MedalRule), so it also checks them here (/medals/rule).
 */
class TomYumCustomMedals
{
    /** Custom medals' ids in osu_achievements start here (clear of osu!'s; TomYum keeps them). */
    public const ID_FLOOR = 100000;

    /** Their group on profiles. */
    public const GROUPING = 'Community';

    /** bancho.py's categories. */
    public const CATEGORIES = [
        'skill' => 'Skill',
        'dedication' => 'Dedication',
        'hush-hush' => 'Hush-Hush',
        'beatmap' => 'Beatmap',
        'mapper' => 'Mapper',
        'artist' => 'Artist',
    ];

    /** Map statuses a medal can be limited to (bancho.py's numbers). None ticked: ranked, approved, qualified, loved. */
    public const MAP_STATUSES = [2 => 'Ranked', 3 => 'Approved', 4 => 'Qualified', 5 => 'Loved', 0 => 'Pending (unranked)'];

    public const STATES = ['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected', 'disabled' => 'Taken out'];

    /** Requests a player can send (bancho.py's limits). */
    public const PER_HOUR = 2;
    public const PER_DAY = 5;

    /** The icon sizes (osu!'s medal icons). */
    public const ICON_SIZE = [115, 126];
    public const ICON_SIZE_2X = [230, 251];

    public static function isTeam(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $user->isMedalTeam());
    }

    public static function iconUrl(object $medal): string
    {
        return TomYumAssets::url("medals/client/{$medal->slug}.png");
    }

    public static function table()
    {
        return DB::table('tomyum_custom_medals');
    }

    /** null when the game server can read the rule; otherwise what's wrong with it. */
    public static function checkRule(string $rule): ?string
    {
        $rule = trim($rule);
        if ($rule === '') {
            return 'Give the medal a rule.';
        }
        try {
            $response = (new TomYumAdmin())->post('/medals/rule', ['rule' => $rule]);
        } catch (\Throwable $e) {
            return 'The game server didn\'t answer, so the rule couldn\'t be checked. Please try again in a moment.';
        }
        if (!$response->successful()) {
            return 'The rule couldn\'t be checked right now. Please try again in a moment.';
        }

        return $response->json('valid') ? null : 'The rule isn\'t one the game can read: '.$response->json('error').'.';
    }

    /**
     * The fields of a request or an edit, checked: [fields, null] or [null, the problem].
     * $except: the medal being edited (its own name doesn't count as taken).
     */
    public static function fields(array $input, ?int $except = null): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $hint = presence(trim((string) ($input['hint'] ?? '')));
        $category = (string) ($input['category'] ?? '');
        $rule = trim(str_replace(["\r", "\n"], ' ', (string) ($input['rule'] ?? '')));
        $statuses = array_values(array_intersect(
            array_map('intval', (array) ($input['statuses'] ?? [])),
            array_keys(static::MAP_STATUSES),
        ));
        sort($statuses);

        if ($name === '' || mb_strlen($name) > 40) {
            return [null, 'The name is 1 to 40 characters.'];
        }
        if ($description === '' || mb_strlen($description) > 255) {
            return [null, 'The description is 1 to 255 characters.'];
        }
        if ($hint !== null && mb_strlen($hint) > 255) {
            return [null, 'The hint is up to 255 characters.'];
        }
        if (!array_key_exists($category, static::CATEGORIES)) {
            return [null, 'Pick a category.'];
        }
        if (mb_strlen($rule) > 2000) {
            return [null, 'The rule is up to 2000 characters.'];
        }
        if (($problem = static::checkRule($rule)) !== null) {
            return [null, $problem];
        }

        $taken = DB::table('osu_achievements')->where('name', $name)
            ->when($except !== null, fn ($q) => $q->where('achievement_id', '<>', static::ID_FLOOR + $except))
            ->exists()
            || static::table()->where('name', $name)->whereIn('status', ['pending', 'approved'])
                ->when($except !== null, fn ($q) => $q->where('id', '<>', $except))
                ->exists();
        if ($taken) {
            return [null, 'There\'s already a medal with that name.'];
        }

        return [[
            'name' => $name,
            'description' => $description,
            'hint' => $hint,
            'category' => $category,
            'rule' => $rule,
            'allowed_statuses' => count($statuses) === 0 ? null : implode(',', $statuses),
        ], null];
    }

    /** A player's request: [the new medal's id, null] or [null, the problem]. */
    public static function submit(User $user, array $input, ?UploadedFile $icon): array
    {
        if ($user->isRestricted() || $user->isSilenced()) {
            return [null, 'You can\'t ask for medals right now.'];
        }
        $recent = fn (int $hours) => static::table()->where('user_id', $user->getKey())
            ->where('created_at', '>=', Carbon::now()->subHours($hours))->count();
        if ($recent(1) >= static::PER_HOUR || $recent(24) >= static::PER_DAY) {
            return [null, 'You can ask for '.static::PER_HOUR.' medals an hour and '.static::PER_DAY.' a day. Please try again later.'];
        }

        [$fields, $problem] = static::fields($input);
        if ($problem !== null) {
            return [null, $problem];
        }

        [$normal, $retina, $problem] = static::icons($icon);
        if ($problem !== null) {
            return [null, $problem];
        }

        $slug = 'custom-'.bin2hex(random_bytes(8));
        if (($problem = (new TomYumAssets($user))->saveBytes("medals/client/{$slug}.png", $normal, $retina)) !== null) {
            return [null, "The icon couldn't be saved: {$problem}"];
        }

        $id = static::table()->insertGetId([
            ...$fields,
            'slug' => $slug,
            'status' => 'pending',
            'user_id' => $user->getKey(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return [$id, null];
    }

    /** A player takes back their request while it's waiting. */
    public static function withdraw(User $user, int $id): ?string
    {
        $medal = static::table()->where('id', $id)->where('user_id', $user->getKey())->where('status', 'pending')->first();
        if ($medal === null) {
            return 'That request isn\'t waiting any more.';
        }
        static::table()->where('id', $id)->delete();
        (new TomYumAssets($user))->remove("medals/client/{$medal->slug}.png");

        return null;
    }

    /** The Medal Team edits a medal (its text, rule or map statuses), before or after deciding. */
    public static function edit(object $medal, array $input): ?string
    {
        [$fields, $problem] = static::fields($input, $medal->id);
        if ($problem !== null) {
            return $problem;
        }

        DB::transaction(function () use ($medal, $fields) {
            static::table()->where('id', $medal->id)->update([...$fields, 'updated_at' => Carbon::now()]);
            if ($medal->achievement_id !== null) {
                DB::table('osu_achievements')->where('achievement_id', $medal->achievement_id)->update([
                    'name' => $fields['name'],
                    'description' => $fields['description'],
                    'quest_instructions' => $fields['hint'],
                ]);
            }
        });
        static::refreshGame();

        return null;
    }

    /** Approves a waiting medal (or puts a taken-out one back): it's on profiles and can be earned. */
    public static function approve(object $medal, User $staff): ?string
    {
        if (!in_array($medal->status, ['pending', 'rejected', 'disabled'], true)) {
            return 'That medal is already out.';
        }
        if (($problem = static::checkRule($medal->rule)) !== null) {
            return $problem;
        }

        $achievementId = static::ID_FLOOR + (int) $medal->id;
        DB::transaction(function () use ($medal, $staff, $achievementId) {
            DB::table('osu_achievements')->upsert([[
                'achievement_id' => $achievementId,
                'name' => $medal->name,
                'slug' => $medal->slug,
                'description' => $medal->description,
                'image' => "{$medal->slug}.png",
                'grouping' => static::GROUPING,
                'ordering' => 0,
                'progression' => min(255, (int) $medal->id),
                'enabled' => 1,
                'mode' => null,
                'quest_instructions' => $medal->hint,
            ]], ['achievement_id'], ['name', 'slug', 'description', 'image', 'grouping', 'enabled', 'quest_instructions']);
            static::table()->where('id', $medal->id)->update([
                'achievement_id' => $achievementId,
                'status' => 'approved',
                'decided_by' => $staff->getKey(),
                'decided_at' => Carbon::now(),
                'decision_reason' => null,
                'updated_at' => Carbon::now(),
            ]);
        });
        static::refreshGame();

        return null;
    }

    /** Rejects a waiting medal, with why (the player sees it). */
    public static function reject(object $medal, User $staff, string $reason): ?string
    {
        if ($medal->status !== 'pending') {
            return 'Only a waiting medal can be rejected (take an approved one out instead).';
        }
        static::table()->where('id', $medal->id)->update([
            'status' => 'rejected',
            'decided_by' => $staff->getKey(),
            'decided_at' => Carbon::now(),
            'decision_reason' => presence(mb_substr(trim($reason), 0, 255)),
            'updated_at' => Carbon::now(),
        ]);

        return null;
    }

    /** Takes an approved medal out: hidden from profiles and no longer earned (those who have it keep it). */
    public static function disable(object $medal, User $staff, string $reason): ?string
    {
        if ($medal->status !== 'approved') {
            return 'That medal isn\'t out.';
        }
        DB::transaction(function () use ($medal, $staff, $reason) {
            DB::table('osu_achievements')->where('achievement_id', $medal->achievement_id)->update(['enabled' => 0]);
            static::table()->where('id', $medal->id)->update([
                'status' => 'disabled',
                'decided_by' => $staff->getKey(),
                'decided_at' => Carbon::now(),
                'decision_reason' => presence(mb_substr(trim($reason), 0, 255)),
                'updated_at' => Carbon::now(),
            ]);
        });
        static::refreshGame();

        return null;
    }

    /** How many players have each approved medal: achievement_id => count. */
    public static function holders(array $achievementIds): array
    {
        if (count($achievementIds) === 0) {
            return [];
        }

        return DB::table('osu_user_achievements')->whereIn('achievement_id', $achievementIds)
            ->groupBy('achievement_id')->selectRaw('achievement_id, COUNT(*) AS n')->pluck('n', 'achievement_id')->all();
    }

    /**
     * The uploaded icon as osu!'s two sizes (fitted, centred on transparency): [normal, retina, null]
     * or [null, null, the problem].
     */
    public static function icons(?UploadedFile $file): array
    {
        if ($file === null || !$file->isValid()) {
            return [null, null, 'Add an icon (PNG, JPG or WebP).'];
        }
        if ($file->getSize() > 2 * 1024 * 1024) {
            return [null, null, 'The icon can be up to 2 MB.'];
        }

        return static::iconsFromBytes((string) file_get_contents($file->getRealPath()));
    }

    public static function iconsFromBytes(string $bytes): array
    {
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return [null, null, 'The icon isn\'t an image (use PNG, JPG or WebP).'];
        }
        $width = imagesx($source);
        $height = imagesy($source);
        if ($width > 2000 || $height > 2000 || $width < 16 || $height < 16) {

            return [null, null, 'The icon is 16 to 2000 pixels each way.'];
        }

        $fit = function (array $size) use ($source, $width, $height): string {
            [$w, $h] = $size;
            $image = imagecreatetruecolor($w, $h);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
            imagealphablending($image, true);
            $scale = min($w / $width, $h / $height);
            $dw = max(1, (int) round($width * $scale));
            $dh = max(1, (int) round($height * $scale));
            imagecopyresampled($image, $source, intdiv($w - $dw, 2), intdiv($h - $dh, 2), 0, 0, $dw, $dh, $width, $height);
            ob_start();
            imagepng($image);

            return (string) ob_get_clean();
        };

        $normal = $fit(static::ICON_SIZE);
        $retina = $fit(static::ICON_SIZE_2X);

        return [$normal, $retina, null];
    }

    /** Tells the game server to read the medals again now (it would within a minute anyway). */
    private static function refreshGame(): void
    {
        try {
            (new TomYumAdmin())->post('/medals/refresh');
        } catch (\Throwable) {
            // It reads them again within a minute.
        }
    }
}
