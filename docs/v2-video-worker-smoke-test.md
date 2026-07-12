# V2 Video Worker Smoke Test

Use this after the API, database, and `video-worker` service are running locally.

## Preconditions

- `WORKER_API_TOKEN` is set in `.env`.
- `docker compose config --quiet` passes.
- V2 schema sync has applied `platform_jobs`, `assessments`, `assessment_videos`, `assessment_video_processing_results`, `uploads`, and Privacy tables.
- API and `video-worker` share the same storage volume at the v2 Storage root and `/storage`.
- At least one Assessment video is uploaded through `POST /api/v1/assessments/{assessmentUuid}/videos/upload-and-process`.

## Commands

```bash
docker compose up -d api nginx mysql schema-sync video-worker
docker compose logs -f video-worker
```

Expected worker behavior:

- Worker polls `POST /api/v1/internal/assessment-video/jobs/next`.
- Job payload contains UUIDs and a `processing_profile`.
- Job payload contains `video_sha256` and `processing_profile_hash` when queued from upload orchestration.
- Worker runs MediaPipe video mode using the profile sampling/resolution controls.
- Worker posts completion to `POST /api/v1/internal/assessment-video/jobs/complete`.
- API registers generated pose video and thumbnail through Storage before persisting their UUIDs on `assessment_videos`.
- API persists reusable metrics, timeline, risky windows, and output UUIDs in `assessment_video_processing_results`.

## Quick Failure Check

```bash
docker compose exec video-worker python /app/workers/shared/container_healthcheck.py video-worker
```

If jobs fail, check:

- `WORKER_API_TOKEN` mismatch.
- Uploaded video path is not mounted under `/storage`, or API writes to a different volume than the worker reads.
- Unsupported model, especially `niosh`.
- Profile limits are too strict for the uploaded video.
- Generated output file is missing before PHP registers it through Storage.
