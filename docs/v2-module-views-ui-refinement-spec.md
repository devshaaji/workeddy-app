# WorkEddy v2 Module Views UI Refinement Specification

## Purpose

This document defines UI and UX refinements for the **currently implemented v2 domain modules and their existing page views only**. It does not propose new modules. It does not treat backend structure as the primary organizing principle. It reorients the current module views around the real business workflow and the needs of the people using the platform.

The goal is to ensure WorkEddy feels like a prevention workflow system for organizations, reviewers, supervisors, and workers, rather than a developer-facing record management interface.

## Task To Perform

Before refining individual modules, the work must be framed around the business workflow instead of the repository layout.

The task is to:

1. Inventory all currently implemented v2 domain modules that have existing page views.
2. Reorder those modules by business workflow priority rather than by codebase module name.
3. Define UI refinement work for each module view so the product becomes more user-centered.
4. Remove developer-centered interaction patterns, especially raw identifier entry where the user should instead choose from meaningful system-managed options.
5. Standardize how navigation, forms, tables, actions, filters, states, and cross-module relationships are presented across the implemented modules.

## Supporting Documents

The following documents support this refinement specification and must be used as the design and implementation context:

- [ui-guide.md](/C:/xampp/htdocs/WorkEddy/v2/docs/ui-guide.md)
- [WorkEddy_Developer_Specification.md](/C:/xampp/htdocs/WorkEddy/v2/docs/WorkEddy_Developer_Specification.md)
- [modules-map.md](/C:/xampp/htdocs/WorkEddy/v2/docs/modules-map.md)
- [platform-map.md](/C:/xampp/htdocs/WorkEddy/v2/docs/platform-map.md)

## Scope Rules

- Cover only modules that are currently implemented in `v2` and have page views.
- Focus on authenticated domain workflow pages first.
- Exclude modules that are implemented but do not currently expose page views for refinement.
- Exclude unregistered or inactive modules from the refinement scope.
- Treat this document as a design specification, not an implementation checklist tied to exact code tasks.

## Business Workflow Module List

The currently implemented v2 domain modules should be considered in this workflow order:

1. IAM
2. Organization
3. Task
4. Assessment
5. CorrectiveAction
6. WorkerVoice
7. Privacy
8. Reporting
9. Export
10. Audit
11. Billing
12. Subscription
13. Payment
14. Notification
15. Storage

## Scope Exclusions

- `Ergonomics` is implemented but has no current page views to refine.
- `Website` is implemented, but its public marketing and content administration pages are outside this domain workflow refinement scope.
- `Finance` is part of the active registered module list in `v2/bootstrap/modules.php` and must be treated as a live module when refining views and workflows.

## Global UX Refinement Principles

### 1. User-Centered Over Developer-Centered

Every page must speak in the language of the user and their workflow.

- Show task names, worksite names, departments, job roles, reviewer names, and user names.
- Do not expose internal IDs, UUIDs, foreign keys, or storage references as primary interaction inputs.
- Replace raw record linking with dropdowns, searchable selectors, typeahead search, pickers, or contextual preselection.
- Use labels that explain the work, not the schema.

Example:

- Do not ask a user to enter `task_id`, `organization_id`, `reviewer_id`, or `storage_uuid`.
- Provide a task selector, worksite selector, reviewer picker, or system-linked file panel instead.

### 2. Workflow First

Each page must help the user understand:

- where they are,
- what this page is for,
- what to do next,
- what happens after completion.

The interface must guide the prevention workflow from setup to evidence capture, review, action, follow-up, reporting, and governance.

### 3. Human Meaning Before System Meaning

Tables, cards, filters, and forms should prioritize:

- task description before task code,
- worksite and department before internal org references,
- worker or reviewer role before account metadata,
- review status before technical processing status.

### 4. Relationship Selection Must Be Assisted

Any page that links records across modules must use user-friendly selection patterns.

- Use dropdowns for short lists.
- Use searchable comboboxes for long lists.
- Use contextual defaults when the relationship is already known.
- Show helper text explaining why the user is selecting the related item.

### 5. Statuses Must Be Actionable

Statuses should not be passive labels only.

- If an assessment is pending review, the page should show who needs to act next.
- If corrective action evidence is overdue, the page should show the next required step.
- If access is restricted by role or privacy policy, the page should explain the reason and available alternatives.

### 6. Empty States Must Teach

Empty states must explain:

- why nothing is shown,
- how to create or request the first record,
- whether access, filters, or workflow stage is the reason.

### 7. Forms Must Reduce Cognitive Load

- Group fields by user intent.
- Hide advanced or secondary fields until needed.
- Pre-fill known values where safe.
- Use plain-language help text.
- Show validation errors near the relevant field and in language the user understands.

