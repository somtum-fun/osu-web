<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Libraries\TomYumDan;
use App\Models\Beatmap;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Database\QueryException;
use Request;

/**
 * TomYum: osu!dan courses, for admins and the NAT. A course is a dan with its maps and rules (key
 * count, accuracy, allowed mods, no pausing). They're the tomyum.dan plugin's tables in TomYum's
 * database: the plugin creates them on its first start and reads the courses again every minute,
 * so changes here reach the game within a minute (or right away with !dan reload).
 */
class DanController extends Controller
{
    /** The most maps one course may have. */
    private const MAX_MAPS = 20;

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canManageDan(Auth::user()));

            return $next($request);
        });
    }

    public static function canManageDan(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $user->isNAT());
    }

    /**
     * Beatmap ids from links or ids, one per line (or separated by commas or spaces): a difficulty
     * link (…/beatmapsets/1#mania/2, …/beatmaps/2, …/b/2) or a plain id.
     */
    public static function parseBeatmapIds(string $input): array
    {
        $ids = [];
        foreach (preg_split('/[\s,]+/', trim($input)) as $part) {
            if ($part === '') {
                continue;
            }
            if (ctype_digit($part)) {
                $ids[] = (int) $part;
            } elseif (preg_match('~(?:#[a-z]+/|/beatmaps/|/b/)(\d+)~', $part, $match) === 1) {
                $ids[] = (int) $match[1];
            } else {
                return [];
            }
        }

        return array_values(array_unique($ids));
    }

    public function index()
    {
        $available = TomYumDan::available();

        return ext_view('panel.dan.index', [
            'available' => $available,
            'clearCounts' => $available ? TomYumDan::clearCounts(false) : [],
            'courses' => $available ? TomYumDan::courses(false) : [],
        ]);
    }

    public function show(string $course)
    {
        $found = ctype_digit($course) ? TomYumDan::course((int) $course) : null;
        abort_if($found === null, 404);

        try {
            $attempts = DB::table(TomYumDan::table('attempts'))
                ->where('course_id', $found->id)
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        } catch (QueryException) {
            $attempts = collect();
        }
        $users = User::whereIn('user_id', $attempts->pluck('user_id'))->get()->keyBy('user_id');

        return ext_view('panel.dan.show', [
            'attempts' => $attempts,
            'clears' => TomYumDan::clearCounts(false)[(int) $found->id] ?? 0,
            'course' => $found,
            'users' => $users,
        ]);
    }

    public function store()
    {
        if (!TomYumDan::available()) {
            return $this->back(null, 'error', "The game server's osu!dan plugin hasn't made its tables yet. Turn it on first.");
        }

        [$fields, $mapIds, $error] = $this->read();
        if ($error !== null) {
            return $this->back(null, 'error', $error);
        }

        $now = Carbon::now('UTC');
        $id = DB::transaction(function () use ($fields, $mapIds, $now) {
            $id = DB::table(TomYumDan::table('courses'))->insertGetId([
                ...$fields,
                'created_by' => Auth::user()->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->saveMaps($id, $mapIds);

            return $id;
        });

        $summary = "Made the osu!dan course {$fields['name']} ({$fields['keys']}K)";
        $this->staffLog('dan.course.create', null, $summary.' with maps '.implode(', ', $mapIds));

        return $this->back($id, 'success', $summary.'. The game picks it up within a minute.');
    }

    public function update(string $course)
    {
        $found = ctype_digit($course) ? TomYumDan::course((int) $course) : null;
        abort_if($found === null, 404);

        [$fields, $mapIds, $error] = $this->read();
        if ($error !== null) {
            return $this->back((int) $found->id, 'error', $error);
        }

        DB::transaction(function () use ($found, $fields, $mapIds) {
            DB::table(TomYumDan::table('courses'))->where('id', $found->id)->update([
                ...$fields,
                'updated_at' => Carbon::now('UTC'),
            ]);
            $this->saveMaps((int) $found->id, $mapIds);
        });

        $summary = "Changed the osu!dan course {$fields['name']} ({$fields['keys']}K)";
        $this->staffLog('dan.course.update', null, $summary.': '.json_encode([...$fields, 'maps' => $mapIds]));

        return $this->back((int) $found->id, 'success', $summary.'. The game picks it up within a minute.');
    }

    public function destroy(string $course)
    {
        $found = ctype_digit($course) ? TomYumDan::course((int) $course) : null;
        abort_if($found === null, 404);

        // Clears are players' achievements: a cleared course is only switched off.
        // Restricted players' clears count too: they come back if the restriction is lifted.
        if ((TomYumDan::clearCounts(false)[(int) $found->id] ?? 0) > 0) {
            return $this->back((int) $found->id, 'error', 'Players have cleared this course, so it can only be switched off (untick "Active"), which keeps their clears.');
        }

        DB::transaction(function () use ($found) {
            DB::table(TomYumDan::table('course_maps'))->where('course_id', $found->id)->delete();
            DB::table(TomYumDan::table('attempts'))->where('course_id', $found->id)->delete();
            DB::table(TomYumDan::table('courses'))->where('id', $found->id)->delete();
        });

        $summary = "Removed the osu!dan course {$found->name} ({$found->keys}K)";
        $this->staffLog('dan.course.remove', null, $summary);

        return $this->back(null, 'success', $summary.'.');
    }

    /**
     * The course from the form.
     *
     * @return array{0: array, 1: int[], 2: ?string} the columns, the map ids, or why the form isn't right
     */
    private function read(): array
    {
        $name = trim(get_string(Request::input('name')) ?? '');
        $category = trim(get_string(Request::input('category')) ?? '');
        $description = trim(get_string(Request::input('description')) ?? '');
        $keys = get_int(Request::input('keys'));
        $sortOrder = get_int(Request::input('sort_order')) ?? 0;
        $accuracy = Request::input('min_accuracy');
        $mods = 0;
        foreach ((array) Request::input('mods', []) as $bit) {
            if (in_array((int) $bit, array_column(TomYumDan::MODS, 0), true)) {
                $mods |= (int) $bit;
            }
        }
        $mapIds = static::parseBeatmapIds(get_string(Request::input('maps')) ?? '');

        $error = match (true) {
            $name === '' || mb_strlen($name) > 100 => 'Give the course a name (up to 100 characters).',
            mb_strlen($category) > 32 => 'Keep the group name short (up to 32 characters).',
            mb_strlen($description) > 500 => 'Keep the description short (up to 500 characters).',
            $keys === null || $keys < 1 || $keys > 18 => 'Pick the key count (1 to 18).',
            !is_numeric($accuracy) || (float) $accuracy < 0 || (float) $accuracy > 100 => 'The accuracy is a percentage from 0 to 100.',
            abs($sortOrder) > 100000 => 'Keep the order between -100000 and 100000.',
            count($mapIds) === 0 => 'Add the course\'s maps: beatmap links or ids, one per line.',
            count($mapIds) > static::MAX_MAPS => 'A course can have up to '.static::MAX_MAPS.' maps.',
            default => null,
        };
        if ($error === null) {
            $error = $this->checkMaps($mapIds, $keys);
        }

        return [[
            'name' => $name,
            'category' => $category,
            'description' => $description === '' ? null : $description,
            'mode' => 3,
            'keys' => $keys,
            'sort_order' => $sortOrder,
            'is_intro' => Request::boolean('is_intro') ? 1 : 0,
            'min_accuracy' => round((float) $accuracy, 2),
            'allowed_mods' => $mods,
            'no_pause' => Request::boolean('no_pause') ? 1 : 0,
            'active' => Request::boolean('active') ? 1 : 0,
        ], $mapIds, $error];
    }

    /** Why the maps can't be a course's: not on the site, not osu!mania, or another key count. */
    private function checkMaps(array $mapIds, int $keys): ?string
    {
        $beatmaps = Beatmap::whereIn('beatmap_id', $mapIds)->get()->keyBy('beatmap_id');
        foreach ($mapIds as $id) {
            $beatmap = $beatmaps[$id] ?? null;
            if ($beatmap === null) {
                return "The site doesn't have beatmap {$id}. Import or upload its set in the BN panel first.";
            }
            if ((int) $beatmap->playmode !== Beatmap::MODES['mania']) {
                return "Beatmap {$id} ({$beatmap->version}) isn't an osu!mania map.";
            }
            if ((int) round((float) $beatmap->diff_size) !== $keys) {
                return "Beatmap {$id} ({$beatmap->version}) is ".((int) round((float) $beatmap->diff_size))."K, not {$keys}K.";
            }
        }

        return null;
    }

    private function saveMaps(int $courseId, array $mapIds): void
    {
        DB::table(TomYumDan::table('course_maps'))->where('course_id', $courseId)->delete();
        $rows = [];
        foreach ($mapIds as $index => $id) {
            $rows[] = ['course_id' => $courseId, 'position' => $index + 1, 'beatmap_id' => $id];
        }
        DB::table(TomYumDan::table('course_maps'))->insert($rows);
    }

    private function back(?int $courseId, string $type, string $message)
    {
        $url = $courseId === null ? route('panel.dan.index') : route('panel.dan.show', ['course' => $courseId]);

        $response = redirect($url)->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);

        // A form that wasn't right comes back filled in. Only text fields and the mods' list:
        // an array where the form has text (name[]=...) would break the page it comes back to.
        if ($type !== 'error') {
            return $response;
        }
        $input = [];
        foreach (Request::except('_token') as $key => $value) {
            if ($key === 'mods') {
                $input[$key] = array_values(array_filter((array) $value, 'is_scalar'));
            } elseif (is_scalar($value)) {
                $input[$key] = $value;
            }
        }

        return $response->withInput($input);
    }
}
