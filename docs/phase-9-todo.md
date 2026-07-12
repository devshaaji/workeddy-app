# Phase 9 Todo

## Goal
Finish de-identified research export so Phase 9 has real module wiring, signed audited download path, and focused verification.

## Exact Gaps
- [ ] Register `Export` module in `v2/bootstrap/modules.php`
- [ ] Register `ExportSchemaBuilder` in `v2/platform/Schema/CanonicalSchemaBuilder.php`
- [ ] Ensure `research_exports` writes required timestamp fields on create/update
- [ ] Ensure signed export download issuance enforces `export.research.download`
- [ ] Ensure signed export read validates export record against storage UUID before streaming
- [ ] Ensure export signed stream audits every read
- [ ] Add module tests for provider/settings/routes/view presence
- [ ] Add signed export access tests for issue/read/expiry/audit path
- [ ] Add export generation tests for de-identification, storage registration, and code-map persistence
- [ ] Update `v2/docs/modules-map.md` with `Export` module scope and contracts
- [ ] Run focused PHPUnit verification for Export tests
- [ ] Run schema verification for canonical builder + migration presence

## Out Of Scope For This Slice
- Cross-org research dashboard aggregation
- New dashboard visualizations
- Advanced free-text redaction with NLP
- Multi-file export bundles
