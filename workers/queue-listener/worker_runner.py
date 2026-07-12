"""Worker loop that pulls jobs from the PHP control plane."""

from __future__ import annotations

import importlib.util
import os
import time
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parent
VIDEO_WORKER = ROOT.parent / "video-worker" / "worker.py"
POLL_INTERVAL_SECONDS = float(os.getenv("WORKER_POLL_INTERVAL_SECONDS", "2"))


spec = importlib.util.spec_from_file_location("video_worker", VIDEO_WORKER)
if spec is None or spec.loader is None:
    raise RuntimeError("Unable to load video worker module")
video_worker = importlib.util.module_from_spec(spec)
spec.loader.exec_module(video_worker)


def run() -> None:
    print("[worker-runner] polling PHP internal queue endpoint")

    while True:
        job: dict[str, Any] | None = None

        try:
            job = video_worker.fetch_next_job()
            if job is None:
                time.sleep(POLL_INTERVAL_SECONDS)
                continue

            video_worker.process_scan_job(job)
            print(f"[worker-runner] completed assessment_video_uuid={job.get('assessment_video_uuid')}")
        except Exception as exc:  # noqa: BLE001
            print(f"[worker-runner] job failed: {exc}")

            try:
                if isinstance(job, dict) and "job_id" in job:
                    video_worker.mark_scan_invalid(job, str(exc))
            except Exception as mark_exc:  # noqa: BLE001
                print(f"[worker-runner] failed to mark scan invalid: {mark_exc}")

            time.sleep(POLL_INTERVAL_SECONDS)


if __name__ == "__main__":
    run()
