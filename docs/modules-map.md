# WorkEddy v2 Modules Map
## The Definitive Guide to Cross-Module Integration and Exposed Contracts

This document maps the domain modules under `v2/modules/` and exposes the specific contracts (interfaces, repositories, and use cases) that other modules can consume.

---

## 1. Integration Standards

To preserve the modularity of the monolith, modules must adhere to the following rules:
* **No Direct DB Access**: A module must never query or modify database tables owned by another module directly.
* **No Model Leakage**: Avoid passing complex ORM/Domain entity objects across module boundaries. Instead, communicate via **Interfaces/Contracts** and simple **DTOs**.
* **Preferred Communication**:
  1. **Synchronous**: Inject the exposed interface/contract of the target module in the constructor (configured via PHP-DI definitions).
  2. **Asynchronous**: Publish an event via `EventPublisherInterface` and let the target module subscribe to it.

---

## 2. Directory of Registered Domain Modules

Below is the catalog of the 19 active domain modules registered in `v2/bootstrap/modules.php`.

### 2.1 Audit
Tracks, serializes, and logs user-initiated state changes and security events.

* **Exposed Contracts**:
  * `WorkEddy\Platform\Audit\IAuditService` (Resolves to `AppendOnlyAuditService` using DBAL backends)
* **Key DTOs**: None (uses primitive parameters for recording)
* **Usage Example**:
  ```php
  use WorkEddy\Platform\Audit\IAuditService;

  class DeleteRecordHandler {
      public function __construct(private IAuditService $audit) {}

      public function delete(string $uuid, string $userId): void {
          // Record action to the audit trail
          $this->audit->record(
              action: 'deleted',
              entityType: 'UserRecord',
              entityId: $uuid,
              beforeState: ['status' => 'active'],
              afterState: null,
              actorId: $userId,
              actorType: 'user'
          );
      }
  }
  ```

---

### 2.2 IAM (Identity & Access Management)
Orchestrates authentication, session verification, user profiles, privilege checks, and organization-scoped authorizations.

* **Exposed Contracts**:
  * `WorkEddy\Modules\IAM\Domain\Contracts\IAuthService`: Handles logins and logouts.
  * `WorkEddy\Modules\IAM\Domain\Contracts\IUserContextService`: Fetches a user's session profile context.
  * `WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService`: Asserts and enforces permission checks.
  * `WorkEddy\Modules\IAM\Domain\Contracts\IDepartmentScopeAuthorizer`: Inspects scoped organization/department access rules.
* **Key DTOs**:
  * `WorkEddy\Platform\Session\UserContext`: Holds active user ID, active organization scope, membership scope, role type, and resolved permissions.
  * `WorkEddy\Modules\IAM\Application\DTOs\UserDTO`: Canonical user view shape for API and presentation.
* **Canonical Shape**:
  * Public-facing user responses expose `uuid`, `email`, `profile`, and `membership`.
  * `profile` owns `fullName`, `phone`, `status`, `lastLoginAt`, and `createdAt`.
  * `membership` owns organization scope, role metadata, and worksite/department/job-role references.
* **Settings**:
  * `WorkEddy\Modules\IAM\Settings\IAMSettingsProvider`
  * `WorkEddy\Modules\IAM\Settings\IAMSettings`
* **Usage Example**:
  ```php
  use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
  use WorkEddy\Platform\Session\ISessionService;
  use WorkEddy\Shared\Exceptions\AuthenticationException;

  class ResourceController {
      public function __construct(
          private IPermissionService $permissions,
          private ISessionService $session
      ) {}

      public function execute(): void {
          $ctx = $this->session->getUserContext();
          if ($ctx === null) {
              throw new AuthenticationException('Unauthenticated');
          }

          // Asserts user has access to view settings; throws ForbiddenException if unauthorized
          $this->permissions->requirePrivilege($ctx, 'settings.view');
      }
  }
  ```

---

### 2.3 Organization
Owns the tenant structure used by IAM and operational modules: organizations, worksites, departments, job roles, and organization membership invitations.

* **Exposed Contracts**:
  * `WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository`
  * `WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository`
  * `WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository`
  * `WorkEddy\Modules\Organization\Domain\Contracts\IJobRoleRepository`
* **Key Use Cases**:
  * `WorkEddy\Modules\Organization\Application\CreateOrganizationUseCase`
  * `WorkEddy\Modules\Organization\Application\UpdateOrganizationStatusUseCase`: transitions an organization between `active`/`suspended`/`deleted` (soft-delete via `IOrganizationRepository::softDelete()`, never a hard delete). Exposed at `PATCH /api/v1/organizations/{id}/status`.
  * `WorkEddy\Modules\Organization\Application\CreateWorksiteUseCase`
  * `WorkEddy\Modules\Organization\Application\CreateDepartmentUseCase`
  * `WorkEddy\Modules\Organization\Application\CreateJobRoleUseCase`
  * `WorkEddy\Modules\Organization\Application\InviteOrganizationMemberUseCase`
