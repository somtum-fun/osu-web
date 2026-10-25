// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

// TomYum: custom lazer rulesets (played in TumThai) as items of a mode bar (PlaymodeTabs' append): the one shown,
// then a "…" listing them all. Their logos are drawn in the bar's own colour, as osu!'s mode icons are.

import PopupMenu from 'components/popup-menu';
import PopupMenuState from 'components/popup-menu-state';
import * as React from 'react';

export interface CustomRulesetEntry {
  href?: string;
  icon: string | null;
  name: string;
  slug: string;
}

interface Props {
  current: string | null;
  entries: CustomRulesetEntry[];
  // Picks one in place (the link is followed when not given).
  onSelect?: (slug: string) => void;
}

// A ruleset's logo as a shape in the text's colour (its transparent parts left out).
export function CustomRulesetIcon({ icon, name }: Pick<CustomRulesetEntry, 'icon' | 'name'>) {
  if (icon == null) {
    return <span className='fas fa-puzzle-piece' title={name} />;
  }

  const mask = `url("${icon}") center / contain no-repeat`;

  return (
    <span
      title={name}
      style={{ backgroundColor: 'currentColor', display: 'inline-block', height: '1em', mask, verticalAlign: 'middle', WebkitMask: mask, width: '1em' }}
    />
  );
}

export default class CustomRulesetTabs extends React.Component<Props> {
  private readonly menu = new PopupMenuState();

  render() {
    if (this.props.entries.length === 0) {
      return null;
    }

    const current = this.props.entries.find((entry) => entry.slug === this.props.current);

    return (
      <>
        {current != null &&
          <li>
            <a className='game-mode-link game-mode-link--active' href={current.href ?? '#'} onClick={this.onClickCurrent}>
              <CustomRulesetIcon icon={current.icon} name={current.name} />
            </a>
          </li>
        }
        <li>
          <button
            ref={this.menu.setButtonRef}
            className='game-mode-link'
            onClick={this.menu.toggle}
            style={{ background: 'none', border: 0, font: 'inherit' }}
            title='Custom lazer rulesets, played in TumThai'
            type='button'
          >
            <span className='fas fa-ellipsis-h' />
          </button>
          <PopupMenu skipButton state={this.menu}>
            {() => (
              <div className='simple-menu'>
                {this.props.entries.map((entry) => (
                  <a
                    key={entry.slug}
                    className='simple-menu__item'
                    data-slug={entry.slug}
                    href={entry.href ?? '#'}
                    onClick={this.onClickEntry}
                  >
                    <span style={{ alignItems: 'center', display: 'inline-flex', gap: 8 }}>
                      <CustomRulesetIcon icon={entry.icon} name={entry.name} />
                      {entry.name}
                    </span>
                  </a>
                ))}
              </div>
            )}
          </PopupMenu>
        </li>
      </>
    );
  }

  private readonly onClickCurrent = (e: React.MouseEvent<HTMLAnchorElement>) => {
    if (this.props.onSelect != null) {
      e.preventDefault();
    }
  };

  private readonly onClickEntry = (e: React.MouseEvent<HTMLAnchorElement>) => {
    this.menu.dismiss();
    if (this.props.onSelect != null) {
      e.preventDefault();
      this.props.onSelect(e.currentTarget.dataset.slug ?? '');
    }
  };
}