### 8. Tables Must Support Work, Not Just Display Data

Every major list view should support:

- meaningful default sorting,
- understandable filters,
- visible record counts,
- clear row actions,
- mobile-friendly summaries,
- a readable empty state,
- bulk actions only where the workflow truly benefits.

### 9. Role Sensitivity Without UX Fragmentation

Role-based experiences should stay coherent.

- Users should not feel punished by hidden system behavior.
- If an action is unavailable, the page should clarify whether it is unavailable because of role, workflow stage, privacy rules, or missing prerequisite data.

### 10. Consistency Across Modules

Shared interaction patterns should be standardized across modules.

- Same vocabulary for review states, verification, evidence, export, and report generation.
- Same page header behavior and action prioritization.
- Same relationship selection conventions.
- Same card, table, modal, and filter behavior where the task type is equivalent.

## Cross-Module Interaction Standards

### Relationship Inputs

- Never make users type identifiers for related records.
- All record relationships should use a visible label and hidden system value pattern.
- The selected option should show enough context to avoid mistakes.

Examples:

- Task selector: task name, worksite, department
- User selector: full name, role, organization scope
- Reviewer selector: full name, credential, role
- Report target selector: assessment title, date, status

### Filters

Default filter groups across modules should prefer:

- organization
- worksite
- department
- job role
- task
- owner or assigned person
- date range
- workflow status

### Row Actions

Use the same action order where relevant:

- View
- Continue or Resume
- Edit
- Review or Verify
- Export or Generate Report
- Archive, Suspend, Delete, or other destructive action last

### State Language

Avoid overly technical state labels when a human-readable equivalent exists.

- Prefer `Pending Review` over `queued`
- Prefer `Needs Evidence` over `missing_attachment`
- Prefer `Ready for Report` over `report_eligible`

Technical details can still appear in secondary metadata where necessary.

## Module Refinement Specifications

### 1. IAM

**Workflow role:** user access, identity, account administration, and self-service security.

**Current page views:**

- Auth: `login`, `register`, `forgot_password`, `reset_password`, `verify_otp`
- Users: `index`, `create`, `edit`, `show`, `assign_role`, `security`, `pending_approvals`
- Roles: `index`, `create`, `edit`, `show`, `assign_permissions`
- Permissions: `index`
- Profile: `show`, `security`, `sessions`
- Settings: `index`

**UX risks to correct:**

- User and role administration can easily read like raw system administration instead of organization access management.
- Role assignment and permission management may expose too much implementation vocabulary.
- Security pages can become overly technical and intimidating for non-technical admins.

**Refinement tasks:**

- Reframe user management around people, responsibility, and access scope rather than internal account records.
- Replace any direct role or permission entry patterns with guided selection and explanation.
- Make account creation flows explain organization, worksite, department, and job role relevance clearly.
- Separate self-service profile/security flows from admin-management flows visually and structurally.
- Present permissions as grouped business capabilities, not only technical nodes.
- Ensure pending approvals show who is waiting, why approval is needed, and what decision options are available.

### 2. Organization

**Workflow role:** organization structure setup and operational scope definition.

**Current page views:**

- `organizations`
- `organization_show`
- `members`
- `worksites`
- `departments`
- `job_roles`
- `pilot_sites`

**UX risks to correct:**

- Structure pages can become master-data screens instead of setup tools for real operating environments.
- Members, worksites, departments, and job roles may feel disconnected from downstream tasks and assessments.

**Refinement tasks:**

- Present organization setup as the foundation for task capture, assessments, and corrective action ownership.
- Use guided create and edit flows for worksites, departments, and job roles.
- Show downstream usage context, such as where a worksite or job role is used in tasks and assessments.
- Replace any internal reference entry with clear selectors.
- Make member management highlight operational role and scope, not just account status.
- Ensure pilot site pages explain their reporting and evidence-generation purpose.

### 3. Task

**Workflow role:** define the work being assessed and tracked.

**Current page views:**

- `index`
- `show`
- `edit`

**UX risks to correct:**

- Task records can feel like generic records rather than the anchor of the ergonomic workflow.
- Task details may not clearly connect to assessment, worker voice, corrective action, and reporting.

**Refinement tasks:**

- Make task list and detail pages emphasize operational meaning: what work is being done, where, by whom, and why it matters.
- Show related worksite, department, job role, exposure context, and linked assessments in human-readable form.
- Add guided task editing structure so the user updates task context without needing to think about backend relationships.
- Ensure every task page makes the next workflow actions obvious, such as capture assessment, review history, view feedback, or follow up.

### 4. Assessment

**Workflow role:** capture, score, review, compare, and validate ergonomic risk.

