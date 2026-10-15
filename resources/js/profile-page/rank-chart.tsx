// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import LineChart, { makeOptionsNumber } from 'charts/line-chart';
import { scaleLinear, scaleLog } from 'd3';
import RankHistoryJson from 'interfaces/rank-history-json';
import { UserStatisticsRankedJson } from 'interfaces/user-statistics-json';
import { last } from 'lodash';
import core from 'osu-core-singleton';
import * as React from 'react';
import { classWithModifiers } from 'utils/css';
import { formatNumber } from 'utils/html';
import { trans, transChoice } from 'utils/lang';

interface Props {
  // TomYum: the player's pp history in the mode shown ([{day, pp}]): the chart can show pp over time instead.
  ppHistoryUrl?: string;
  rankHistory: RankHistoryJson | null;
  stats: UserStatisticsRankedJson;
}

interface State {
  ppHistory: { day: string; pp: number }[] | null;
  view: 'pp' | 'rank';
}

const options = makeOptionsNumber({
  axisLabels: false,
  circleLine: true,
  infoBoxFormatX: formatX,
  infoBoxFormatY: formatY,
  marginBottom: 15,
  marginLeft: 0,
  marginRight: 0,
  marginTop: 15,
  modifiers: 'profile-page profile-page-card',
  scaleX: scaleLinear(),
  scaleY: scaleLog(),
});

// TomYum: pp over time, on a plain scale.
const ppOptions = makeOptionsNumber({
  axisLabels: false,
  circleLine: true,
  infoBoxFormatX: formatX,
  infoBoxFormatY: (d: number) => `<strong>pp</strong> ${formatNumber(Math.round(d))}`,
  marginBottom: 15,
  marginLeft: 0,
  marginRight: 0,
  marginTop: 15,
  modifiers: 'profile-page profile-page-card',
  scaleX: scaleLinear(),
  scaleY: scaleLinear(),
});

function formatX(d: number) {
  return d === 0 ? trans('common.time.now') : transChoice('common.time.days_ago', -d);
}

function formatY(d: number) {
  return `<strong>${trans('users.show.rank.global_simple')}</strong> #${formatNumber(-d)}`;
}

export default class RankChart extends React.Component<Props, State> {
  state: State = { ppHistory: null, view: 'rank' };

  private readonly disposers = new Set<(() => void) | undefined>();
  private ppChart?: LineChart<number>;
  private readonly ppChartArea = React.createRef<HTMLDivElement>();
  private rankChart?: LineChart<number>;
  private readonly rankChartArea = React.createRef<HTMLDivElement>();

  get data() {
    const raw = this.props.rankHistory?.data ?? [];
    const data = raw.map((rank, i) => ({ x: i - raw.length + 1, y: -rank })).filter((point) => point.y < 0);

    if (data.length === 0) {
      data.push({ x: 0, y: -this.props.stats.global_rank });
    }

    if (data.length === 1) {
      data.unshift({ x: data[0].x - 1, y: data[0].y });
    }

    const lastData = last(data);

    if (lastData?.x !== 0) {
      data.push({ x: 0, y: -this.props.stats.global_rank });
    }

    return data;
  }

  // TomYum: the pp history as points (days ago, pp), with today's pp at the end.
  get ppData() {
    const today = new Date();
    today.setUTCHours(0, 0, 0, 0);
    const data = (this.state.ppHistory ?? []).map((point) => ({
      x: Math.round((Date.parse(`${point.day}T00:00:00Z`) - today.getTime()) / 86_400_000),
      y: point.pp,
    }));
    const lastData = last(data);
    if (lastData?.x !== 0) {
      data.push({ x: 0, y: this.props.stats.pp });
    }
    if (data.length === 1) {
      data.unshift({ x: -1, y: data[0].y });
    }

    return data;
  }

  componentDidMount() {
    if (this.rankChartArea.current == null) return;

    if (this.rankChart == null) {
      const rankChart = new LineChart(this.rankChartArea.current, options);
      $(window).on('resize', rankChart.resize);
      this.disposers.add(() => $(window).off('resize', rankChart.resize));
      this.rankChart = rankChart;
    }

    this.disposers.add(core.reactTurbolinks.runAfterPageLoad(this.loadRankChart));
  }

  componentDidUpdate() {
    this.loadRankChart();
    this.loadPpChart();
  }

  componentWillUnmount() {
    this.disposers.forEach((disposer) => disposer?.());
  }

  render() {
    return (
      <div className='profile-rank-chart'>
        {this.props.ppHistoryUrl != null && (
          <div className='profile-rank-chart__views'>
            {(['rank', 'pp'] as const).map((view) => (
              <button
                key={view}
                className={classWithModifiers('profile-rank-chart__view', { active: this.state.view === view })}
                data-view={view}
                onClick={this.setView}
                type='button'
              >
                {view === 'rank' ? 'Rank' : 'pp'}
              </button>
            ))}
          </div>
        )}
        <div ref={this.rankChartArea} hidden={this.state.view !== 'rank'} />
        <div ref={this.ppChartArea} hidden={this.state.view !== 'pp'} />
      </div>
    );
  }

  private readonly loadPpChart = () => {
    if (this.state.view !== 'pp' || this.ppChartArea.current == null) return;

    if (this.ppChart == null) {
      const ppChart = new LineChart(this.ppChartArea.current, ppOptions);
      $(window).on('resize', ppChart.resize);
      this.disposers.add(() => $(window).off('resize', ppChart.resize));
      this.ppChart = ppChart;
    }
    this.ppChart.loadData(this.ppData);
  };

  private readonly loadRankChart = () => {
    this.rankChart?.loadData(this.data);
  };

  private readonly setView = (e: React.MouseEvent<HTMLButtonElement>) => {
    const view = e.currentTarget.dataset.view === 'pp' ? 'pp' : 'rank';
    this.setState({ view });

    if (view === 'pp' && this.state.ppHistory == null && this.props.ppHistoryUrl != null) {
      void $.getJSON(this.props.ppHistoryUrl).done((ppHistory: State['ppHistory']) => this.setState({ ppHistory: ppHistory ?? [] }));
    }
  };
}
