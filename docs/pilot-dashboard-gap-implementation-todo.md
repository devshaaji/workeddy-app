# Pilot Dashboard Gap Implementation Todo

## Goal

Implement the Pilot Study Dashboard requirements with first-class platform data instead of inferred dashboard-only proxies.

## Completed

- [x] Add `pilot_sites` under Organization as the enrollment source of truth.
  - [x] Canonical schema + migration
  - [x] Domain model
  - [x] Repository
  - [x] Enroll/list/update use cases
  - [x] Service provider bindings
  - [x] Organization tests

- [x] Add `validation_reviews` under Assessment as the reviewer agreement source of truth.
  - [x] Canonical schema + migration
  - [x] Domain model
  - [x] Repository
  - [x] Submit/list use cases
  - [x] Agreement analytics service
  - [x] Service provider bindings
  - [x] Assessment tests

- [x] Add `supervisor_feedback` under WorkerVoice as a separate evidence stream from worker self-report.
  - [x] Canonical schema + migration
  - [x] Domain model
  - [x] Repository
  - [x] Submit/trend use cases
  - [x] Trend service
  - [x] Service provider bindings
  - [x] WorkerVoice tests

- [x] Wire Reporting to read the new sources.
  - [x] `worksites_enrolled` from `pilot_sites`
  - [x] `workers_participating` from `pilot_sites.actual_worker_count`
  - [x] `reviewer_agreement_rate` from `validation_reviews`
  - [x] supervisor feedback trend sections from `supervisor_feedback`
  - [x] industry filter path
  - [x] PDF/CSV output updates
  - [x] reporting tests

## Remaining

- [ ] Add Organization presentation/API flows for pilot site enrollment management.
  - [ ] page/controller data
  - [ ] API controller endpoints
  - [ ] routes
  - [ ] UI views

- [ ] Add Assessment presentation/API flows for validation reviews.
  - [ ] reviewer submission endpoint
  - [ ] review history endpoint
  - [ ] reviewer UI
  - [ ] adjudication/finalization workflow if multiple rounds are required

- [ ] Add WorkerVoice presentation/API flows for supervisor feedback.
  - [ ] submission endpoint
  - [ ] supervisor trend endpoint
  - [ ] UI view/forms

- [ ] Extend pilot reporting presentation polish.
  - [ ] dedicated labels for worker vs supervisor trend sections
  - [ ] explicit before/after score change block
  - [ ] export formatting tuned for publication/proposal evidence packs

- [ ] Add end-to-end coverage.
  - [ ] migration smoke test
  - [ ] repository integration tests against DBAL/sqlite
  - [ ] reporting page/export integration tests with populated pilot data

- [ ] Document operations rollout.
  - [ ] migration order
  - [ ] seed/update guidance for existing pilot customers
  - [ ] reporting backfill expectations for orgs with legacy data only
