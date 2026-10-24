// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import * as React from 'react';

// TomYum: the play's rendered replay (o!rdr), played under the score when it's been rendered.
// Render replay (render-button) tells it when a new render's done.

export const renderDoneEvent = 'tomyum:render-done';

interface WatchJson {
  fileUrl: string | null;
  skin: string;
  skinName: string | null;
  videoUrl: string;
}

interface Props {
  // The map's cover, shown until the video plays.
  cover?: string;
  scoreId: number;
}

// The map's whole background (fullsize, next to its cover: 16:9 like the video), else its cover.
function usePoster(cover: string | undefined) {
  const [poster, setPoster] = React.useState<string | undefined>();

  React.useEffect(() => {
    if (cover == null) {
      return;
    }
    const fullsize = cover.replace('/covers/cover.jpg', '/covers/fullsize.jpg');
    const image = new Image();
    image.onload = () => setPoster(fullsize);
    image.onerror = () => setPoster(cover);
    image.src = fullsize;
  }, [cover]);

  return poster;
}

export default function RenderPlayer(props: Props) {
  const [watch, setWatch] = React.useState<WatchJson | null>(null);
  const poster = usePoster(props.cover);

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

  const custom = watch.skin.startsWith('custom:');
  const skin = watch.skinName ?? (custom ? 'a custom skin' : watch.skin);

  return (
    <div className='score-render'>
      <div className='score-render__panel'>
        <div className='score-render__header'>
          <div className='score-render__title'>
            <i className='fas fa-film score-render__icon' />
            Replay render
          </div>
          {watch.fileUrl != null && (
            <a className='score-render__action' download href={watch.fileUrl} rel='noopener noreferrer' target='_blank'>
              <i className='fas fa-download' /> Download
            </a>
          )}
          <a className='score-render__action' href={watch.videoUrl} rel='noopener noreferrer' target='_blank'>
            <i className='fas fa-external-link-alt' /> Open on o!rdr
          </a>
        </div>

        {watch.fileUrl != null ? (
          <video className='score-render__video' controls poster={poster} preload='metadata' src={watch.fileUrl} />
        ) : (
          <div className='score-render__footer'>
            The video couldn&apos;t be loaded here; open it on o!rdr.
          </div>
        )}

        <div className='score-render__footer'>
          <span className='score-render__footer-item'>
            <i className='fas fa-paint-brush' />
            Skin <span className='score-render__value'>{skin}</span>
          </span>
          <span className='score-render__footer-item'>
            <i className='fas fa-video' />
            Rendered by <a className='score-render__value' href='https://ordr.issou.best' rel='noopener noreferrer' target='_blank'>o!rdr</a>
          </span>
        </div>
      </div>
    </div>
  );
}
