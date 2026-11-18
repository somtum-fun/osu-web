<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Console\Commands;

use App\Libraries\TomYumRegistrationGuard;
use Illuminate\Console\Command;

/** TomYum: downloads the hosting network, Tor exit and disposable email domain lists the sign-up rules use (TomYumRegistrationGuard). */
class TomYumRegistrationListsUpdate extends Command
{
    protected $signature = 'tomyum:registration-lists-update';

    protected $description = 'Downloads the hosting network (AS), Tor exit and disposable email domain lists that sign-ups are checked against.';

    public function handle()
    {
        [$hosting, $tor, $email] = TomYumRegistrationGuard::updateLists();
        $this->info("{$hosting} hosting networks, {$tor} Tor exits, {$email} disposable email domains.");
    }
}