* **Canonical Scope**:
  * All public organization-facing identifiers are UUIDs.
  * Internal persistence and joins use numeric IDs.
  * `subscriptions_organization_fk` is `ON DELETE RESTRICT` (see `Version20260708150000_RestrictSubscriptionOrganizationCascade`) — a hard-delete of an organization with an existing subscription fails at the DB level; the only supported path is `UpdateOrganizationStatusUseCase` with `newStatus: 'deleted'`, which cancels the subscription through `Subscription\CancelSubscription` (audited, event-published) before the org itself is soft-deleted.
* **Published Events** (via `EventPublisherInterface`):
  * `organization.created` (payload: `organization_id`, `organization_uuid`, `name`, `slug`, `contact_email`) — published by `CreateOrganizationUseCase` after commit. Subscription's `ProvisionDefaultSubscription` listener subscribes to this to optionally auto-activate a default plan (off by default; see `SubscriptionSettings::autoProvisionOnSignup`).
  * `organization.status_changed` (payload: `organization_id`, `organization_uuid`, `old_status`, `new_status`, `reason`) — published by `UpdateOrganizationStatusUseCase`. Subscription's `SuspendSubscriptionOnOrganizationSuspended` listener subscribes to this: suspends the subscription on `suspended`, only auto-reactivates on `active` if the subscription was suspended specifically for that reason (won't clobber a subscription suspended for non-payment), and cancels the subscription on `deleted`.
* **Settings**:
  * `WorkEddy\Modules\Organization\Settings\OrganizationSettingsProvider`
  * `WorkEddy\Modules\Organization\Settings\OrganizationSettings`

---

### 2.4 Task
Owns organization-scoped task records used by Phase 1 workflow and future operational modules.

* **Exposed Contracts**:
  * `WorkEddy\Modules\Task\Domain\Contracts\ITaskRepository`
* **Key Use Cases**:
  * `WorkEddy\Modules\Task\Application\CreateTaskUseCase`
  * `WorkEddy\Modules\Task\Application\ListTasksUseCase`
  * `WorkEddy\Modules\Task\Application\UpdateTaskUseCase`
* **Canonical Scope**:
  * Task responses expose UUIDs publicly.
  * Task records are filtered by `organization_id` internally.
  * Task linkage to `worksite`, `department`, and `job role` is optional and organization-scoped.
* **Settings**:
  * `WorkEddy\Modules\Task\Settings\TaskSettingsProvider`
  * `WorkEddy\Modules\Task\Settings\TaskSettings`

---

### 2.5 Ergonomics
Owns deterministic ergonomic scoring for REBA, RULA, and NIOSH. This module is pure scoring infrastructure used by Assessment and future video workflows.

* **Exposed Contracts**:
  * `WorkEddy\Modules\Ergonomics\Domain\Services\ErgonomicAssessmentInterface`
* **Key Services and Use Cases**:
  * `WorkEddy\Modules\Ergonomics\Domain\Services\AssessmentEngine`
  * `WorkEddy\Modules\Ergonomics\Domain\Services\RebaService`
  * `WorkEddy\Modules\Ergonomics\Domain\Services\RulaService`
  * `WorkEddy\Modules\Ergonomics\Domain\Services\NioshService`
  * `WorkEddy\Modules\Ergonomics\Application\ScoreErgonomicAssessmentUseCase`
* **Canonical Output**:
  * Scoring responses expose `model`, `inputType`, `scoreSource`, `score`, `details`, and `algorithmVersion`.
  * `scoreSource` is `manual` for manual inputs and `ai_estimated` for video inputs.
  * Raw model details preserve v1 fields such as `raw_score`, `normalized_score`, `risk_category`, and `algorithm_version`.
* **Settings**:
  * `WorkEddy\Modules\Ergonomics\Settings\ErgonomicsSettingsProvider`
  * `WorkEddy\Modules\Ergonomics\Settings\ErgonomicsSettings`

---

### 2.6 Assessment
Owns the assessment workflow around ergonomic scoring: manual assessment capture, risk factor checklist, body-region evidence, video evidence metadata, reviewer validation, and immutable locked final scores.

* **Exposed Contracts**:
  * `WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository`
* **Key Use Cases**:
  * `WorkEddy\Modules\Assessment\Application\CreateManualAssessmentUseCase`
  * `WorkEddy\Modules\Assessment\Application\SubmitAssessmentForReviewUseCase`
  * `WorkEddy\Modules\Assessment\Application\AttachAssessmentVideoUseCase`
  * `WorkEddy\Modules\Assessment\Application\EnqueueAssessmentVideoProcessingUseCase`
  * `WorkEddy\Modules\Assessment\Application\ClaimAssessmentVideoJobUseCase`
  * `WorkEddy\Modules\Assessment\Application\CompleteAssessmentVideoJobUseCase`
  * `WorkEddy\Modules\Assessment\Application\FailAssessmentVideoJobUseCase`
  * `WorkEddy\Modules\Assessment\Application\UploadAssessmentVideoForProcessingUseCase`
  * `WorkEddy\Modules\Assessment\Application\ReviewAssessmentUseCase`
  * `WorkEddy\Modules\Assessment\Application\GetAssessmentUseCase`
  * `WorkEddy\Modules\Assessment\Application\ListAssessmentsUseCase`
