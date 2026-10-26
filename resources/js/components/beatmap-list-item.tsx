// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import UserLinkList from 'beatmap-discussions/user-link-list';
import DifficultyBadge from 'components/difficulty-badge';
import BeatmapExtendedJson from 'interfaces/beatmap-extended-json';
import BeatmapJson from 'interfaces/beatmap-json';
import BeatmapsetJson from 'interfaces/beatmapset-json';
import { hasOwners } from 'interfaces/with-beatmap-owners';
import * as React from 'react';
import { hasGuestOwners } from 'utils/beatmap-helper';
import { classWithModifiers, Modifiers } from 'utils/css';
import { formatNumber } from 'utils/html';
import { trans } from 'utils/lang';
import StringWithComponent from './string-with-component';

interface BaseProps {
  beatmap: BeatmapJson | BeatmapExtendedJson;
  // TomYum: another icon in place of the mode's (a custom lazer ruleset's), and no star rating (it isn't its).
  customIcon?: React.ReactNode;
  beatmapUrl?: string;
  count?: number;
  inline?: boolean;
  modifiers?: Modifiers;
}

type MapperProps = {
  beatmapset: BeatmapsetJson;
  showNonGuestOwner: boolean;
  showOwners: true;
} | {
  showOwners: false;
};

type Props = BaseProps & MapperProps;

export default class BeatmapListItem extends React.PureComponent<Props> {
  render() {
    const deleted = 'deleted_at' in this.props.beatmap && this.props.beatmap.deleted_at !== null;
    const version = `${this.props.beatmap.version}${deleted ? ` [${trans('beatmap_discussions.index.deleted_beatmap')}]` : ''}`;

    return (
      <div className={classWithModifiers('beatmap-list-item', { deleted, inline: this.props.inline }, this.props.modifiers)}>
        <div className='beatmap-list-item__col beatmap-list-item__col--icon'>
          {this.props.customIcon ?? <span className={`fal fa-extra-mode-${this.props.beatmap.mode}`} />}
        </div>

        {this.props.customIcon == null &&
          <div className='beatmap-list-item__col'>
            <DifficultyBadge rating={this.props.beatmap.difficulty_rating} />
          </div>
        }

        <div className='beatmap-list-item__col beatmap-list-item__col--main'>
          <div className={`beatmap-list-item__version ${this.props.inline ? '' : 'u-ellipsis-overflow'}`}>
            {this.props.beatmapUrl != null
              ? <a className='beatmap-list-item__version-link' href={this.props.beatmapUrl}>{version}</a>
              : version}
            {' '}
            <span className='beatmap-list-item__mapper'>
              {this.renderOwners()}
            </span>
          </div>
        </div>
        {this.props.count != null &&
          <span className='beatmap-list-item__count'>
            {formatNumber(this.props.count)}
          </span>
        }
      </div>
    );
  }

  private renderOwners() {
    if (!this.props.showOwners) return null;

    const owners = this.props.beatmap.owners;
    if (owners == null || owners.length === 0) {
      return null;
    }

    // TomYum: an imported map's mappers have no accounts here (they came out as deleted users): who made it
    // on osu! instead (the import), as the beatmap page shows them, linked to their osu! profiles.
    const deletedName = trans('users.deleted');
    if (owners.every((owner) => owner.username === deletedName)) {
      const mappers = this.props.beatmapset.tomyum_mappers;
      const onOsu = mappers?.beatmaps[this.props.beatmap.id] ?? (mappers?.creator != null ? [mappers.creator] : []);
      const names: React.ReactNode[] = onOsu.length > 0
        ? onOsu.map((user, i) => (
          <React.Fragment key={user.id}>
            {i > 0 && ', '}
            <a href={`https://osu.ppy.sh/users/${user.id}`} rel='nofollow noreferrer' target='_blank'>{user.username}</a>
          </React.Fragment>
        ))
        : [this.props.beatmapset.creator];

      return (
        <StringWithComponent
          mappings={{ mapper: <>{names}</> }}
          pattern={trans('beatmapsets.show.details.mapped_by')}
        />
      );
    }

    const userId = this.props.beatmapset.user_id;
    const visibleOwners = this.props.showNonGuestOwner
      ? owners
      : owners.filter((mapper) => mapper.id !== userId);

    if (visibleOwners.length === 0) {
      return null;
    }

    const translationKey = hasOwners(this.props.beatmap) && hasGuestOwners(this.props.beatmap, this.props.beatmapset)
      ? 'mapped_by_guest'
      : 'mapped_by';

    return (
      <StringWithComponent
        mappings={{
          mapper: (
            <span className='u-hover'>
              <UserLinkList tooltipPosition='bottom center' users={visibleOwners} />
            </span>
          ),
        }}
        pattern={trans(`beatmapsets.show.details.${translationKey}`)}
      />
    );
  }
}
