<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Libraries;

use Cache;
use Exception;
use Http;

/**
 * TomYum: what the download page offers, from the file host's manifest.json (dl.blueskychan.dev):
 * TumThai (lazer) for Windows and Linux, the Somtum Patcher, the hook, and the patched
 * legacy clients (2008 to 2015).
 */
class TomYumDownloads
{
    const PATCHER_URL = 'https://github.com/osu-somtum/osu-somtum-patcher/releases/download/v1.1/patcher-ui.exe';

    public static function baseUrl(): string
    {
        // TomYum: Admin panel > Branding, else TOMYUM_DL_URL.
        return TomYumBranding::downloadsHost();
    }

    public static function manifest(): array
    {
        // Per host: changing it in the Branding page shows the new host's files at once.
        $cacheKey = 'tomyum_downloads_manifest:'.md5(static::baseUrl());
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $manifest = Http::timeout(5)->get(static::baseUrl().'/manifest.json')->throw()->json();
        } catch (Exception $e) {
            $manifest = null;
        }

        if (!is_array($manifest)) {
            // Try again in a minute; the page still has the patcher and a link to the file host.
            Cache::put($cacheKey, [], 60);

            return [];
        }

        Cache::put($cacheKey, $manifest, 600);

        return $manifest;
    }

    /** The legacy clients by year, newest first. */
    public static function legacyByYear(array $manifest): array
    {
        $years = [];
        foreach ($manifest['legacy'] ?? [] as $build) {
            // A list without years (a dev publish) would put every build under one blank heading: take a dated build's
            // year from its name (b20151228.3), else group it under "Other".
            $year = $build['year'] ?? null;
            if ($year === null && preg_match('/^b(\d{4})\d{4}/', $build['build'] ?? '', $m) === 1) {
                $year = (int) $m[1];
            }
            $years[$year ?? osu_trans('home.download.tomyum.legacy.other_year')][] = $build;
        }
        // Newest year first; builds without a year last.
        $other = osu_trans('home.download.tomyum.legacy.other_year');
        $rest = $years[$other] ?? null;
        unset($years[$other]);
        krsort($years);
        if ($rest !== null) {
            $years[$other] = $rest;
        }

        return $years;
    }

    public static function size(?int $bytes): string
    {
        return $bytes === null ? '' : number_format($bytes / 1048576, 1).' MB';
    }
}
