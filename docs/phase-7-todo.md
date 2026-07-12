# Phase 7 PDF Reporting Todo

## Closed in current slice

* [x] Kept PDF/export work inside existing `Reporting` module. No duplicate export module created.
* [x] Added canonical `report_artifacts` registry for generated PDF/CSV files.
* [x] Wired generated report files to persist artifact metadata: report type, source UUID, format, template, snapshot hash, actor, timestamp.
* [x] Added reporting settings for template version, methodology note, limitations note, privacy note, and planned download TTL.
* [x] Upgraded assessment snapshot path to use real `Assessment` repository data when available.
* [x] Upgraded corrective action snapshot path to use real `CorrectiveAction` repository data when available.
* [x] Added report download audit logging on every API report download response.
* [x] Extended assessment PDF template with reviewed status, score source, heat map SVG, evidence references, and settings-backed notes.
* [x] Added signed report access flow with expiring token issue and audited stream read.
* [x] Added artifact history lookup endpoint for report/source UUID pairs.
* [x] Switched report download endpoints to signed-access redirects while preserving browser download links.

## Download report scope todo

* [x] Replace direct report streaming with signed, expiring report download links backed by `Storage`.
* [x] Add report artifact lookup endpoint so UI can show generation history and latest downloadable artifact.
* [ ] Add report regeneration endpoint/version chain after reviewed data changes.
* [ ] Add pilot summary report snapshot, template, PDF, and CSV exports.
* [ ] Add comparison/corrective action template upgrades to same canonical note/evidence blocks.
* [ ] Add authorization-aware redaction policy for worker identifiers and sensitive evidence.
* [ ] Add real database migration verification and smoke test of report artifact writes/download audit.
