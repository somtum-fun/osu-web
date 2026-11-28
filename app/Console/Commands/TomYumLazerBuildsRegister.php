<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Libraries\TomYumDownloads;
use App\Libraries\TomYumLazerBuilds;
use Illuminate\Console\Command;

/**
 * TomYum: registers the TumThai releases in the download manifest as client builds (TomYumLazerBuilds). Run by
 * scripts/dl-publish.py after a lazer release; safe to run again.
 */
class TomYumLazerBuildsRegister extends Command
{
    protected $signature = 'tomyum:lazer-builds
        {--manifest= : a manifest.json file instead of the download site\'s ("-": read it from stdin)}
        {--dry-run : show what would change and write nothing}
        {--build-json=* : instead, release build.json files (lazer-release.sh output; dated by the file), for releases made before this}';

    protected $description = 'Registers the TumThai releases in the download manifest as client builds.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $buildJsons = $this->option('build-json');
        if ($buildJsons !== []) {
            foreach ($buildJsons as $path) {
                $build = json_decode((string) file_get_contents($path), true);
                $key = ($build['stream'] ?? '') === 'next' ? 'lazer_next' : 'lazer';
                $entry = [$key => [
                    'version' => $build['version'] ?? null,
                    'version_hash' => $build['version_hash'] ?? null,
                    'date' => date('Y-m-d H:i:s', (int) filemtime($path)),
                ]];
                foreach (TomYumLazerBuilds::register($entry, $dryRun) as $line) {
                    if (str_contains($line, ' build ')) {
                        $this->line($line);
                    }
                }
            }

            return 0;
        }

        $file = $this->option('manifest');
        if (present($file)) {
            $manifest = json_decode((string) file_get_contents($file === '-' ? 'php://stdin' : $file), true);
        } else {
            \Cache::forget('tomyum_downloads_manifest:'.md5(TomYumDownloads::baseUrl()));
            $manifest = TomYumDownloads::manifest();
        }

        if (!is_array($manifest)) {
            $this->error('No manifest.');

            return 1;
        }

        if ($dryRun) {
            $this->line('dry run: nothing is written');
        }
        foreach (TomYumLazerBuilds::register($manifest, $dryRun) as $line) {
            $this->line($line);
        }

        return 0;
    }
}