**Current page views:**

- `index`
- `show`
- `manual_form`
- `video_capture`
- `video_evidence`
- `review`
- `reviewer_queue`
- `validation_reviews`
- `heatmap`
- `comparisons`
- `comparison_create`
- `comparison_show`

**UX risks to correct:**

- This is the most critical workflow module and the highest risk for developer-centered interactions.
- Video, scoring, review, and comparison steps can become fragmented.
- Users may be forced to interpret technical status instead of business progress.
- Related record entry may drift into raw IDs or unassisted linking.

**Refinement tasks:**

- Treat assessment creation as a guided workflow, not a free-form record form.
- Replace any relationship ID inputs with task, worksite, reviewer, and worker-friendly selectors.
- Break the manual assessment form into clear intent-based sections such as task context, exposure, posture and force factors, body-region impact, and submission.
- Make video capture and video evidence pages explain consent, privacy, processing status, and next steps clearly.
- Ensure reviewer queue prioritizes urgency, due work, status clarity, and decision readiness.
- Make review pages clearly distinguish estimated score, manual score, and reviewer-confirmed final score.
- Ensure heatmap pages are explanatory, not only visual.
- Make comparison flows explain baseline, follow-up, and improvement evidence in plain language.
- Provide strong empty and locked states for cases where review or comparison is not yet possible.

### 5. CorrectiveAction

**Workflow role:** turn identified risk into tracked interventions and follow-up.

**Current page views:**

- `recommendations`
- `actions`
- `action_show`
- `controls`
- `evidence`

**UX risks to correct:**

- Corrective action pages may read like administrative tracking instead of intervention workflow.
- Library and recommendation screens may be too system-oriented.
- Evidence upload may feel disconnected from action completion and verification.

**Refinement tasks:**

- Organize corrective action pages around the flow: recommendation, review, assignment, completion, evidence, verification, follow-up.
- Make recommendation pages explain why each recommendation exists and which risk factors it addresses.
- Ensure action assignment uses user-friendly assignee selectors rather than any direct identifier references.
- Show due date, urgency, status, and evidence requirements prominently.
- Make controls and rule-management screens understandable to authorized non-developer administrators.
- Ensure evidence pages make it clear what qualifies as proof, who needs to review it, and what happens next.

### 6. WorkerVoice

**Workflow role:** collect worker and supervisor feedback on discomfort, strain, and practical suggestions.

**Current page views:**

- `index`
- `show`
- `submit`
- `supervisor_submit`
- `trends`
- `supervisor_trends`

**UX risks to correct:**

- Feedback submission can easily become intimidating, unclear, or too form-heavy.
- Anonymous and non-anonymous experiences may not be clear enough.
- Trend pages may display data without enough operational interpretation.

**Refinement tasks:**

- Make worker and supervisor submission pages short, supportive, and privacy-aware.
- Explain anonymity, consent, and visibility rules plainly.
- Use task and work context selectors rather than any manual identifier entry.
- Break long forms into digestible sections.
- Make trend pages useful for action by linking discomfort patterns back to task and assessment context.
- Ensure the detail page clearly distinguishes reported concern, context, and suggested follow-up.

### 7. Privacy

**Workflow role:** govern consent, retention, and sensitive media access.

**Current page views:**

- `consent`
- `retention`
- `video_access_log`

**UX risks to correct:**

- Privacy pages can become legal or technical repositories rather than operational governance tools.
- Access logs can be hard to interpret for admins.

**Refinement tasks:**

- Present consent pages in plain, confidence-building language.
- Make retention settings understandable in terms of operational and privacy consequences.
- Show video access logs as accountability records with clear actor, action, reason, and time.
- Avoid exposing raw storage references or technical implementation details as the main content.

### 8. Reporting

**Workflow role:** generate, review, and navigate evidence outputs.

**Current page views:**

- `Index/index`
- `Assessment/index`
- `Comparison/index`
- `Corrective-action/index`
- `Audit-trial/index`
- `Finance/index`
- `Operations/index`
- `Pilot-summary/index`

**UX risks to correct:**

- Reporting can become a list of report types without enough user guidance.
- Users may not know which report to use for which purpose.

**Refinement tasks:**

- Reframe reporting around user goals such as assessment evidence, follow-up comparison, operational summary, pilot summary, and audit proof.
- Use guided report selection with plain-language explanations.
- Make report readiness visible so users understand missing prerequisites.
- Ensure report target selection uses searchable record pickers instead of IDs.
- Clarify whether a report is draft, final, review-backed, or export-ready.

### 9. Export

**Workflow role:** generate de-identified research and evidence exports.

**Current page views:**

- `Index/index`

**UX risks to correct:**

