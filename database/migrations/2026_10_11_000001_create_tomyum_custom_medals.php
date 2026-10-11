<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use App\Models\Group;
use App\Models\Rank;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: custom medals (TomYumCustomMedals). Players ask for a medal (name, description, icon and an
 * unlock rule in bancho.py's condition language); the Medal Team (a new listed group, after Project
 * Loved, with its title) approves or rejects it; approved ones get an osu_achievements row numbered
 * from 100000 and the game server awards them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tomyum_custom_medals')) {
            Schema::create('tomyum_custom_medals', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('achievement_id')->nullable()->unique();
                $table->string('slug', 64)->unique();
                $table->string('name', 40);
                $table->string('description', 255);
                $table->string('hint', 255)->nullable();
                $table->string('category', 32);
                $table->text('rule');
                $table->string('allowed_statuses', 32)->nullable();
                $table->string('status', 16)->default('pending')->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->unsignedInteger('decided_by')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->string('decision_reason', 255)->nullable();
                // The bancho.py achievement it came from (tomyum:custom-medals-import), if any.
                $table->unsignedInteger('bancho_id')->nullable()->unique();
                $table->timestamps();
            });
        }

        if (Group::where('identifier', 'medal')->doesntExist()) {
            Group::whereNotNull('display_order')
                ->where('display_order', '>=', 8)
                ->increment('display_order');

            Group::create([
                'colour' => '9CCB6B',
                'display_order' => 8,
                'group_colour' => '9CCB6B',
                'group_desc' => '',
                'group_name' => 'Medal Team',
                'group_type' => 1,
                'has_playmodes' => false,
                'identifier' => 'medal',
                'short_name' => 'MDL',
            ]);
        }

        if (Rank::where('rank_special', 1)->where('rank_title', 'Medal Team')->doesntExist()) {
            Rank::create([
                'rank_image' => '',
                'rank_min' => 0,
                'rank_special' => 1,
                'rank_title' => 'Medal Team',
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_custom_medals');
        $group = Group::where('identifier', 'medal')->first();
        if ($group !== null) {
            $group->delete();
            Group::whereNotNull('display_order')
                ->where('display_order', '>', 8)
                ->decrement('display_order');
        }
    }
};
