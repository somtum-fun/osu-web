<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// TomYum: more playstyles (User::PLAYSTYLES) than fit in a tinyint bitmask; touchpad is bit 256.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE phpbb_users MODIFY osu_playstyle SMALLINT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('UPDATE phpbb_users SET osu_playstyle = osu_playstyle & 255');
        DB::statement('ALTER TABLE phpbb_users MODIFY osu_playstyle TINYINT UNSIGNED NOT NULL DEFAULT 0');
    }
};
