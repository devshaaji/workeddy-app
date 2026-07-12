"""Frame extraction utilities for WorkEddy video processing."""

from __future__ import annotations

import cv2


def sample_frame_stats(video_path: str, sample_every_n: int = 4) -> dict[str, float | int]:
    cap = cv2.VideoCapture(video_path)
    if not cap.isOpened():
        raise RuntimeError(f"Cannot open video: {video_path}")

    fps = cap.get(cv2.CAP_PROP_FPS) or 0.0
    processed = 0
    sampled = 0

    while True:
        ok, _ = cap.read()
        if not ok:
            break
        processed += 1
        if processed % sample_every_n == 0:
            sampled += 1

    cap.release()
    return {"processed_frames": processed, "sampled_frames": sampled, "fps": float(fps)}
