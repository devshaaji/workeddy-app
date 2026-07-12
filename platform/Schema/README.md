# WorkEddy Schema Layer

This directory owns the canonical DBAL schema definitions for the WorkEddy Runtime.

Migrations must delegate to these builders instead of defining module tables inline. Builders are module-scoped and should stay aligned with `ARCHITECTURE.md`: outlet identity, append-only sync events, outlet sequence tracking, idempotency, audit, IAM credentials, QR sessions, and operational jobs.
