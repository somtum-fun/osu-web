<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Models;

/**
 * TomYum: a custom lazer ruleset staff enabled (App\Libraries\TomYumCustomRulesets), with its pinned release.
 *
 * @property string $assembly
 * @property \Carbon\Carbon|null $created_at
 * @property string|null $description
 * @property string $download_url
 * @property bool $enabled
 * @property string|null $icon_url
 * @property int $id
 * @property string $name
 * @property \Carbon\Carbon|null $pinned_at
 * @property string $ranking "pp" (Pla-ra's) or "score": how its players are ranked
 * @property int|null $pinned_by
 * @property string $sha256
 * @property int $size
 * @property string $slug
 * @property string|null $source_url
 * @property \Carbon\Carbon|null $updated_at
 * @property string|null $version
 */
class TomYumCustomRuleset extends Model
{
    public $incrementing = false;

    protected $casts = [
        'enabled' => 'boolean',
        'pinned_at' => 'datetime',
    ];
    protected $table = 'tomyum_custom_rulesets';

    /** True when its players are ranked by Pla-ra's pp (staff's pick); false: by ranked score, and pp isn't shown. */
    public function ranksByPp(): bool
    {
        return ($this->ranking ?? 'pp') !== 'score';
    }
}
