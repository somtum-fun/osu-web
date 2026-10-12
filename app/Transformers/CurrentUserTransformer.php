<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Transformers;

class CurrentUserTransformer extends UserTransformer
{
    public function __construct()
    {
        $this->defaultIncludes = [
            ...$this->defaultIncludes,
            'blocks',
            'follow_user_mapping',
            'friends',
            'groups',
            'is_admin',
            'team',
            'unread_pm_count',
            'user_preferences',
        ];
    }

    // TomYum: osu!direct for supporters and voters with time left (TomYumVotes), for its download buttons.
    public function transform(\App\Models\User $user)
    {
        $json = parent::transform($user);
        $json['osu_direct'] = \App\Libraries\TomYumVotes::hasDirect($user);

        return $json;
    }
}
