<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries\Opengraph\Wiki;

use App\Libraries\Opengraph\OpengraphInterface;
use App\Models\Wiki\Page;

class PageOpengraph implements OpengraphInterface
{
    public function __construct(private Page $page)
    {
    }

    public function get(): array
    {
        if (!$this->page->isVisible()) {
            return [];
        }

        return [
            // TomYum: the text after the page's heading (the title is already the embed's title).
            // The preview comes escaped and the layout escapes it again, so it's decoded here.
            'description' => ltrim(preg_replace(
                '/^'.preg_quote($this->page->title(), '/').'/u',
                '',
                ltrim(html_entity_decode($this->page->getTextPreview(), ENT_QUOTES | ENT_HTML5)),
                1,
            )),
            'title' => $this->page->title(),
        ];
    }
}
