<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: players' daily Pla-ra pp on custom lazer rulesets' boards next to their rank, for the profile's rank graph's
 * pp view (tomyum:custom-ruleset-ranks keeps both).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tomyum_custom_ruleset_rank_history', 'pp')) {
            return;
        }

        Schema::table('tomyum_custom_ruleset_rank_history', function (Blueprint $table) {
            $table->float('pp')->nullable()->after('rank');
        });
    }

    public function down(): void
    {
        Schema::table('tomyum_custom_ruleset_rank_history', function (Blueprint $table) {
            $table->dropColumn('pp');
        });
    }
};
