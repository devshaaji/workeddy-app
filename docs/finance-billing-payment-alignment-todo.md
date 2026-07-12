# Finance, Billing, Payment Alignment Todo

Verified against active v2 code, schema builders, routes, and runtime assets on 2026-07-09.

## Verified findings

1. `Finance` code and schema exist and are now registered in `v2/bootstrap/modules.php`.
2. `Billing` is active and models billed parties as Organizations, not Customers.
3. `Payment` is active and now uses `organization_id` / `organizationId` as its canonical naming.
4. `Billing` views now use organization wording and have a working `v2/public/assets/js/billing.js`.
5. `Finance` views now have a working `v2/public/assets/js/finance.js`.

## Implementation steps

1. Completed: refactor Payment schema and code from `customer` naming to `organization` naming.
2. Completed: add a migration to rename `payment_records.customer_id` to `organization_id` for existing databases.
3. Completed: enrich Payment read models so the UI can show organization and invoice context cleanly.
4. Completed: register Finance in the active module list after asset and route verification.
5. Completed: add `finance.js` to make Finance list, form, archive, payroll refresh, and settings pages functional.
6. Completed: add `billing.js` to make Billing list, form, and detail actions functional.
7. Completed: remove stale `Customer` wording from Billing views and replace raw free-text org/quotation references with real selectable data where the system already has a source.
8. Completed: run syntax verification and targeted runtime checks on Finance, Billing, Payment, and Subscription touchpoints.