* **Canonical Shape**:
  * Public assessment, organization, task, video, and storage references use UUIDs.
  * Internal persistence and joins use numeric IDs.
  * `scoreSource` is `manual`, `ai_estimated`, or `reviewer_confirmed`.
  * Reviewed assessments expose `finalScore`; reports must use `finalScore`, not raw input.
  * Locked assessments are immutable for workflow-changing actions such as video attachment.
* **Video Evidence**:
  * `assessment_videos` stores storage-file UUID, original filename, MIME type, size, duration, consent text version, face-blur request flag, and processing status.
  * `assessment_video_processing_results` stores reusable video SHA-256/profile-hash processing outputs, metrics, timeline data, risky windows, and generated Storage UUIDs so reports can be regenerated without rerunning MediaPipe.
  * Video processing moves through `pending`, `queued`, `processing`, `completed`, and `failed`.
  * Worker payloads use assessment/video/storage UUIDs; PHP remains the scoring and persistence authority.
  * Processing profiles control MediaPipe model, sampled FPS, duration limits, max resolution, queue priority, report depth, outputs, retention, audit requirements, and worker concurrency.
  * Active Subscription plan feature maps can override the processing profile without hardcoding tier logic in controllers.
  * Duplicate uploads with the same source hash and processing profile hash can reuse cached outputs instead of dispatching a worker job.
  * Generated pose videos and thumbnails must be registered through Storage before UUID references are stored on `assessment_videos`.
  * The upload orchestration path stores raw video through Storage, records Privacy consent, attaches video metadata, and queues processing without running MediaPipe in the request cycle.
  * Storage-relative upload paths are translated to the worker storage mount before queue dispatch.
  * Raw video bytes remain owned by Storage; Assessment stores references and consent/review metadata only.
* **Settings**:
  * `WorkEddy\Modules\Assessment\Settings\AssessmentSettingsProvider`
  * `WorkEddy\Modules\Assessment\Settings\AssessmentSettings`

---

### 2.7 Privacy
Owns privacy-by-design controls for video evidence and sensitive assessment media: consent records, video access logs, organization retention policy, and raw-video retention enforcement.

* **Exposed Contracts**:
  * `WorkEddy\Modules\Privacy\Domain\Contracts\IPrivacyRepository`
* **Key Use Cases**:
  * `WorkEddy\Modules\Privacy\Application\RecordVideoConsentUseCase`
  * `WorkEddy\Modules\Privacy\Application\LogVideoAccessUseCase`
  * `WorkEddy\Modules\Privacy\Application\IssueSignedVideoAccessUseCase`
  * `WorkEddy\Modules\Privacy\Application\ReadSignedVideoAccessUseCase`
  * `WorkEddy\Modules\Privacy\Application\UpdateRetentionPolicyUseCase`
  * `WorkEddy\Modules\Privacy\Application\EnforceVideoRetentionUseCase`
  * `WorkEddy\Modules\Privacy\Console\EnforceVideoRetentionCommand`
* **Canonical Scope**:
  * Public references use UUIDs for organization, assessment, and storage file identity.
  * Internal policy ownership uses `organization_id`.
  * Privacy does not own raw files; it calls Storage by storage file UUID when policy enforcement requires deletion.
* **Video Privacy Rules**:
  * Consent stores text version, acceptance timestamp, actor, IP address, user agent, assessment UUID, and storage file UUID.
  * Video access logs store who accessed evidence, when, why, and from which request context.
  * Signed video links are short-lived PHP API tokens; raw storage paths are not public API output.
  * Scheduled retention enforcement runs through `php bin/console privacy:video-retention:enforce` or `php cronjobs/video-retention-enforce.php`.
  * Retention policy supports retaining raw video for review, deleting after processing, or retaining de-identified evidence only.
* **Settings**:
  * `WorkEddy\Modules\Privacy\Settings\PrivacySettingsProvider`
  * `WorkEddy\Modules\Privacy\Settings\PrivacySettings`

---

### 2.8 Notification
Dispatches multi-channel messages (In-App, Email, SMS, WhatsApp) based on templated payloads.

* **Exposed Contracts**:
  * `WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface`
* **Key DTOs**:
  * `WorkEddy\Modules\Notification\Domain\NotificationRequest`
  * `WorkEddy\Modules\Notification\Domain\NotificationRecipient`
  * `WorkEddy\Modules\Notification\Domain\NotificationType`
  * `WorkEddy\Modules\Notification\Domain\NotificationChannel` (Enum: `IN_APP`, `EMAIL`, `SMS`, `WHATSAPP`)
  * `WorkEddy\Modules\Notification\Domain\NotificationPriority` (Enum: `LOW`, `NORMAL`, `HIGH`, `URGENT`)
