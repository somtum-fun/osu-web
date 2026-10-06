// TomYum: Bad Apple!! on the profile's rank graph. Run `badApple()` in the browser console on a
// player's profile and the graph's line draws the video's outlines to the song; run it again to stop.
// The frames (made by bin/tomyum_bad_apple.py, about 1 MB) and the song are only downloaded from the
// file host when it plays.

interface Movie {
  count: number;
  data: Uint8Array;
  fps: number;
  height: number;
  offsets: Uint32Array;
  width: number;
}

interface Playback {
  stop: () => void;
}

const headerLength = 15;
const musicVolume = 0.5;
const maxHeight = 360;
const svgNs = 'http://www.w3.org/2000/svg';

let movie: Promise<Movie> | undefined;
let playback: Playback | undefined;

function fileUrl(name: string) {
  // served by the website itself (public/media/easter)
  return `/media/easter/${name}`;
}

async function load(): Promise<Movie> {
  const response = await fetch(fileUrl('bad-apple.bin.gz'));
  if (!response.ok || response.body == null) {
    throw new Error(`couldn't download the frames (${response.status})`);
  }

  let bytes = new Uint8Array(await response.arrayBuffer());
  // Gzipped unless something on the way already unpacked it.
  if (bytes[0] === 0x1f && bytes[1] === 0x8b) {
    const unpacked = new Blob([bytes]).stream().pipeThrough(new DecompressionStream('gzip'));
    bytes = new Uint8Array(await new Response(unpacked).arrayBuffer());
  }

  if (String.fromCharCode(...bytes.subarray(0, 4)) !== 'BAPL') {
    throw new Error('the frames file is not what it should be');
  }

  const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
  const count = view.getUint32(11, true);
  const reader = { data: bytes, position: headerLength };
  const offsets = new Uint32Array(count);
  for (let frame = 0; frame < count; frame++) {
    offsets[frame] = reader.position;
    skipFrame(reader);
  }

  return {
    count,
    data: bytes,
    fps: view.getUint16(5, true) / 100,
    height: view.getUint16(9, true),
    offsets,
    width: view.getUint16(7, true),
  };
}

interface Reader {
  data: Uint8Array;
  position: number;
}

function varint(reader: Reader) {
  let value = 0;
  let shift = 0;
  let byte: number;
  do {
    byte = reader.data[reader.position++];
    value |= (byte & 0x7f) << shift;
    shift += 7;
  } while (byte >= 0x80);

  return value;
}

function unzigzag(value: number) {
  return (value >>> 1) ^ -(value & 1);
}

function skipFrame(reader: Reader) {
  const polylines = varint(reader);
  for (let i = 0; i < polylines; i++) {
    const points = varint(reader);
    for (let j = 0; j < points * 2; j++) {
      varint(reader);
    }
  }
}

function framePath(movie: Movie, frame: number) {
  const reader = { data: movie.data, position: movie.offsets[frame] };
  const parts: string[] = [];
  const polylines = varint(reader);
  for (let i = 0; i < polylines; i++) {
    const points = varint(reader);
    let x = varint(reader);
    let y = varint(reader);
    parts.push(`M${x} ${y}`);
    for (let j = 1; j < points; j++) {
      x += unzigzag(varint(reader));
      y += unzigzag(varint(reader));
      parts.push(`L${x} ${y}`);
    }
    parts.push('Z');
  }

  return parts.join('');
}

