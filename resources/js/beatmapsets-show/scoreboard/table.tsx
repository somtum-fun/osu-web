// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import { computed, observable, makeObservable } from 'mobx';
import { observer } from 'mobx-react';
import * as React from 'react';
import { ActiveKeyState, ContainerContext, KeyContext } from 'stateful-activation-context';
import { shouldShowPp } from 'utils/beatmap-helper';
import { classWithModifiers } from 'utils/css';
import { trans } from 'utils/lang';
import { scoreStatisticsMapping } from 'utils/score-helper';
import Controller from './controller';
import TableRow from './table-row';

const bn = 'beatmap-scoreboard-table';

interface Props {
  controller: Controller;
}

// TomYum: a custom ruleset's judgements, short (as osu!'s columns are): lazer's own names, as few words as they'll take.
const customShortLabels: Partial<Record<string, string>> = {
  large_bonus: 'bonus',
  large_tick_hit: 'tick',
  large_tick_miss: 'tick miss',
  slider_tail_hit: 'tail',
  small_bonus: 'spin',
  small_tick_hit: 'drop',
  small_tick_miss: 'drop miss',
};
const customShortLabel = (attribute: string) => customShortLabels[attribute] ?? attribute.replace(/_/g, ' ');

@observer
export default class Table extends React.Component<Props> {
  @observable private readonly activeKeyState = new ActiveKeyState<number>();

  // TomYum: sorted by pp on a map that gives pp, so the score column shows pp.
  @computed
  get byPp() {
    return this.props.controller.sort === 'pp' && this.showPp;
  }

  @computed
  // TomYum: on a custom ruleset's board, the judgements its plays have (lazer's names), in the order they first come.
  get customAttributes() {
    if (this.props.controller.customRuleset == null) return null;

    const attributes: string[] = [];
    for (const score of this.props.controller.data.scores) {
      for (const [attribute, value] of Object.entries(score.statistics)) {
        if (value != null && value > 0 && !attribute.startsWith('ignore_') && !attributes.includes(attribute)) {
          attributes.push(attribute);
        }
      }
    }

    return attributes;
  }

  get showPp() {
    // TomYum: a custom lazer ruleset has no pp.
    return this.props.controller.customRuleset == null && shouldShowPp(this.props.controller.beatmap);
  }

  constructor(props: Props) {
    super(props);

    makeObservable(this);
  }

  render() {
    return (
      <ContainerContext.Provider value={this.activeKeyState}>
        <div className={classWithModifiers(bn, { 'menu-active': this.activeKeyState.value != null })}>
          <table className={`${bn}__table`}>
            <thead>
              <tr>
                <th className={`${bn}__header ${bn}__header--rank`}>
                  {trans('beatmapsets.show.scoreboard.headers.rank')}
                </th>
                <th className={`${bn}__header ${bn}__header--grade`} />
                <th className={`${bn}__header ${bn}__header--score`}>
                  {trans(`beatmapsets.show.scoreboard.headers.${this.byPp ? 'pp' : 'score'}`)}
                </th>
                <th className={`${bn}__header ${bn}__header--accuracy`}>
                  {trans('beatmapsets.show.scoreboard.headers.accuracy')}
                </th>
                <th className={`${bn}__header ${bn}__header--flag`} />
                <th className={`${bn}__header ${bn}__header--player`}>
                  {trans('beatmapsets.show.scoreboard.headers.player')}
                </th>
                <th className={`${bn}__header ${bn}__header--maxcombo`}>
                  {trans('beatmapsets.show.scoreboard.headers.combo')}
                </th>
                {/* TomYum: a custom ruleset's board: its plays' own judgements. */}
                {this.customAttributes != null && this.customAttributes.map((attribute) => (
                  <th key={attribute} className={classWithModifiers(`${bn}__header`, ['hitstat', `hit-${attribute}`])}>
                    {customShortLabel(attribute)}
                  </th>
                ))}
                {this.customAttributes == null && scoreStatisticsMapping[this.props.controller.beatmap.mode]
                  .filter((stat) => stat.relevantTypes.includes('leaderboard'))
                  .map((stat) => (
                    <th
                      key={stat.label.short}
                      className={classWithModifiers(`${bn}__header`, ['hitstat', `hit-${stat.attributes[0]}`])}
                    >
                      {stat.label.short}
                    </th>
                  ))}
                {this.showPp && !this.byPp &&
                  <th className={`${bn}__header ${bn}__header--pp`}>
                    {trans('beatmapsets.show.scoreboard.headers.pp')}
                  </th>
                }
                <th className={`${bn}__header ${bn}__header--time`}>
                  {trans('beatmapsets.show.scoreboard.headers.time')}
                </th>
                <th className={`${bn}__header ${bn}__header--mods`}>
                  {trans('beatmapsets.show.scoreboard.headers.mods')}
                </th>
                <th className={`${bn}__header ${bn}__header--popup-menu`} />
              </tr>
            </thead>
            <tbody className={`${bn}__body`}>
              {this.props.controller.data.scores.map((score, index) => (
                <KeyContext.Provider key={index} value={index}>
                  <TableRow
                    activated={this.activeKeyState.value === index}
                    beatmap={this.props.controller.beatmap}
                    byPp={this.byPp}
                    highlightFriends={this.props.controller.currentType !== 'friend'}
                    index={index}
                    customAttributes={this.customAttributes}
                    score={score}
                    showPp={this.showPp && !this.byPp}
                  />
                </KeyContext.Provider>
              ))}
            </tbody>
          </table>
        </div>
      </ContainerContext.Provider>
    );
  }
}
