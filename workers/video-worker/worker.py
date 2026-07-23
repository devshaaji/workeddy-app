"""Video worker processor implementation.

This worker only extracts pose metrics and reports them back to the PHP API.
PHP remains the single scoring and persistence authority.
"""

from __future__ import annotations

import importlib.util
import json
import os
import sys
import urllib.error
import urllib.request
from functools import lru_cache
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parent
SHARED_ROOT = ROOT.parent / "shared"

if str(SHARED_ROOT) not in sys.path:
    sys.path.append(str(SHARED_ROOT))

from worker_contract import load_contract, route, validate_payload  # noqa: E402

API_BASE_URL = os.getenv("WORKER_API_BASE_URL", "http://nginx").rstrip("/")
API_TIMEOUT_SECONDS = float(os.getenv("WORKER_API_TIMEOUT_SECONDS", "20"))
VIDEO_CONTRACT = load_contract("video-worker")
NEXT_JOB_ENDPOINT = f"/api/v1{route(VIDEO_CONTRACT, 'next_job')}"
COMPLETE_ENDPOINT = f"/api/v1{route(VIDEO_CONTRACT, 'complete')}"
FAIL_ENDPOINT = f"/api/v1{route(VIDEO_CONTRACT, 'fail')}"


def _load(name: str, filename: str):
    spec = importlib.util.spec_from_file_location(name, ROOT / filename)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"Unable to load module {filename}")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


@lru_cache(maxsize=1)
def _frame_extractor_module():
    return _load("frame_extractor", "frame_extractor.py")


@lru_cache(maxsize=1)
def _pose_detector_module():
    return _load("pose_estimation", "pose_estimation.py")


def _api_request(
    endpoint: str,
    *,
    method: str,
    payload: dict[str, Any] | None = None,
    allow_no_content: bool = False,
) -> dict[str, Any] | None:
    token = os.getenv("WORKER_API_TOKEN", "").strip()
    if token == "":
        raise RuntimeError("WORKER_API_TOKEN is not configured")

    body = None
    headers = {
        "X-Worker-Token": token,
    }

    if payload is not None:
        body = json.dumps(payload).encode("utf-8")
        headers["Content-Type"] = "application/json"

    request = urllib.request.Request(
        f"{API_BASE_URL}{endpoint}",
        data=body,
        method=method,
        headers=headers,
    )

    try:
        with urllib.request.urlopen(request, timeout=API_TIMEOUT_SECONDS) as response:  # noqa: S310
            raw = response.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as exc:
        error_body = exc.read().decode("utf-8", errors="replace") if hasattr(exc, "read") else ""
        if "<html>" in error_body.lower():
            error_body = f"HTML response from gateway (Status {exc.code})"
        raise RuntimeError(
            f"Worker API request failed with status {exc.code}: {error_body or str(exc)}"
        ) from exc
    except TimeoutError as exc:
        # Python 3.11+: socket.timeout is TimeoutError, a sibling of URLError (both
        # are OSError subclasses).  Timeouts on response.read() raise it directly
        # without going through URLError, so it must be caught explicitly.
        raise RuntimeError(
            f"Worker API request timed out after {API_TIMEOUT_SECONDS}s"
        ) from exc
    except urllib.error.URLError as exc:
        raise RuntimeError(f"Worker API request failed: {exc.reason}") from exc

    if raw == "":
        return None if allow_no_content else {}

    try:
        parsed = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"Worker API returned non-JSON response: {raw}") from exc

    if isinstance(parsed, dict) and parsed.get("error"):
        raise RuntimeError(f"Worker API returned error: {parsed['error']}")

    return parsed if isinstance(parsed, dict) else {}


def _api_post(endpoint: str, payload: dict[str, Any]) -> dict[str, Any]:
    response = _api_request(endpoint, method="POST", payload=payload)
    return response if isinstance(response, dict) else {}


def fetch_next_job() -> dict[str, Any] | None:
    response = _api_request(NEXT_JOB_ENDPOINT, method="POST", payload={}, allow_no_content=True)
    if response is None:
        return None

    job = response.get("data")
    if job is None:
        return None

    if not isinstance(job, dict):
        raise RuntimeError("Worker job response must contain an object under 'data'")

    validate_payload(VIDEO_CONTRACT, "job", job)

    return job


