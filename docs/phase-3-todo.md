# Phase 3 Completion Todo

## Closed In Current Slices

- Privacy module foundation: consent, access logging, retention policy, retention enforcement.
- V2-owned video worker copy and Docker service.
- UUID-based worker contract and internal worker API.
- Assessment video processing states and worker complete/fail flow.
- Signed PHP video access links with stream-time audit logging.
- Processing profiles controlling model, sampled FPS, duration, resolution, queue, report depth, outputs, retention, audit, and concurrency.
- Upload-to-processing orchestration: Storage upload, consent, Assessment attachment, and background queueing.
- Generated pose video and thumbnail output registration through Storage.
- Active Subscription plan feature maps drive Assessment video processing profiles without controller tier logic.
- Uploads enqueue source video SHA-256 and processing profile hashes for duplicate detection.
- Completed worker results persist reusable metrics, timeline data, risky windows, and generated Storage UUIDs in `assessment_video_processing_results`.
- Duplicate upload/profile pairs can reuse cached processing outputs without dispatching a worker job.
- Retention automation is available through `php bin/console privacy:video-retention:enforce` and `php cronjobs/video-retention-enforce.php`.
- V2 upload orchestration translates Storage-relative paths to worker-readable `/storage/...` paths, and Docker mounts the same volume at the v2 Storage root and worker path.

## Remaining To Fully Complete Phase 3

- Run schema sync/migration verification against a real MySQL database.
- Run `v2-video-worker` runtime smoke test against a real queued job.
- Add UI wiring for signed playback/report links beyond current stubs.