function play(chart: HTMLElement, movie: Movie): Playback {
  const box = chart.parentElement;
  const graph = chart.querySelector('svg');
  const hoverArea = chart.querySelector<HTMLElement>('.line-chart__hover-area');

  // Room for the video at its own shape: the graph's box grows (the profile card's box fills its
  // section, so that grows instead).
  const grow = box == null ? null : getComputedStyle(box).position === 'absolute' ? box.parentElement : box;
  const growStyle = grow === box ? 'height' : 'minHeight';
  const previousSize = grow?.style[growStyle] ?? '';
  if (box != null && grow != null) {
    grow.style[growStyle] = `${Math.min(maxHeight, Math.round(box.clientWidth * movie.height / movie.width))}px`;
  }
  if (graph != null) graph.style.display = 'none';
  if (hoverArea != null) hoverArea.style.visibility = 'hidden';

  // The rank graph's own line (same colour and width), drawing the video instead of the ranks.
  const screen = document.createElementNS(svgNs, 'svg');
  screen.setAttribute('viewBox', `0 0 ${movie.width} ${movie.height}`);
  screen.setAttribute('preserveAspectRatio', 'xMidYMid meet');
  screen.setAttribute('aria-hidden', 'true');
  screen.style.cssText = 'display: block; width: 100%; height: 100%; overflow: visible';
  const line = document.createElementNS(svgNs, 'path');
  line.classList.add('line-chart__line');
  line.setAttribute('vector-effect', 'non-scaling-stroke');
  screen.appendChild(line);
  chart.appendChild(screen);
  screen.scrollIntoView({ behavior: 'smooth', block: 'center' });

  // The song is the clock. A browser may refuse to start sound that wasn't asked for with a click
  // (and typing in the console isn't one): then the video plays on its own and the song joins in,
  // at the same point, on the first click or key press on the page.
  const music = new Audio(fileUrl('bad-apple.m4a'));
  music.volume = musicVolume;
  let clock: 'music' | 'silent' | 'starting' = 'starting';
  let silentSince = 0;

  const seconds = (now: number) => clock === 'music'
    ? music.currentTime
    : clock === 'silent' ? (now - silentSince) / 1000 : 0;

  const joinMusic = () => {
    removeJoinListeners();
    music.currentTime = seconds(performance.now());
    void music.play().then(() => {
      if (!stopped) clock = 'music';
    }).catch(() => undefined);
  };
  const removeJoinListeners = () => {
    document.removeEventListener('pointerdown', joinMusic);
    document.removeEventListener('keydown', joinMusic);
  };

  let animationFrame = 0;
  let shown = -1;
  let stopped = false;

  music.play().then(() => {
    if (!stopped) clock = 'music';
  }).catch(() => {
    if (stopped) return;
    clock = 'silent';
    silentSince = performance.now();
    console.info('Bad Apple!!: click anywhere on the page to hear the music.');
    document.addEventListener('pointerdown', joinMusic);
    document.addEventListener('keydown', joinMusic);
  });

  const stop = () => {
    if (stopped) return;
    stopped = true;
    cancelAnimationFrame(animationFrame);
    document.removeEventListener('turbo:before-cache', stop);
    removeJoinListeners();
    music.pause();
    music.removeAttribute('src');
    music.load();
    screen.remove();
    if (grow != null) grow.style[growStyle] = previousSize;
    if (graph != null) graph.style.display = '';
    if (hoverArea != null) hoverArea.style.visibility = '';
    // The graph sizes itself on resize: back to its own shape.
    window.dispatchEvent(new Event('resize'));
    if (playback?.stop === stop) playback = undefined;
  };

  // Frames follow the clock, so a slow frame is skipped rather than slowing the video down.
  const tick = (now: number) => {
    if (!chart.isConnected) {
      stop();
      return;
    }
    const frame = Math.floor(seconds(now) * movie.fps);
    if (frame >= movie.count) {
      stop();
      return;
    }
    if (frame !== shown && frame >= 0) {
      line.setAttribute('d', framePath(movie, frame));
      shown = frame;
    }
    animationFrame = requestAnimationFrame(tick);
  };

  document.addEventListener('turbo:before-cache', stop);
  animationFrame = requestAnimationFrame(tick);

  return { stop };
}

function badApple() {
  if (playback != null) {
    playback.stop();
    return 'Stopped.';
  }

  const chart = document.querySelector<HTMLElement>('.line-chart--profile-page');
  if (chart == null) {
    return 'Open a player\'s profile (one with a rank graph) first.';
  }

  movie ??= load();
  movie.then((loaded) => {
    if (playback == null && chart.isConnected) {
      playback = play(chart, loaded);
    }
  }).catch((error: unknown) => {
    movie = undefined;
    console.error('Bad Apple!!:', error);
  });

  return 'Bad Apple!! \u266a (run badApple() again to stop)';
}

Object.assign(window, { badApple });
