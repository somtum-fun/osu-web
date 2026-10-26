<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: players' daily global rank on custom lazer rulesets' boards (Normal, Relax, Autopilot), for their
 * profile's rank graph (tomyum:custom-ruleset-ranks keeps it, once a day).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tomyum_custom_ruleset_rank_history')) {
            return;
        }

        Schema::create('tomyum_custom_ruleset_rank_history', function (Blueprint $table) {
            $table->unsignedInteger('user_id');
            $table->unsignedSmallInteger('ruleset_id');
            $table->string('variant', 16);
            $table->date('date');
            $table->unsignedInteger('rank');
            $table->primary(['user_id', 'ruleset_id', 'variant', 'date']);
            $table->index(['ruleset_id', 'variant', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_custom_ruleset_rank_history');
    }
};
