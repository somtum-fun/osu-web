// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

// TomYum: the beatmap page's pp calculator (osu! maps): pick mods (a DT's speed, Difficulty Adjust's stats,
// Relax/Autopilot), misses and combo, and see what a pass would be worth at a few accuracies. The server works
// it out as it does a submitted lazer play (TomYum's /pp-calc), with Reprise's own rules.

import Mod from 'components/mod';
import BeatmapExtendedJson from 'interfaces/beatmap-extended-json';
import { route } from 'laroute';
import { action, computed, makeObservable, observable, reaction, runInAction } from 'mobx';
import { observer } from 'mobx-react';
import * as React from 'react';
import { classWithModifiers } from 'utils/css';
import { formatNumber } from 'utils/html';

interface Props {
  beatmap: BeatmapExtendedJson;
}

interface Result {
  accuracy: number;
  pp: number | null;
}

interface Response {
  maxCombo: number;
  objects: number;
  results: Result[];
}

// The mods that change pp, in osu!'s order; each row's are mutually exclusive.
const modRows = [['EZ', 'NF', 'HT'], ['HR', 'DT', 'NC', 'HD', 'FL'], ['RX', 'AP', 'SO', 'DA']];
const exclusive: Partial<Record<string, string[]>> = {
  AP: ['RX', 'SO'],
  DT: ['NC', 'HT'],
  EZ: ['HR'],
  HR: ['EZ'],
  HT: ['DT', 'NC'],
  NC: ['DT', 'HT'],
  RX: ['AP'],
  SO: ['AP'],
};
const speedRange: Partial<Record<string, [number, number, number]>> = { DT: [1.01, 2, 1.5], HT: [0.5, 0.99, 0.75], NC: [1.01, 2, 1.5] };
const adjustStats = [['circle_size', 'CS'], ['approach_rate', 'AR'], ['overall_difficulty', 'OD'], ['drain_rate', 'HP']] as const;

@observer
export default class PpCalculator extends React.Component<Props> {
  @observable private readonly adjust = new Map<string, string>();
  @observable private combo = '';
  @observable private customAccuracy = '';
  private readonly disposer;
  @observable private readonly enabled = new Set<string>();
  @observable private loading = false;
  @observable private misses = '0';
  @observable private response: Response | null = null;
  @observable private speed = '';
  private xhr?: JQuery.jqXHR;

  constructor(props: Props) {
    super(props);
    makeObservable(this);
    this.disposer = reaction(() => this.query, () => this.load(), { delay: 300, fireImmediately: true });
  }

  // Anything picked or typed in (so there's something to reset).
  @computed
  private get changed() {
    return this.enabled.size > 0 || this.adjust.size > 0 || this.combo !== '' || this.customAccuracy !== ''
      || (this.misses !== '' && this.misses !== '0') || this.speed !== '';
  }

  // The most accuracy the misses leave (when they leave less than 100%).
  @computed
  private get maxAccuracy() {
    const misses = Number(this.misses);
    const objects = this.response?.objects ?? 0;
    return objects > 0 && misses > 0 ? (objects - Math.min(misses, objects)) / objects : null;
  }

  @computed
  private get rateMod() {
    return ['DT', 'NC', 'HT'].find((acronym) => this.enabled.has(acronym)) ?? null;
  }

  // The mods as lazer has them (settings and all), for the server.
  @computed
  private get lazerMods() {
    return [...this.enabled].map((acronym) => {
      const settings: Record<string, number> = {};
      if (acronym === this.rateMod) {
        const speed = Number(this.speed);
        const [min, max] = speedRange[acronym] ?? [0, 0];
        if (this.speed !== '' && speed >= min && speed <= max) settings.speed_change = speed;
      }
      if (acronym === 'DA') {
        for (const [setting] of adjustStats) {
          const value = Number(this.adjust.get(setting));
          if (this.adjust.get(setting) != null && this.adjust.get(setting) !== '' && value >= 0 && value <= 11) settings[setting] = value;
        }
      }

      return Object.keys(settings).length > 0 ? { acronym, settings } : { acronym };
    });
  }