def build_scoring_metrics(
    pose_metrics: dict[str, Any],
    *,
    profile: dict[str, Any],
    sampled_fps: float,
    max_width: int,
    max_height: int,
) -> dict[str, Any]:
    return {
        "trunk_angle": float(pose_metrics["max_trunk_angle"]),
        "neck_angle": float(pose_metrics["neck_angle"]),
        "upper_arm_angle": float(pose_metrics["upper_arm_angle"]),
        "lower_arm_angle": float(pose_metrics["lower_arm_angle"]),
        "wrist_angle": float(pose_metrics["wrist_angle"]),
        "leg_score": 1,
        "shoulder_elevation_duration": float(pose_metrics["shoulder_elevation_duration"]),
        "repetition_count": int(pose_metrics["repetition_count"]),
        "processing_confidence": float(pose_metrics["processing_confidence"]),
        "sampled_frames": int(pose_metrics.get("sampled_frames", 0)),
        "processed_pose_frames": int(pose_metrics.get("processed_pose_frames", 0)),
        "analysis_fps": float(pose_metrics.get("analysis_fps", sampled_fps)),
        "multi_person_detected_frames": int(pose_metrics.get("multi_person_detected_frames", 0)),
        "max_persons_detected": int(pose_metrics.get("max_persons_detected", 1)),
        "multi_person_policy": str(pose_metrics.get("multi_person_policy", "")),
        "model_version": str(pose_metrics.get("model_version") or profile.get("mediapipe_model") or "unknown"),
        "processing_tier": str(profile.get("tier") or "default"),
        "processing_report_depth": str(profile.get("report_depth") or "standard"),
        "processing_queue_priority": str(profile.get("queue_priority") or "normal"),
        "processing_max_duration_seconds": int(profile.get("max_duration_seconds") or 0),
        "sampled_fps_target": float(sampled_fps),
        "max_resolution_width": int(max_width),
        "max_resolution_height": int(max_height),
        "output_types": ",".join(
            [str(item).strip() for item in profile.get("output_types", []) if str(item).strip()]
        ),
        "heavy_recheck_mode": str(profile.get("heavy_recheck_mode") or ""),
    }


def process_scan_job(job: dict[str, Any]) -> None:
    job_id = str(job["job_id"])
    assessment_uuid = str(job["assessment_uuid"])
    assessment_video_uuid = str(job["assessment_video_uuid"])
    organization_uuid = str(job["organization_uuid"])
    video_path = str(job["video_path"])
    model = str(job.get("model", "reba")).lower()
    profile = job.get("processing_profile") if isinstance(job.get("processing_profile"), dict) else {}
    multi_person_policy = str(job.get("multi_person_policy") or os.getenv("VIDEO_MULTI_PERSON_POLICY", "dominant_subject")).strip().lower()
    face_blur_requested = bool(job.get("face_blur_requested", True))
    sampled_fps = float(profile.get("sampled_fps") or os.getenv("VIDEO_TARGET_FPS", "3"))
    max_width = int((profile.get("max_resolution") or {}).get("width") or 1280)
    max_height = int((profile.get("max_resolution") or {}).get("height") or 720)
    output_types = profile.get("output_types") if isinstance(profile.get("output_types"), list) else []

    if model == "niosh":
        raise ValueError("NIOSH model does not support video scans")

    frame_extractor = _frame_extractor_module()
    pose_detector = _pose_detector_module()

    frame_extractor.sample_frame_stats(video_path=video_path, sample_every_n=4)
    pose_metrics = pose_detector.estimate_pose_metrics(
        video_path=video_path,
        target_fps=sampled_fps,
        generate_visualization=("pose_video" in output_types or "blurred_video" in output_types),
        blur_faces=face_blur_requested,
        multi_person_policy=multi_person_policy,
        max_resolution=(max_width, max_height),
    )

    metrics_for_scoring = build_scoring_metrics(
        pose_metrics,
        profile=profile,
        sampled_fps=sampled_fps,
        max_width=max_width,
        max_height=max_height,
    )

    payload: dict[str, Any] = {
        "job_id": job_id,
        "assessment_uuid": assessment_uuid,
        "assessment_video_uuid": assessment_video_uuid,
        "organization_uuid": organization_uuid,
        "model": model,
        "metrics": metrics_for_scoring,
        "faces_blurred": bool(pose_metrics.get("faces_blurred", False)),
        "processing_confidence": float(pose_metrics.get("processing_confidence", 0.0)),
        "timeline": pose_metrics.get("timeline", []),
        "risky_windows": pose_metrics.get("risky_windows", []),
    }
    if str(job.get("video_sha256", "")).strip():
        payload["video_sha256"] = str(job["video_sha256"])
    if str(job.get("processing_profile_hash", "")).strip():
        payload["processing_profile_hash"] = str(job["processing_profile_hash"])
    pose_video_path = str(pose_metrics.get("pose_video_path", "")).strip()
    if pose_video_path.startswith("/storage/uploads/pose/") or pose_video_path.startswith("/storage/uploads/videos/"):
        payload["pose_video_path"] = pose_video_path
        if face_blur_requested or pose_metrics.get("faces_blurred"):
            payload["blurred_video_path"] = pose_video_path
    thumbnail_path = str(pose_metrics.get("thumbnail_path", "")).strip()
    if thumbnail_path.startswith("/storage/uploads/pose/") or thumbnail_path.startswith("/storage/uploads/videos/"):
        payload["thumbnail_path"] = thumbnail_path

    validate_payload(VIDEO_CONTRACT, "complete", payload)

    _api_post(
        COMPLETE_ENDPOINT,
        payload,
    )


def mark_scan_invalid(job: dict[str, Any], error_message: str = "") -> None:
    payload = {
        "job_id": str(job["job_id"]),
        "assessment_uuid": str(job["assessment_uuid"]),
        "assessment_video_uuid": str(job["assessment_video_uuid"]),
        "organization_uuid": str(job["organization_uuid"]),
        "error_message": (error_message or "Processing failed").strip(),
    }
    validate_payload(VIDEO_CONTRACT, "fail", payload)
    _api_post(FAIL_ENDPOINT, payload)
