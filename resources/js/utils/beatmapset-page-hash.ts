// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import BeatmapJson from 'interfaces/beatmap-json';
import Ruleset, { ensureRuleset } from 'interfaces/ruleset';
import { getInt } from './math';
import { currentUrl } from './turbolinks';

export function parse(hash: string) {
  const [mode, id] = hash.slice(1).split('/');
  const playmode = ensureRuleset(mode);

  return {
    beatmapId: getInt(id),
    // TomYum: a custom lazer ruleset's board (its slug in place of the mode: #tau/123).
    customRuleset: playmode == null && /^[a-z0-9_-]{1,64}$/i.test(mode ?? '') ? mode : null,
    playmode,
  };
}

export function generate({ beatmap, customRuleset, ruleset }: { beatmap?: BeatmapJson; customRuleset?: string | null; ruleset?: Ruleset }) {
  let hash = '';

  // TomYum: a custom lazer ruleset's board is linked by its slug (on an osu! difficulty).
  const mode = customRuleset ?? ruleset ?? beatmap?.mode;
  if (mode != null) {
    hash += `#${mode}`;

    if (beatmap != null) {
      hash += `/${beatmap.id}`;
    }
  }

  return hash;
}

export function setHash(newHash: string) {
  const currUrl = currentUrl().href;
  const newUrl = `${currUrl.replace(/#.*/, '')}${newHash}`;

  if (newUrl === currUrl) return;

  history.replaceState(history.state, '', newUrl);
}
