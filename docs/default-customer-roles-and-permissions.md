# Default Customer Roles and Permissions

This document defines the baseline customer-scoped IAM roles for WorkEddy v2.

The seed file is `v2/seeds/110_default_customer_roles.php`. It runs after `100_demo_data.php`, creates the customer roles, syncs the permission catalog, and applies the default role-permission matrix.

## Scope Rules

- These roles are customer-scoped only.
- `organization_admin` is the canonical slug for the organization owner role.
- `org_admin` remains a compatibility alias in the broader IAM model, but it is not seeded as a separate role.
- System and platform roles such as `super_admin`, `admin`, `member`, `staff`, and `operator` are out of scope for this customer baseline.
- `audit.record` is system-only and must not be assigned to human customer roles.

## Default Roles

| Role | Purpose | Seeded Default Access |
| --- | --- | --- |
| `organization_admin` | Full organization owner and tenant administrator. | All organization, assessment, corrective action, privacy, worker voice, export, reporting, audit, and ergonomics permissions relevant to customer operations. |
| `safety_manager` | Operational safety lead who creates, reviews, and closes ergonomic work. | Broad access to organization structure, assessments, corrective actions, privacy review, worker voice analytics, exports, reporting, audit, and ergonomics scoring. |
| `supervisor` | Frontline team lead who uploads evidence and closes assigned work. | Task, assessment draft, corrective action execution, worker voice submission, and consent capture. |
| `worker` | End user who submits evidence and feedback. | Task visibility, assessment visibility, video upload when allowed, consent capture, and worker voice submission. |
| `external_reviewer` | External ergonomist or reviewer who validates outcomes. | Organization context, task visibility, assessment review and comparison, corrective action verification, worker voice review, privacy-controlled video access, and ergonomic model visibility. |

## Permission Matrix

### `organization_admin`

| Module | Permissions |
| --- | --- |
| `organization` | `organization.view`, `organization.manage`, `organization.members.manage`, `organization.structure.manage` |
| `task` | `task.view`, `task.create`, `task.update` |
| `assessment` | `assessment.view`, `assessment.create`, `assessment.update`, `assessment.review`, `assessment.lock`, `assessment.video.upload`, `assessment.comparison.view`, `assessment.comparison.generate`, `assessment.comparison.lock` |
| `corrective_action` | `corrective_action.view`, `corrective_action.generate_recommendations`, `corrective_action.review_recommendations`, `corrective_action.assign`, `corrective_action.update_status`, `corrective_action.upload_evidence`, `corrective_action.verify`, `corrective_action.manage_library` |
| `privacy` | `privacy.consent.record`, `privacy.video.access`, `privacy.retention.manage`, `privacy.retention.enforce`, `privacy.audit.view` |
| `worker_voice` | `worker_voice.submit`, `worker_voice.view`, `worker_voice.view_sensitive`, `worker_voice.aggregate.view`, `worker_voice.export` |
| `export` | `export.research.view`, `export.research.preview`, `export.research.generate`, `export.research.download` |
| `reporting` | `reporting.view`, `reporting.settings` |
| `audit` | `audit.view`, `audit.export`, `audit.settings.manage` |
| `ergonomics` | `ergonomics.score`, `ergonomics.models.view` |

### `safety_manager`

| Module | Permissions |
| --- | --- |
| `organization` | `organization.view`, `organization.members.manage`, `organization.structure.manage` |
| `task` | `task.view`, `task.create`, `task.update` |
| `assessment` | `assessment.view`, `assessment.create`, `assessment.update`, `assessment.review`, `assessment.lock`, `assessment.video.upload`, `assessment.comparison.view`, `assessment.comparison.generate`, `assessment.comparison.lock` |
| `corrective_action` | `corrective_action.view`, `corrective_action.generate_recommendations`, `corrective_action.review_recommendations`, `corrective_action.assign`, `corrective_action.update_status`, `corrective_action.upload_evidence`, `corrective_action.verify`, `corrective_action.manage_library` |
| `privacy` | `privacy.consent.record`, `privacy.video.access`, `privacy.retention.enforce`, `privacy.audit.view` |
| `worker_voice` | `worker_voice.submit`, `worker_voice.view`, `worker_voice.view_sensitive`, `worker_voice.aggregate.view`, `worker_voice.export` |
| `export` | `export.research.view`, `export.research.preview`, `export.research.generate`, `export.research.download` |
| `reporting` | `reporting.view` |
| `audit` | `audit.view`, `audit.export` |
| `ergonomics` | `ergonomics.score`, `ergonomics.models.view` |

### `supervisor`

| Module | Permissions |
| --- | --- |
| `organization` | `organization.view` |
| `task` | `task.view`, `task.create`, `task.update` |
| `assessment` | `assessment.view`, `assessment.create`, `assessment.update`, `assessment.video.upload`, `assessment.comparison.view` |
| `corrective_action` | `corrective_action.view`, `corrective_action.assign`, `corrective_action.update_status`, `corrective_action.upload_evidence` |
| `privacy` | `privacy.consent.record` |
| `worker_voice` | `worker_voice.submit`, `worker_voice.view`, `worker_voice.aggregate.view` |

### `worker`

| Module | Permissions |
| --- | --- |
| `organization` | `organization.view` |
| `task` | `task.view` |
| `assessment` | `assessment.view`, `assessment.video.upload` |
| `privacy` | `privacy.consent.record` |
| `worker_voice` | `worker_voice.submit`, `worker_voice.view` |

### `external_reviewer`

| Module | Permissions |
| --- | --- |
| `organization` | `organization.view` |
| `task` | `task.view` |
| `assessment` | `assessment.view`, `assessment.review`, `assessment.lock`, `assessment.comparison.view`, `assessment.comparison.generate`, `assessment.comparison.lock` |
| `corrective_action` | `corrective_action.view`, `corrective_action.verify` |
| `privacy` | `privacy.video.access` |
| `worker_voice` | `worker_voice.view`, `worker_voice.aggregate.view` |
| `ergonomics` | `ergonomics.models.view` |

## Seed Notes

- The permission catalog is synced from the module permission providers before role grants are applied.
- The seed is idempotent: rerunning it will not duplicate roles, permissions, or role-permission joins.
- The seed is intentionally separate from `iam:permissions:sync`; the seed defines the baseline customer role matrix, while the sync command keeps the catalog current.
- Reporting permissions are included in the seed explicitly because the Reporting module is implemented but not currently registered in `v2/bootstrap/modules.php`.
