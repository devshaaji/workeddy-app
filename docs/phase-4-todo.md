# Phase 4 Corrective Action Engine Todo

## Closed in current slice

* [x] Registered `CorrectiveAction` module.
* [x] Added permissions and settings provider.
* [x] Added canonical schema and Doctrine migration.
* [x] Migrated recommendation engine services into v2.
* [x] Added recommendation generation, review, assignment, evidence, status, verification, and follow-up use cases.
* [x] Added corrective action library and recommendation rule management.
* [x] Added API routes and page stubs.
* [x] Added focused PHPUnit coverage.

## Remaining Phase 4 work

* [x] Seed default library items and recommendation rules through idempotent maintenance command.
* [x] Emit corrective action events for assignment, status update, verification, overdue, follow-up scheduled, and follow-up due.
* [x] Add scheduled overdue/follow-up maintenance command and cron wrapper.
* [x] Replace page stubs with full UX for action register, recommendation review, detail workflow, and evidence upload.
* [ ] Validate migration against shared staging DB before marking Phase 4 production complete.
