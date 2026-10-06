#!/usr/bin/env python3
# TomYum: builds the frames for the profile page's Bad Apple!! easter egg (resources/js/bad-apple.ts).
#
#   pip install opencv-python-headless numpy
#   bin/tomyum_bad_apple.py video.mp4 bad-apple.bin.gz
#
# Each frame's shapes are outlined, simplified and stored as closed polylines, so the rank graph's
# own line can draw them. File (gzip, little-endian):
#   "BAPL", version u8, fps x100 u16, width u16, height u16, frame count u32,
#   then per frame: varint polyline count; per polyline: varint point count, varint x, varint y,
#   then zigzag varint deltas for the other points.

import gzip
import struct
import sys

import cv2

THRESHOLD = 128  # Bad Apple!! is black and white
EPSILON = 1.2  # simplification, in pixels
MIN_AREA = 6.0  # specks smaller than this aren't drawn


def varint(value: int, out: bytearray):
    while True:
        byte = value & 0x7F
        value >>= 7
        if value:
            out.append(byte | 0x80)
        else:
            out.append(byte)
            return


def zigzag(value: int) -> int:
    return (value << 1) ^ (value >> 31)


def frame_polylines(gray):
    _, mask = cv2.threshold(gray, THRESHOLD, 255, cv2.THRESH_BINARY)
    contours, _ = cv2.findContours(mask, cv2.RETR_LIST, cv2.CHAIN_APPROX_SIMPLE)
    polylines = []
    for contour in contours:
        if cv2.contourArea(contour) < MIN_AREA:
            continue
        points = cv2.approxPolyDP(contour, EPSILON, True).reshape(-1, 2)
        if len(points) >= 3:
            polylines.append(points)
    return polylines


def main():
    if len(sys.argv) != 3:
        sys.exit(f'usage: {sys.argv[0]} <video> <out.bin.gz>')

    video = cv2.VideoCapture(sys.argv[1])
    fps = video.get(cv2.CAP_PROP_FPS)
    width = int(video.get(cv2.CAP_PROP_FRAME_WIDTH))
    height = int(video.get(cv2.CAP_PROP_FRAME_HEIGHT))

    frames = bytearray()
    count = 0
    while True:
        ok, image = video.read()
        if not ok:
            break
        polylines = frame_polylines(cv2.cvtColor(image, cv2.COLOR_BGR2GRAY))
        varint(len(polylines), frames)
        for points in polylines:
            varint(len(points), frames)
            x, y = int(points[0][0]), int(points[0][1])
            varint(x, frames)
            varint(y, frames)
            for px, py in points[1:]:
                varint(zigzag(int(px) - x), frames)
                varint(zigzag(int(py) - y), frames)
                x, y = int(px), int(py)
        count += 1

    header = b'BAPL' + struct.pack('<BHHHI', 1, round(fps * 100), width, height, count)
    with gzip.open(sys.argv[2], 'wb', compresslevel=9) as out:
        out.write(header + frames)
    print(f'{count} frames, {width}x{height} at {fps:g} fps, {len(frames) / 1e6:.1f} MB raw')


if __name__ == '__main__':
    main()