- Export workflows often expose too much technical detail and too little clarity on privacy implications.

**Refinement tasks:**

- Make export generation a guided workflow with scope, filters, preview, privacy explanation, and final generation.
- Explain what is removed, what is retained, and who can access the result.
- Present date range, site, task, and status filters using business-friendly labels.
- Show signed download access and expiration in user language.

### 10. Audit

**Workflow role:** operational accountability and security traceability.

**Current page views:**

- Log: `index`, `show`, `export`
- Settings: `index`

**UX risks to correct:**

- Audit pages can feel like raw log viewers.
- Non-technical admins may struggle to interpret action history.

**Refinement tasks:**

- Present audit logs as a traceable activity timeline with actor, action, object, reason, and time.
- Use business-friendly action labels where possible.
- Provide filters aligned to real investigative questions.
- Ensure export flows explain why and when to export audit records.
- Make settings pages describe retention, sensitivity, and governance outcomes clearly.

### 11. Billing

**Workflow role:** manage quotations, invoices, and billing configuration for organizations.

**Current page views:**

- `quotations`
- `quotation_form`
- `quotation_detail`
- `invoices`
- `invoice_form`
- `invoice_detail`
- `settings`

**UX risks to correct:**

- Billing pages may inherit invoice-centric admin patterns without enough organization context.
- Quotes and invoices may feel disconnected from subscription and payment states.

**Refinement tasks:**

- Present quotations and invoices in terms of organization billing workflow rather than isolated accounting records.
- Use organization selectors and contextual summaries instead of manual relationship entry.
- Clarify draft, issued, paid, overdue, and cancelled states with next-step actions.
- Make detail pages show linked subscription or payment context where relevant.
- Ensure settings explain billing behavior in business terms.

### 12. Subscription

**Workflow role:** manage plan access, entitlements, and subscription state.

**Current page views:**

- `Index/index`
- `Index/detail`
- `Index/settings`

**UX risks to correct:**

- Subscription pages can become overly internal, especially around limits and state changes.

**Refinement tasks:**

- Present plans, usage, and limits in organization-operational language.
- Show what the current plan enables for worksite, user, assessment, export, and video workflows.
- Make suspension, renewal, and reactivation implications easy to understand.
- Keep technical feature maps out of the primary UI unless translated into user meaning.

### 13. Payment

**Workflow role:** record and review payment activity.

**Current page views:**

- `index`

**UX risks to correct:**

- Payment logs can feel detached from invoice and subscription workflows.

**Refinement tasks:**

- Present payment records as part of a billing resolution workflow.
- Show organization, invoice, amount, method, reference, and payment outcome clearly.
- Link payment outcomes back to invoice and subscription consequences where relevant.

### 14. Notification

**Workflow role:** message templates, logs, and communication settings.

**Current page views:**

- `Template/index`
- `Log/index`
- `Settings/index`

**UX risks to correct:**

- Notification configuration screens may be too implementation-focused.
- Message logs may not help users understand communication outcomes.

**Refinement tasks:**

- Group templates by use case, such as account access, billing, assessment workflow, and corrective action updates.
- Show message logs with recipient, purpose, channel, status, and sent time in a readable way.
- Explain settings in terms of communication outcomes rather than technical channels only.

### 15. Storage

**Workflow role:** admin visibility into stored files and storage usage.

**Current page views:**

- `admin_storage`

**UX risks to correct:**

- Storage interfaces can become pure infrastructure surfaces.

**Refinement tasks:**

- Keep this page clearly administrative and restricted, but still human-readable.
- Show file purpose, owning workflow, sensitivity level, and retention context rather than raw storage-only metadata.
- Ensure no workflow depends on end users interacting with raw storage concepts where a higher-level interface should exist.

## Common Anti-Patterns To Remove

- Any form field asking for a record ID when the system could present a selector.
- Labels that mirror database column names.
- Screens that show system states without explaining next actions.
- Tables with technical columns but no workflow guidance.
- Review pages that force users to infer what is awaiting review.
- Admin pages that assume the user understands module internals.

## Acceptance Criteria For UI Refinement

- Implemented module views are ordered and evaluated by business workflow, not code ownership alone.
- The design specification explicitly places supporting documents and task framing before the module list.
- No user-facing workflow relies on entering raw internal identifiers for related records where selection is possible.
- Module pages speak in user-centered, operational language.
- Relationships across modules are expressed through understandable selectors and context.
- Statuses, empty states, errors, and locked states explain what the user should do next.
- Cross-module patterns are consistent enough that moving between modules does not feel like switching products.

## Implementation Note

When implementation begins, the UI guide remains the source for layout and component conventions, but these refinements take precedence for workflow language, relationship selection, and user-centered interaction behavior.
