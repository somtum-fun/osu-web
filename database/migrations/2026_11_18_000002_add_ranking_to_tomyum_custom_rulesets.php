<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: how a custom ruleset's players are ranked, staff's pick (admin panel → Custom rulesets): "pp" (Pla-ra's) or
 * "score" (ranked score, as before Pla-ra). Pla-ra keeps working out pp either way, so a switch back is instant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tomyum_custom_rulesets', 'ranking')) {
            return;
        }
        Schema::table('tomyum_custom_rulesets', function (Blueprint $table) {
            $table->string('ranking', 8)->default('pp')->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tomyum_custom_rulesets', function (Blueprint $table) {
            $table->dropColumn('ranking');
        });
    }
};
