# Identity Tenant Refactor Todo

- [x] Make public registration create a real organization and initial customer membership.
- [x] Enforce customer-scoped roles when a user is attached to an organization.
- [x] Publish a real `organization.created` event from public registration after tenant provisioning.
- [x] Make IAM user hydration use the primary active membership instead of any active membership.
- [x] Enrich organization member listings with user identity data for module-facing pickers.
- [x] Switch corrective action assignment to organization members instead of generic IAM users.
- [ ] Add explicit tenant switching for multi-organization users.
- [ ] Remove duplicated global-vs-membership role semantics from the IAM user aggregate.
