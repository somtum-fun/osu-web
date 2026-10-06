<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Libraries\TomYumDan;

/**
 * TomYum: osu!dan (Community → osu!dan): the rules, the courses with their maps and how many cleared
 * them, the latest clears, and each course's clears. The game server's tomyum.dan plugin watches the
 * attempts and records the clears (App\Libraries\TomYumDan); staff edit the courses in the panel.
 */
class DanController extends Controller
{
    public function index()
    {
        $courses = TomYumDan::courses();
        $byId = [];
        foreach ($courses as $course) {
            $byId[(int) $course->id] = $course;
        }

        return ext_view('dan.index', [
            'clearCounts' => TomYumDan::clearCounts(),
            'coursesByKeys' => collect($courses)->groupBy('keys')->all(),
            'coursesById' => $byId,
            // Only clears of the courses shown: with switched-off ones the heading had an empty list.
            'recentClears' => array_values(array_filter(
                TomYumDan::recentClears(10),
                fn ($clear) => isset($byId[(int) $clear->course_id]),
            )),
        ]);
    }

    public function show(string $course)
    {
        $found = ctype_digit($course) ? TomYumDan::course((int) $course) : null;
        // Switched-off courses keep their page: profiles link to players' clears of them.
        abort_if($found === null, 404);

        return ext_view('dan.show', [
            'clears' => TomYumDan::clears((int) $found->id, 500),
            'course' => $found,
        ]);
    }
}