* **Usage Example**:
  ```php
  use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;
  use WorkEddy\Modules\Notification\Domain\NotificationRequest;
  use WorkEddy\Modules\Notification\Domain\NotificationRecipient;
  use WorkEddy\Modules\Notification\Domain\NotificationType;
  use WorkEddy\Modules\Notification\Domain\NotificationChannel;

  class TicketAlert {
      public function __construct(private NotificationServiceInterface $notifications) {}

      public function sendAlert(string $email, string $name, string $ticketId): void {
          $recipient = new NotificationRecipient(
              recipientId: $ticketId,
              recipientType: 'ticket',
              name: $name,
              email: $email
          );

          $request = new NotificationRequest(
              type: new NotificationType('ticket.created'),
              recipient: $recipient,
              data: ['ticket_id' => $ticketId],
              preferredChannel: NotificationChannel::EMAIL
          );

          $this->notifications->send($request);
      }
  }
  ```

---

### 2.9 Storage
A unified file repository abstracting local storage and cloud providers, managing temporary/permanent uploads and deletion flags.

* **Exposed Contracts**:
  * `WorkEddy\Modules\Storage\Domain\Contracts\IStorageService`
* **Key DTOs**:
  * `WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest`
  * `WorkEddy\Modules\Storage\Application\DTOs\StoredFileDTO`
* **Usage Example**:
  ```php
  use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
  use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;

  class DocumentUploader {
      public function __construct(private IStorageService $storage) {}

      public function saveFile(string $tempPath, string $fileName): string {
          $request = new StoreUploadedFileRequest($tempPath, $fileName, 'application/pdf');
          $fileDto = $this->storage->storeUploadedFile($request);
          return $fileDto->uuid; // Unique key to retrieve file later
      }
  }
  ```

---

### 2.10 Billing
Manages quotation compilation, invoice records, and PDF exports. The billed party is always an **Organization** — there is no Customer/CRM module anywhere in this codebase (no module directory, no repository interface, not registered in `bootstrap/modules.php`). Legacy `customers` / `customer_contacts` / `customer_addresses` tables still exist in the database from the pre-rework schema but are orphaned: no module owns or reads/writes them any more. Before `Version20260708140000_RemoveCustomerConceptFromBilling`, `GeneratePdf` referenced a non-existent `ICustomerRepository` class and fatally errored on every PDF generation; this is now fixed.

* **Exposed Contracts**:
  * `WorkEddy\Modules\Billing\Domain\Contracts\IInvoiceRepository`
  * `WorkEddy\Modules\Billing\Domain\Contracts\IQuotationRepository`
* **Key Use Cases**:
  * `WorkEddy\Modules\Billing\Application\UseCases\GenerateInvoice`
  * `WorkEddy\Modules\Billing\Application\UseCases\GenerateQuotation`
  * `WorkEddy\Modules\Billing\Application\UseCases\GeneratePdf`
