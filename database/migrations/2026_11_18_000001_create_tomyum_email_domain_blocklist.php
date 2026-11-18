<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: the disposable email domains sign-ups are refused for (App\Libraries\TomYumRegistrationGuard),
 * downloaded from the lists set on GMT panel → Sign-ups (tomyum:registration-lists-update, daily). The
 * lists run to hundreds of thousands of domains, too many for the cache: the update fills a copy of this
 * table and swaps it in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomyum_email_domain_blocklist', function (Blueprint $table) {
            $table->string('domain', 253)->primary();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_email_domain_blocklist');
        Schema::dropIfExists('tomyum_email_domain_blocklist_new');
        Schema::dropIfExists('tomyum_email_domain_blocklist_old');
    }
};
