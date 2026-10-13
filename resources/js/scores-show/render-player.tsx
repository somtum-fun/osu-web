// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import * as React from 'react';

// TomYum: the play's rendered replay (o!rdr), played under the score when it's been rendered.
// Render replay (render-button) tells it when a new render's done.

export const renderDoneEvent = 'tomyum:render-done';

interface WatchJson {
  fileUrl: string | null;
  skin: string;
  videoUrl: string;
}

interface Props {
  scoreId: number;
}

export default function RenderPlayer(props: Props) {
  const [watch, setWatch] = React.useState<WatchJson | null>(null);

  React.useEffect(() => {
    const load = () => {
      void $.ajax(`/scores/${props.scoreId}/render`, { method: 'GET' })
        .done((json: WatchJson) => setWatch(json))
        .fail(() => setWatch(null));
    };
    load();
    window.addEventListener(renderDoneEvent, load);

    return () => window.removeEventListener(renderDoneEvent, load);
  }, [props.scoreId]);

  if (watch == null) {
    return null;
  }

  const skin = watch.skin.startsWith('custom:') ? 'a custom skin' : watch.skin;

  return (
    <div style={{ marginTop: 20 }}>
      {watch.fileUrl != null ? (
        <video
          controls
          preload='metadata'
          src={watch.fileUrl}
          style={{ aspectRatio: '16 / 9', background: '#000', borderRadius: 6, display: 'block', width: '100%' }}
        />
      ) : (
        <a className='btn-osu-big btn-osu-big--rounded' href={watch.videoUrl} rel='noopener noreferrer' target='_blank'>
          <i className='fas fa-play' /> Watch the replay render
        </a>
      )}
      <div style={{ fontSize: 12, marginTop: 6, opacity: 0.7 }}>
        Replay rendered by <a href={watch.videoUrl} rel='noopener noreferrer' target='_blank'>o!rdr</a> with {skin}.
      </div>
    </div>
  );
}
