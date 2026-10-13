// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import * as React from 'react';
import { renderDoneEvent } from './render-player';

// TomYum: Render replay (o!rdr, through the game server): asks for a render with the player's skin
// (account settings), then follows it until the video's there.

interface RenderJson {
  error: string | null;
  id: number;
  progress: string | null;
  status: 'queued' | 'rendering' | 'done' | 'failed';
  videoUrl: string | null;
}

interface Props {
  scoreId: number;
}

type State =
  | { kind: 'idle' }
  | { kind: 'asking' }
  | { kind: 'error'; message: string }
  | { kind: 'render'; render: RenderJson };

export default function RenderButton(props: Props) {
  const [state, setState] = React.useState<State>({ kind: 'idle' });
  const timer = React.useRef<number>();

  React.useEffect(() => () => window.clearTimeout(timer.current), []);

  const follow = (render: RenderJson) => {
    setState({ kind: 'render', render });
    if (render.status === 'done') {
      // The player under the score shows it.
      window.dispatchEvent(new Event(renderDoneEvent));
    }
    if (render.status === 'queued' || render.status === 'rendering') {
      timer.current = window.setTimeout(() => {
        void $.ajax(`/renders/${render.id}`, { method: 'GET' })
          .done((next: RenderJson) => follow(next))
          .fail(() => follow(render));
      }, 5000);
    }
  };

  const ask = () => {
    setState({ kind: 'asking' });
    void $.ajax(`/scores/${props.scoreId}/render`, { method: 'POST' })
      .done((response: { error: string | null; render: RenderJson | null }) => {
        if (response.render == null || response.render.status === 'failed') {
          setState({ kind: 'error', message: response.error ?? response.render?.error ?? 'The replay couldn\'t be rendered.' });
        } else {
          follow(response.render);
        }
      })
      .fail(() => setState({ kind: 'error', message: 'The replay couldn\'t be rendered right now.' }));
  };

  if (state.kind === 'render' && state.render.status === 'done' && state.render.videoUrl != null) {
    return (
      <a className='btn-osu-big btn-osu-big--rounded' href={state.render.videoUrl} rel='noopener noreferrer' target='_blank'>
        <i className='fas fa-play' /> Watch the render
      </a>
    );
  }

  const busy = state.kind === 'asking' || (state.kind === 'render' && state.render.status !== 'failed');
  const note = state.kind === 'error'
    ? state.message
    : state.kind === 'render'
      ? (state.render.status === 'failed' ? state.render.error : state.render.progress)
      : null;

  return (
    <span style={{ alignItems: 'center', display: 'inline-flex', flexWrap: 'wrap', gap: 10 }}>
      <button
        className='js-login-required--click btn-osu-big btn-osu-big--rounded'
        disabled={busy}
        onClick={ask}
        type='button'
      >
        {busy ? <><i className='fas fa-spinner fa-spin' /> Rendering...</> : <><i className='fas fa-video' /> Render replay</>}
      </button>
      {note != null && <span style={{ fontSize: 12, opacity: 0.8 }}>{note}</span>}
    </span>
  );
}