  @computed
  private get query() {
    const accuracies = [0.95, 0.97, 0.98, 0.99, 1];
    const custom = Number(this.customAccuracy) / 100;
    if (this.customAccuracy !== '' && custom >= 0.5 && custom <= 1 && !accuracies.includes(custom)) {
      accuracies.push(custom);
    }

    return {
      acc: accuracies.sort((a, b) => a - b).join(','),
      combo: this.combo === '' ? undefined : this.combo,
      misses: this.misses === '' ? '0' : this.misses,
      mods: JSON.stringify(this.lazerMods),
    };
  }

  componentWillUnmount() {
    this.disposer();
    this.xhr?.abort();
  }

  render() {
    const maxCombo = this.response?.maxCombo ?? this.props.beatmap.max_combo ?? 0;
    const results = this.response?.results ?? [];
    const best = Math.max(0, ...results.map((result) => result.pp ?? 0));
    const custom = Number(this.customAccuracy) / 100;

    return (
      <div className='beatmapset-pp-calculator'>
        <div className='beatmapset-pp-calculator__header'>
          <h3 className='beatmapset-pp-calculator__title'>pp calculator</h3>
          <span className='beatmapset-pp-calculator__summary'>
            {this.enabled.size > 0 ? [...this.enabled].join(' ') : 'No mods'}
            {' · '}
            {formatNumber(maxCombo)}x max combo
            {this.maxAccuracy != null && ` · at most ${formatNumber(this.maxAccuracy * 100, 2)}% with ${this.misses} misses`}
          </span>
          {this.changed && (
            <button className='beatmapset-pp-calculator__reset' onClick={this.reset} type='button'>
              <span className='fas fa-undo' /> Reset
            </button>
          )}
        </div>

        <div className='beatmapset-pp-calculator__body'>
          <div className='beatmapset-pp-calculator__controls'>
            <div className='beatmapset-pp-calculator__mods'>
              {modRows.map((row) => (
                <div key={row.join()} className='beatmapset-pp-calculator__mod-row'>
                  {row.map((acronym) => (
                    <button
                      key={acronym}
                      className={classWithModifiers('beatmap-scoreboard-mod', { enabled: this.enabled.has(acronym) })}
                      data-acronym={acronym}
                      onClick={this.toggle}
                      type='button'
                    >
                      <Mod mod={{ acronym }} />
                    </button>
                  ))}
                </div>
              ))}
            </div>

            <div className='beatmapset-pp-calculator__fields'>
              {this.renderField('Misses', <input className='beatmapset-pp-calculator__input' min={0} onChange={this.setMisses} placeholder='0' type='number' value={this.misses} />)}
              {this.renderField('Combo', (
                <input
                  className='beatmapset-pp-calculator__input'
                  max={maxCombo}
                  min={0}
                  onChange={this.setCombo}
                  placeholder={`${maxCombo}`}
                  type='number'
                  value={this.combo}
                />
              ), 'x')}
              {this.renderField('Accuracy', (
                <input
                  className='beatmapset-pp-calculator__input'
                  max={100}
                  min={50}
                  onChange={this.setCustomAccuracy}
                  placeholder='96.5'
                  step={0.01}
                  type='number'
                  value={this.customAccuracy}
                />
              ), '%')}
              {this.rateMod != null && this.renderField('Speed', (
                <input
                  className='beatmapset-pp-calculator__input'
                  max={speedRange[this.rateMod]?.[1]}
                  min={speedRange[this.rateMod]?.[0]}
                  onChange={this.setSpeed}
                  placeholder={`${speedRange[this.rateMod]?.[2]}`}
                  step={0.01}
                  type='number'
                  value={this.speed}
                />
              ), '×')}
              {this.enabled.has('DA') && adjustStats.map(([setting, label]) => (
                <React.Fragment key={setting}>
                  {this.renderField(label, (
                    <input
                      className='beatmapset-pp-calculator__input'
                      data-setting={setting}
                      max={11}
                      min={0}
                      onChange={this.setAdjust}
                      placeholder={`${this.mapStat(setting)}`}
                      step={0.1}
                      type='number'
                      value={this.adjust.get(setting) ?? ''}
                    />
                  ))}
                </React.Fragment>
              ))}
            </div>
          </div>

          <div className={classWithModifiers('beatmapset-pp-calculator__results', { loading: this.loading })}>
            {/* (Each accuracy once: the server drops ones the misses make the same.) */}
            {results.map((result) => (
              <div
                key={result.accuracy}
                className={classWithModifiers('beatmapset-pp-calculator__result', { custom: this.customAccuracy !== '' && Math.abs(result.accuracy - custom) < 1e-6 })}
              >
                <div className='beatmapset-pp-calculator__accuracy'>{formatNumber(result.accuracy * 100, 2)}%</div>
                <div className='beatmapset-pp-calculator__pp'>
                  {result.pp == null ? '-' : formatNumber(Math.round(result.pp))}
                  <span className='beatmapset-pp-calculator__pp-unit'>pp</span>
                </div>
                <div className='beatmapset-pp-calculator__bar'>
                  <div className='beatmapset-pp-calculator__bar-fill' style={{ width: `${best > 0 ? (result.pp ?? 0) / best * 100 : 0}%` }} />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  private load() {
    this.xhr?.abort();
    runInAction(() => this.loading = true);
    this.xhr = $.ajax(route('beatmaps.tomyum-pp', { beatmap: this.props.beatmap.id }), { data: this.query, dataType: 'json' });
    this.xhr
      .done((response: Response) => runInAction(() => this.response = response))
      .always(() => runInAction(() => this.loading = false));
  }

  private mapStat(setting: typeof adjustStats[number][0]) {
    switch (setting) {
      case 'circle_size': return this.props.beatmap.cs;
      case 'approach_rate': return this.props.beatmap.ar;
      case 'overall_difficulty': return this.props.beatmap.accuracy;
      case 'drain_rate': return this.props.beatmap.drain;
    }
  }

  // A labelled box for a number, with what it's in after it (x, %, ×).
  private renderField(label: string, input: React.ReactNode, suffix?: string) {
    return (
      <label className='beatmapset-pp-calculator__field'>
        <span className='beatmapset-pp-calculator__field-label'>{label}</span>
        <span className='beatmapset-pp-calculator__input-box'>
          {input}
          {suffix != null && <span className='beatmapset-pp-calculator__suffix'>{suffix}</span>}
        </span>
      </label>
    );
  }

  // Back to no mods and a full combo pass.
  @action
  private readonly reset = () => {
    this.enabled.clear();
    this.adjust.clear();
    this.combo = '';
    this.customAccuracy = '';
    this.misses = '0';
    this.speed = '';
  };

  @action
  private readonly setAdjust = (e: React.ChangeEvent<HTMLInputElement>) => {
    this.adjust.set(e.currentTarget.dataset.setting ?? '', e.currentTarget.value);
  };

  @action
  private readonly setCombo = (e: React.ChangeEvent<HTMLInputElement>) => this.combo = e.currentTarget.value;

  @action
  private readonly setCustomAccuracy = (e: React.ChangeEvent<HTMLInputElement>) => this.customAccuracy = e.currentTarget.value;

  @action
  private readonly setMisses = (e: React.ChangeEvent<HTMLInputElement>) => this.misses = e.currentTarget.value;

  @action
  private readonly setSpeed = (e: React.ChangeEvent<HTMLInputElement>) => this.speed = e.currentTarget.value;

  @action
  private readonly toggle = (e: React.MouseEvent<HTMLButtonElement>) => {
    const acronym = e.currentTarget.dataset.acronym ?? '';
    if (this.enabled.has(acronym)) {
      this.enabled.delete(acronym);
    } else {
      for (const other of exclusive[acronym] ?? []) this.enabled.delete(other);
      this.enabled.add(acronym);
    }
    if (this.rateMod == null) this.speed = '';
  };
}