* **Canonical Scope**:
  * `billing_invoices.organization_id` / `billing_quotations.organization_id` are integer Organization ids. Fresh installs get a `RESTRICT` foreign key to `organizations.id` directly from `BillingSchemaBuilder`; databases migrated from the pre-rework schema do **not** get this FK automatically (their old `customer_id` values referenced the legacy `customers` table, not `organizations`, and may not satisfy referential integrity — see the migration's docblock for the required data-cleanup follow-up before a FK can be added retroactively).
  * `billing_invoices.subscription_uuid` (nullable) links an invoice back to the `Subscription` that generated it.
  * `GeneratePdf::enrichData()` resolves the billed party's display name/email/phone via `IOrganizationRepository::findById()`.
* **Published Events** (via `EventPublisherInterface`): `invoice.paid` (payload includes `invoice_id`, `organization_id`, `subscription_uuid`, `amount`) — published by `PaymentCompletedListener` once an invoice is fully paid. Subscription's `ReactivateSubscriptionOnInvoicePaid` listener subscribes to this to auto-reactivate a suspended subscription once its invoice clears.
* **`IInvoiceRepository::listOverdueSubscriptionInvoices()`**: unpaid/partial subscription-linked invoices past their due date, as of a given instant. Powers Subscription's `SuspendOverdueSubscriptions` dunning sweep; does not depend on anything ever flipping an invoice's status to `overdue`.
* **Current UI Status**: the admin invoice/quotation table views now have a concrete `public/assets/js/billing.js` runtime for list, create, status, and archive workflows. Proration and renewal invoicing are still handled entirely from the Subscription side (`GenerateProrationInvoiceOnPlanChange`, `GenerateInvoiceOnRenewal`) calling `GenerateInvoice`; Billing itself has no `subscription.*` listeners of its own.
* **Usage Example**:
  ```php
  use WorkEddy\Modules\Billing\Application\UseCases\GenerateInvoice;

  class SubscriptionActivatedHandler {
      public function __construct(private GenerateInvoice $generateInvoice) {}

      public function onActivated(int $organizationId, string $subscriptionUuid): void {
          $invoice = $this->generateInvoice->execute(
              organizationId: $organizationId,
              quotationUuid: null,
              items: [['description' => 'Professional plan', 'quantity' => 1, 'unit_price' => 99.00]],
              currency: 'USD',
              daysUntilDue: 14,
              actorId: null,
              subscriptionUuid: $subscriptionUuid,
          );
      }
  }
  ```

---

### 2.11 Payment
Handles manual transaction logging, integration gateway handshakes (e.g. Paystack), and checkout workflow registrations.

* **Exposed Contracts**:
  * `WorkEddy\Modules\Payment\Domain\Contracts\IPaymentRecordRepository`
* **Key Use Cases**:
  * `WorkEddy\Modules\Payment\Application\UseCases\RecordManualPayment`
  * `WorkEddy\Modules\Payment\Application\UseCases\ProcessOnlinePayment`
  * `WorkEddy\Modules\Payment\Application\UseCases\CreateGatewayCheckout`
* **Canonical Scope**: `payment_records.organization_id` is the Organization foreign-reference used by manual and gateway payment workflows. Payment read models now expose invoice and organization context directly so the UI can present payments as part of the organization billing workflow.
* **Usage Example**:
  ```php
  use WorkEddy\Modules\Payment\Application\UseCases\RecordManualPayment;

  class InvoicePaymentController {
      public function __construct(private RecordManualPayment $recordPayment) {}

      public function pay(string $invoiceUuid, float $amount, string $txReference): void {
          $this->recordPayment->execute([
              'invoice_uuid' => $invoiceUuid,
              'amount' => $amount,
              'reference' => $txReference,
              'method' => 'bank_transfer'
          ]);
      }
  }
  ```

---

### 2.12 Subscription
Manages SaaS tier plans and the single active subscription bound to each Organization: activation, suspension, reactivation, expiry, cancellation, plan upgrades/downgrades, and per-period usage tracking against plan feature limits. Reworked from a legacy ISP/RADIUS billing model (bandwidth plans, NAS routers, PPPoE credentials, session accounting) into a SaaS subscription engine — see `docs/subscription-rework.md` for the full rationale. The old ISP-era code is preserved for reference under `modules/Subscription/_archived_isp_legacy/` but is not wired into the module and should not be used.

* **Exposed Contracts**:
  * `WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionLimitGuard`
  * `WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRecorder`
  * `WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository`
  * `WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository`
  * `WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRepository`
* **Key Use Cases**:
  * `WorkEddy\Modules\Subscription\Application\UseCases\ActivateSubscription`
  * `WorkEddy\Modules\Subscription\Application\UseCases\SuspendSubscription`
  * `WorkEddy\Modules\Subscription\Application\UseCases\ReactivateSubscription`
  * `WorkEddy\Modules\Subscription\Application\UseCases\ExpireSubscription`
  * `WorkEddy\Modules\Subscription\Application\UseCases\CancelSubscription`
  * `WorkEddy\Modules\Subscription\Application\UseCases\ChangeSubscriptionPlan`
  * `WorkEddy\Modules\Subscription\Application\UseCases\RenewSubscription`
  * `WorkEddy\Modules\Subscription\Application\UseCases\CreateSubscriptionPlan`
  * `WorkEddy\Modules\Subscription\Application\UseCases\UpdateSubscriptionPlan`
  * `WorkEddy\Modules\Subscription\Application\UseCases\CheckSubscriptionLimits`: internal implementation behind `ISubscriptionLimitGuard`.
  * `WorkEddy\Modules\Subscription\Application\UseCases\RecordUsage`: internal implementation behind `ISubscriptionUsageRecorder`.
  * `WorkEddy\Modules\Subscription\Application\UseCases\RunSubscriptionRenewalSweep`: finds active, auto-renewing subscriptions whose `current_period_end` has passed and renews each (advance billing). Cron-triggered; see Console/Cron below.
  * `WorkEddy\Modules\Subscription\Application\UseCases\SuspendOverdueSubscriptions`: dunning sweep — suspends subscriptions whose linked invoice is unpaid past its due date, gated by `SubscriptionSettings::autoSuspendOnExpiry`.
* **Canonical Scope**:
  * One active subscription per Organization, enforced at activation time via `ISubscriptionRepository::findActiveByOrganizationId`.
  * Public references use UUIDs (`subscriptions.uuid`, `organization_uuid`); internal joins use `organization_id`.
  * A plan's feature/limit map (`subscription_plans.features`, e.g. `max_worksites`, `max_users`, `max_assessments_per_month`, `video_storage_gb`, `ai_scoring_credits_per_month`, `has_export_access`) is a dynamic JSON dictionary, not fixed columns, so new tiers or entitlements don't require a schema change.
  * `SubscriptionStatus` is `pending_activation`, `active`, `suspended`, `expired`, or `cancelled`.
  * `subscriptions.current_period_start`/`current_period_end` track the active billing period, advanced on each renewal; `start_date` is the lifetime activation date (never changes) and `expiry_date` mirrors `current_period_end` for backward-compatible display. Proration and renewal math use the period fields, never `start_date`.
* **Published Events** (via `EventPublisherInterface`): `subscription.activated`, `subscription.suspended`, `subscription.reactivated`, `subscription.expired`, `subscription.cancelled`, `subscription.renewed`, `subscription.plan_changed`, `subscription.limit_exceeded`.
* **Subscribed Events**:
  * `organization.created` (via `ProvisionDefaultSubscription`, off by default — see `SubscriptionSettings::autoProvisionOnSignup`/`defaultPlanCode`).
  * `organization.status_changed` (via `SuspendSubscriptionOnOrganizationSuspended`, see §2.3).
  * `invoice.paid` (via `ReactivateSubscriptionOnInvoicePaid`, reactivates a suspended subscription once its linked invoice is paid).
* **Billing Integration**:
  * `GenerateInvoiceOnActivation` (on `subscription.activated`) and `GenerateInvoiceOnRenewal` (on `subscription.renewed`, advance billing — invoices the *upcoming* period) both call `Billing\GenerateInvoice::execute()` with `organizationId` and `subscriptionUuid` set.
  * `GenerateProrationInvoiceOnPlanChange` (on `subscription.plan_changed`) invoices **upgrades only** — standard unused-time proration using `current_period_start`/`current_period_end`. **v1 scope decision**: downgrades are computed and logged for audit but never invoiced negatively; there is no credit ledger. A credit ledger and/or deferring a downgrade to take effect at the next billing period are explicit **Phase 2** scope, not built. See `docs/subscription-rework.md` §6.2.
* **Renewal → Dunning Flow** (advance billing, "renew optimistically, suspend later if unpaid"):
  1. `subscription:renewal:sweep` finds subscriptions with `auto_renew=true`, `status=active`, and `current_period_end` due → calls `RenewSubscription` (advances the period, publishes `subscription.renewed`).
  2. `GenerateInvoiceOnRenewal` generates a full-price invoice for the new period, due within `SubscriptionSettings::gracePeriodDays()`.
  3. The subscription stays active through the grace period regardless of payment status.
  4. `subscription:dunning:sweep` finds invoices past their due date (via `IInvoiceRepository::listOverdueSubscriptionInvoices()`) and suspends the linked subscription, unless `SubscriptionSettings::autoSuspendOnExpiry()` is off.
  5. If the customer pays afterward, `invoice.paid` → `ReactivateSubscriptionOnInvoicePaid` brings it back to `active`.
* **Console/Cron**:
  * `php bin/console subscription:renewal:sweep [--limit=100]` / `php cronjobs/subscription-renewal-sweep.php`
  * `php bin/console subscription:dunning:sweep [--limit=100]` / `php cronjobs/subscription-dunning-sweep.php`
* **Usage Example**:
  ```php
  use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionLimitGuard;

  class CreateWorksiteHandler {
      public function __construct(private ISubscriptionLimitGuard $limits) {}

      public function beforeCreate(int $organizationId): void {
          if ($this->limits->wouldExceed($organizationId, 'max_worksites')) {
              throw new \WorkEddy\Shared\Exceptions\ValidationException(['worksite' => 'Plan worksite limit reached. Upgrade to add more.']);
          }
      }
  }
  ```
* **Integration Rule**:
  * Other modules should depend on `ISubscriptionLimitGuard` and `ISubscriptionUsageRecorder`, not on Subscription application use cases directly.
  * Metric normalization stays inside Subscription. Callers may ask for canonical plan metrics such as `max_worksites`, `max_users`, `max_assessments_per_month`, `video_storage_gb`, or usage metrics such as `video_storage_used_mb`.
* **Canonical Metrics**:
  * `max_worksites`: checked and recorded by `Organization` when a worksite is created.
  * `max_users`: checked and recorded by `Organization` when a member is invited or provisioned into an org.
  * `max_assessments_per_month`: checked and recorded by `Assessment` when a manual assessment is created or an equivalent assessed workflow is completed.
  * `video_storage_gb`: checked via `ISubscriptionLimitGuard`; normalized internally to the usage counter `video_storage_used_mb`.
  * `video_storage_used_mb`: recorded by `Assessment`/video upload workflows after Storage accepts the uploaded file.
  * `ai_scoring_credits_per_month`: checked via `ISubscriptionLimitGuard`; normalized internally to the usage counter `ai_scoring_credits_used`.
  * `ai_scoring_credits_used`: reserved for AI/pose-processing completion workflows; callers should record usage through `ISubscriptionUsageRecorder`, not write counters directly.
* **Current Ownership Map**:
  * `organization.worksite.created` -> `Organization` checks `max_worksites`, then records `max_worksites`.
  * `organization.member.invited` -> `Organization` checks `max_users`, then records `max_users`.
  * `assessment.created` -> `Assessment` checks `max_assessments_per_month`, then records `max_assessments_per_month`.
  * `assessment.video.uploaded` / upload-and-process flow -> `Assessment` checks `video_storage_gb`, then records `video_storage_used_mb`.

---

### 2.13 Website
Renders public landing page frameworks and handles public domain queries.

* **Exposed Contracts**: None. This module operates strictly as a Presentation delivery layer, reading settings configurations and consuming `ISubscriptionPlanRepository::listActive()` to render plan pricing pages.

---

### 2.14 CorrectiveAction
Owns the Phase 4 corrective action engine: recommendation generation from reviewed ergonomic assessments, review/accept/reject workflow, assignment, evidence upload, status tracking, verification, follow-up scheduling, corrective action library, and recommendation rules.

* **Exposed Contracts**:
  * `WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository`
* **Key Use Cases**:
  * `WorkEddy\Modules\CorrectiveAction\Application\GenerateRecommendationsUseCase`
  * `WorkEddy\Modules\CorrectiveAction\Application\ReviewRecommendationUseCase`
  * `WorkEddy\Modules\CorrectiveAction\Application\AssignCorrectiveActionUseCase`
  * `WorkEddy\Modules\CorrectiveAction\Application\UpdateCorrectiveActionStatusUseCase`
  * `WorkEddy\Modules\CorrectiveAction\Application\UploadCorrectiveActionEvidenceUseCase`
  * `WorkEddy\Modules\CorrectiveAction\Application\VerifyCorrectiveActionUseCase`
  * `WorkEddy\Modules\CorrectiveAction\Application\ScheduleFollowUpAssessmentUseCase`
  * `WorkEddy\Modules\CorrectiveAction\Application\ListCorrectiveActionLibraryUseCase`
  * `WorkEddy\Modules\CorrectiveAction\Application\UpsertCorrectiveActionLibraryItemUseCase`
  * `WorkEddy\Modules\CorrectiveAction\Application\ListRecommendationRulesUseCase`
  * `WorkEddy\Modules\CorrectiveAction\Application\UpsertRecommendationRuleUseCase`
  * `WorkEddy\Modules\CorrectiveAction\Application\SeedCorrectiveActionDefaultsUseCase`
  * `WorkEddy\Modules\CorrectiveAction\Application\RunCorrectiveActionMaintenanceUseCase`
* **Canonical Scope**:
  * Public references use UUIDs for assessments, recommendations, corrective actions, library items, rules, and Storage files.
  * Internal persistence and joins use numeric IDs where available.
  * Corrective actions are generated only from reviewed or locked assessments.
  * Recommendation rules and library items are data-driven, managed by permissions, and not hardcoded in controllers.
  * Evidence files are stored through the Storage module; CorrectiveAction stores Storage UUID references only.
* **Events**:
  * `corrective_action.assigned`
  * `corrective_action.status_updated`
  * `corrective_action.verified`
  * `corrective_action.follow_up_scheduled`
  * `corrective_action.overdue`
  * `corrective_action.follow_up_due`
* **Console/Cron**:
  * `php bin/console corrective-action:maintenance` idempotently seeds default library/rules, marks overdue actions, and emits due follow-up events.
  * `php cronjobs/corrective-action-maintenance.php` is the deployment wrapper.
* **Settings**:
  * `WorkEddy\Modules\CorrectiveAction\Settings\CorrectiveActionSettingsProvider`
  * `WorkEddy\Modules\CorrectiveAction\Settings\CorrectiveActionSettings`
* **Permissions**:
  * `corrective_action.view`
  * `corrective_action.generate_recommendations`
  * `corrective_action.review_recommendations`
  * `corrective_action.assign`
  * `corrective_action.update_status`
  * `corrective_action.upload_evidence`
  * `corrective_action.verify`
  * `corrective_action.manage_library`

---

### 2.15 WorkerVoice
Owns the Phase 6 worker voice workflow: discomfort reporting, anonymous mode, body-region mapping, worker suggestions, and trend aggregation by task, department, and region.

* **Exposed Contracts**:
  * `WorkEddy\Modules\WorkerVoice\Domain\Contracts\IWorkerVoiceRepository`
* **Key Use Cases**:
  * `WorkEddy\Modules\WorkerVoice\Application\SubmitWorkerFeedbackUseCase`
  * `WorkEddy\Modules\WorkerVoice\Application\GetWorkerFeedbackUseCase`
  * `WorkEddy\Modules\WorkerVoice\Application\ListWorkerFeedbackUseCase`
  * `WorkEddy\Modules\WorkerVoice\Application\GetWorkerFeedbackTrendsUseCase`
* **Canonical Scope**:
  * Public references use UUIDs for feedback, organization, task, assessment, worksite, department, and job role.
  * Internal persistence and joins use numeric IDs where available.
  * Anonymous feedback redacts worker identity from default view/list/trend payloads.
  * Body regions are catalog-driven and fixed through module settings, not controller constants.
  * Feedback may link to task only, assessment only, or both when validated to same org/task chain.
* **Permissions**:
  * `worker_voice.submit`
  * `worker_voice.view`
  * `worker_voice.view_sensitive`
  * `worker_voice.aggregate.view`
  * `worker_voice.export`
* **Settings**:
* `WorkEddy\Modules\WorkerVoice\Settings\WorkerVoiceSettingsProvider`
* `WorkEddy\Modules\WorkerVoice\Settings\WorkerVoiceSettings`
* **Current Implementation Gaps**:
  * Organization-level anonymity policy is only partially surfaced. Anonymous submission exists, but org-admin policy control and enforcement are not yet fully wired end to end.
  * Module-local export workflow is not complete. The permission exists (`worker_voice.export`), but a dedicated Worker Voice export flow is still not implemented as a finished module surface.
  * Some dashboard surface wiring remains incomplete. Worker Voice trends exist, but not all documented dashboard destinations are fully connected to the module's aggregates yet.

---

### 2.16 Export
Owns Phase 9 de-identified research export workflows: export preview, de-identification, CSV/XLSX generation, signed download issuance, signed stream validation, and export audit trail.

* **Exposed Contracts**:
  * `WorkEddy\Modules\Export\Domain\Contracts\IResearchExportRepository`
* **Key Use Cases**:
  * `WorkEddy\Modules\Export\Application\UseCases\PreviewResearchExportUseCase`
  * `WorkEddy\Modules\Export\Application\UseCases\GenerateResearchExportUseCase`
  * `WorkEddy\Modules\Export\Application\IssueSignedResearchExportAccessUseCase`
  * `WorkEddy\Modules\Export\Application\ReadSignedResearchExportAccessUseCase`
* **Canonical Scope**:
  * Public references use UUIDs for exports, organizations, tasks, assessments, storage files, and signed-access targets.
  * Internal persistence and joins use numeric IDs where needed.
  * Export payloads expose study-safe generated codes such as `ORG001`, `SITE001`, `TASK001`, `WORKER001`; direct identifiers and free-text notes are excluded.
  * Generated files are always registered through `Storage`; Export stores only Storage UUID references and metadata.
  * Signed download links are short-lived PHP API tokens, and every issue/read event is audited.
* **Permissions**:
  * `export.research.view`
  * `export.research.preview`
  * `export.research.generate`
  * `export.research.download`
* **Settings**:
  * `WorkEddy\Modules\Export\Settings\ExportSettingsProvider`
  * `WorkEddy\Modules\Export\Settings\ExportSettings`

---

### 2.17 Finance
Owns internal finance operations that sit alongside Billing and Payment: finance dashboard inputs, receivable/payable views, transaction rollups, and administrative finance summaries used inside the platform.

* **Exposed Contracts**:
  * No cross-module domain contract is currently documented as a stable public integration point.
* **Canonical Scope**:
  * Finance consumes internal billing and payment data through module-owned services and platform wiring rather than allowing direct cross-module table access.
  * Public APIs and admin surfaces should expose derived finance summaries, not raw persistence internals from Billing or Payment.
* **Integration Note**:
  * Treat Finance as a presentation and orchestration module over commercial data already owned by Billing, Payment, and Subscription unless a future explicit contract is added.

---

### 2.18 Reporting
Owns report snapshot generation, artifact registration, PDF/CSV output, and the report-facing read models used for assessment, comparison, corrective action, audit trail, pilot summary, dashboard, finance, and operations reports.

* **Exposed Contracts**:
  * No general-purpose write contract is exposed to other modules; Reporting is primarily an internal consumer/orchestrator.
* **Key Use Cases**:
  * `WorkEddy\Modules\Reporting\Application\UseCases\GeneratePdf`
  * `WorkEddy\Modules\Reporting\Application\UseCases\GenerateCsv`
* **Canonical Scope**:
  * Reporting owns rendered artifacts and report snapshots, not the source business records.
  * Report generation must read final reviewer-confirmed assessment data from owner modules and preserve provenance for regenerated artifacts.
  * Reporting reads published methodology/privacy/limitations notes from `Content` when available and falls back to reporting settings only when no published content page exists.
* **Settings**:
  * `WorkEddy\Modules\Reporting\Settings\ReportingSettingsProvider`
  * `WorkEddy\Modules\Reporting\Settings\ReportingSettings`

---

### 2.19 Content
Owns managed editorial content inside the authenticated platform: structured pages, draft/publish lifecycle, revision history, embedded references, and content media metadata.

* **Exposed Contracts**:
  * `WorkEddy\Modules\Content\Domain\Contracts\ContentPageReader`
  * `WorkEddy\Modules\Content\Domain\Contracts\ContentPreviewReader`
* **Key Services**:
  * `WorkEddy\Modules\Content\Application\Services\ContentWorkflowService`
  * `WorkEddy\Modules\Content\Application\Services\ContentQueryService`
* **Canonical Scope**:
  * `Content` owns the Methodology and Limitations page as managed platform content.
  * Structured page content is versioned through `content_pages` and `content_page_revisions`; do not add feature-specific methodology tables alongside this canonical model.
  * References are embedded in the revision snapshot and governed by content permissions, not by a separate reporting-owned data model.
  * Content media metadata is owned here, while raw files remain owned by `Storage`.
* **Integration Note**:
  * `Reporting` is the primary downstream consumer for Methodology today. It should read the published page through `ContentPageReader` and never own the canonical methodology text.
