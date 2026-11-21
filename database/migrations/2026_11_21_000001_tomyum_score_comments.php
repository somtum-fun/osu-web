<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// TomYum: comments on score pages. A website score (solo_score) is commented on as itself; a Relax/Autopilot play
// lives in TomYum, so it gets a row here (made when its page is first shown) for its comments to belong to.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE comments MODIFY commentable_type ENUM('beatmapset','build','news_post','solo_score','tomyum_mode_score') NULL");

        Schema::create('tomyum_mode_scores', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // TomYum's id for the play (/scores/rx/{id})
            $table->unsignedInteger('user_id');
            $table->unsignedMediumInteger('beatmap_id');
            $table->unsignedTinyInteger('ruleset_id');
            $table->timestamps();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_mode_scores');
        DB::statement("ALTER TABLE comments MODIFY commentable_type ENUM('beatmapset','build','news_post') NULL");
    }
};
