# Subscription Module Rework Plan
## WorkEddy v2 — From ISP/RADIUS Billing to SaaS Tier Subscription

**Status:** Implemented (see "Implementation Status" addendum below) — original plan retained as historical rationale.  
**Author:** Code Review & Architecture Team  
**Scope:** `v2/modules/Subscription/` + schema + cross-module integration  

---

## 0. Implementation Status Addendum

Everything in sections 1–14 below describes the *original* plan as drafted. It has since been implemented and, in several places, extended further than originally scoped. This addendum is the current source of truth; treat the rest of the document as design rationale, not a live checklist.

**Delivered as planned:**
- All ISP entities/use cases/infra deleted (archived to `modules/Subscription/_archived_isp_legacy/`, not physically removed, so history is recoverable)
- SaaS `SubscriptionPlan`/`Subscription`/`SubscriptionUsage` domain model, all three repository contracts, full DBAL implementations
- Full lifecycle use cases: `ActivateSubscription`, `SuspendSubscription`, `ReactivateSubscription`, `ExpireSubscription`, `CancelSubscription`, `ChangeSubscriptionPlan`, `RenewSubscription`, `CreateSubscriptionPlan`, `UpdateSubscriptionPlan`, `CheckSubscriptionLimits`, `RecordUsage`
- `GenerateInvoiceOnActivation` (§5.2/§6.2), Website reads `ISubscriptionPlanRepository::listActive()` (§6.4)
- `modules-map.md` Subscription section kept current

**Extended beyond the original plan** (via follow-up work after the initial rework):
- **Billing had no Customer module to bill against at all** (not just "needs a subscription_uuid column" as §6.2 assumed) — `ICustomerRepository` didn't exist anywhere in the codebase, so `GeneratePdf` fatally errored on every invoice/quotation. Billing was reworked to bill exclusively against Organization: `customer_id` renamed to `organization_id` throughout (`Invoice`, `Quotation`, both DBAL repos, `GenerateInvoice`, `GenerateQuotation`, `BillingApiController`), `billing_invoices.subscription_uuid` added, `GeneratePdf` resolves the billed party via `IOrganizationRepository`. See `modules-map.md` §2.10 and migrations `Version20260707160000_AddSubscriptionLinkageToBillingInvoices` / `Version20260708140000_RemoveCustomerConceptFromBilling`.
- **`subscriptions_organization_fk` changed from `ON DELETE CASCADE` to `ON DELETE RESTRICT`** (`Version20260708150000_RestrictSubscriptionOrganizationCascade`) — closes a silent-data-loss hole where hard-deleting an Organization would cascade-delete its subscription with no audit trail.
- **`subscriptions.current_period_start`/`current_period_end`** added (same migration) — the billing period is now tracked independently of the lifetime `start_date`, needed for correct renewal and proration math.
- **Renewal actually runs now.** `RunSubscriptionRenewalSweep` (`bin/console subscription:renewal:sweep`, `cronjobs/subscription-renewal-sweep.php`) finds due subscriptions and renews them (advance billing). `GenerateInvoiceOnRenewal` listens for `subscription.renewed` and invoices the upcoming period, due within `SubscriptionSettings::gracePeriodDays()`.
- **Dunning.** `SuspendOverdueSubscriptions` (`bin/console subscription:dunning:sweep`, `cronjobs/subscription-dunning-sweep.php`) suspends subscriptions whose linked invoice (via the new `IInvoiceRepository::listOverdueSubscriptionInvoices()`) is unpaid past its due date, gated by `SubscriptionSettings::autoSuspendOnExpiry`. Paired with the pre-existing `ReactivateSubscriptionOnInvoicePaid` (on `invoice.paid`), this closes the full "renew optimistically → suspend on non-payment → reactivate on payment" loop.
- **Upgrade-only proration.** `GenerateProrationInvoiceOnPlanChange` (on `subscription.plan_changed`) computes standard unused-time proration via `Domain\Services\SubscriptionProrationCalculator` using `current_period_start`/`current_period_end`, and invoices **upgrades only**. **Explicit v1/Phase 2 split, by design decision:** downgrades are computed and logged for audit but never invoiced negatively — there is no credit ledger, and deferring a downgrade to the next billing period is not implemented. Both are explicit Phase 2 scope.
- **Organization lifecycle sync.** Organization gained `UpdateOrganizationStatusUseCase` (validates `active`/`suspended`/`deleted` transitions, persists, audits, publishes `organization.status_changed`; `deleted` is soft-delete only via the new `IOrganizationRepository::softDelete()`). Subscription's `SuspendSubscriptionOnOrganizationSuspended` listener suspends the subscription when the org is suspended, only auto-reactivates it if it was suspended *for that specific reason* (won't clobber a subscription suspended for non-payment), and cancels it (via `CancelSubscription`, never a raw delete) when the org is soft-deleted.

**Still not built (confirmed Phase 2, not silently dropped):**
- Downgrade credit ledger / wallet balance applied to future invoices
- Scheduling a downgrade to take effect at the next billing period instead of immediately
- Payment records now use `organization_id` consistently across schema, domain, repositories, and APIs.
- The Billing UI now has a concrete `public/assets/js/billing.js` implementation for invoice and quotation workflows.

---

## 1. Executive Summary

The current `Subscription` module models an ISP/RADIUS internet-service business (bandwidth plans, NAS routers, PPPoE credentials, session accounting). WorkEddy is an ergonomics/EHS SaaS platform. This plan defines a complete scope rework to transform the module into a **SaaS tier subscription engine** that:

- Binds subscriptions to `Organization` entities (not loose `customer_reference` strings)
- Defines feature-tier limits (worksites, users, assessments, video storage, AI credits)
- Integrates natively with the existing `Billing` and `Payment` modules
- Preserves the excellent engineering patterns already in place (state machine, audit, notifications, event-driven sync)

---

## 2. Current State (What Exists Today)

### 2.1 Domain Entities

| Entity | Purpose | WorkEddy Relevance |
|---|---|---|
| `Subscription` | Customer service contract with bandwidth profile | **Retool** — keep state machine, replace ISP fields with SaaS limits |
| `SubscriptionPlan` | ISP plan: download/upload kbps, quota bytes, session TTL | **Replace** — tier definition with feature gates |
| `SubscriptionAccessIdentity` | PPPoE/hotspot credential per edge destination | **Delete** — no network-access concept |
| `SubscriptionAccessNas` | Network Access Server (router IP, secret) | **Delete** — no edge hardware concept |

### 2.2 State Machine (Keep)

```
pending_activation → active → suspended
                          ↓
                        expired
```

Actions: `Activate`, `Suspend`, `Reactivate`, `Expire`

### 2.3 Cross-Cutting Patterns (Keep)

- Every state mutation is audited via `IAuditService` with `beforeState`/`afterState`
- Every state mutation triggers a customer notification via `NotificationServiceInterface`
- Async sync via `CloudEdgeAccessSyncPublisher` → **retain as generic `SubscriptionEventPublisher`**
- Settings-driven defaults via `SubscriptionSettings`
- Permission-gated actions via `SubscriptionPermissions`

### 2.4 Database Tables (Current)

- `subscription_plans` — ISP plan catalog
- `subscriptions` — customer contract
- `subscription_access_identities` — RADIUS credentials
- `subscription_access_nas` — router config
- `subscription_access_accounting_events` — session usage data
- `subscription_edge_override_events` — admin overrides

### 2.5 Integrations (Current)

- Listens to `installation.activation_ready` (from a non-existent `Customer`/`Installation` module)
- Exposes `ISubscriptionRepository` consumed by `Website` module for pricing pages
- Publishes sync events to edge infrastructure via Transport bus

---

## 3. Target State (What WorkEddy Needs)

### 3.1 Conceptual Model

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│  Organization   │◄────│  Subscription    │◄────│ SubscriptionPlan│
│  (1 per tenant) │ 1:1 │  (1 active max)  │ N:1 │  (tier catalog) │
└─────────────────┘     └──────────────────┘     └─────────────────┘
         ▲                                              │
         │                                              │
         │         ┌──────────────────┐                │
         └─────────┤  BillingInvoice  │◄───────────────┘
                   │  (charges plan)  │
                   └──────────────────┘
```

### 3.2 SubscriptionPlan — SaaS Tier Definition

```php
final class SubscriptionPlan
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,               // 'starter', 'professional', 'enterprise'
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $billingCycle,       // 'monthly' | 'annual'
        public readonly float $price,
        public readonly string $currency,
        public readonly array $features,            // JSON-mapped: ['max_worksites' => 5, 'has_export_access' => true, ...]
        public readonly bool $isActive,
        public readonly ?int $displayOrder,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Retrieve a feature value safely from the dynamic features dictionary.
     */
    public function getFeature(string $key, mixed $default = null): mixed
    {
        return $this->features[$key] ?? $default;
    }
}
```

### 3.3 Subscription — Organization Contract

```php
final class Subscription
{
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $organizationUuid,   // FK → Organization (strict, not loose ref)
        public readonly string $planCode,
        public readonly string $planName,           // denormalized at creation
        public readonly SubscriptionStatus $status,
        public readonly string $billingCycle,       // 'monthly' | 'annual'
        public readonly \\DateTimeImmutable $startDate,
        public readonly ?\\DateTimeImmutable $expiryDate,
        public readonly ?\\DateTimeImmutable $activatedAt,
        public readonly ?\\DateTimeImmutable $suspendedAt,
        public readonly ?string $suspendedReason,
        public readonly ?string $cancelledAt,
        public readonly ?string $cancellationReason,
        public readonly bool $autoRenew,
        public readonly \\DateTimeImmutable $createdAt,
        public readonly \\DateTimeImmutable $updatedAt,
    ) {}
}
```

### 3.4 SubscriptionUsage — Optional but Recommended

Track current-period consumption against plan limits. This enables:
- Enforcing hard limits at the API layer
- Showing "X of Y used" in UI
- Triggering upgrade nudges

```php
final class SubscriptionUsage
{
    public function __construct(
        public readonly string $subscriptionUuid,
        public readonly string $periodStart,        // YYYY-MM-01
        public readonly string $periodEnd,
        public readonly array $usageData,           // JSON-mapped dynamic key-values: ['worksites' => 2, 'users' => 4, ...]
        public readonly \DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Get usage value safely.
     */
    public function getUsage(string $key, int $default = 0): int
    {
        return $this->usageData[$key] ?? $default;
    }
}
```

---

## 4. Database Schema Design (Canonical v2)

The new database structure defines direct, clean schemas for the SaaS subscription system without legacy network configuration or bandwidth constraints.

### 4.1 Table: `subscription_plans`

Stores details of the available subscription plans/tiers.

```sql
CREATE TABLE subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(64) NOT NULL,
    description TEXT NULL,
    billing_cycle VARCHAR(16) NOT NULL, -- 'monthly' or 'annual'
    price DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    features JSON NOT NULL, -- dynamic tier features & limits (max_worksites, max_users, has_export_access, etc.)
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    display_order INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 4.2 Table: `subscriptions`

Links organizations to their active subscription contracts.

```sql
CREATE TABLE subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    organization_id INT NOT NULL,
    organization_uuid CHAR(36) NOT NULL,
    plan_code VARCHAR(32) NOT NULL,
    plan_name VARCHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL, -- 'pending_activation', 'active', 'suspended', 'expired'
    billing_cycle VARCHAR(16) NOT NULL, -- 'monthly' or 'annual'
    start_date DATETIME NOT NULL,
    expiry_date DATETIME NULL,
    activated_at DATETIME NULL,
    suspended_at DATETIME NULL,
    suspended_reason TEXT NULL,
    cancelled_at DATETIME NULL,
    cancellation_reason TEXT NULL,
    auto_renew BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_subscriptions_organization FOREIGN KEY (organization_id) REFERENCES organizations(id),
    CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_code) REFERENCES subscription_plans(code)
);
```

### 4.3 Table: `subscription_usage`

Tracks period-based resource consumption against subscription limits.

```sql
CREATE TABLE subscription_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscription_uuid CHAR(36) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    usage_data JSON NOT NULL, -- dynamic consumed metrics (worksites, users, assessments, etc.)
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_subscription_usage_subscription FOREIGN KEY (subscription_uuid) REFERENCES subscriptions(uuid),
    UNIQUE INDEX idx_subscription_usage_period (subscription_uuid, period_start),
    INDEX idx_subscription_usage_end (period_end)
);
```

---

## 5. Code Changes by Layer

### 5.1 Domain Layer

#### Files to DELETE:
- `Domain/Entities/SubscriptionAccessIdentity.php`
- `Domain/Entities/SubscriptionAccessNas.php`

#### Files to REWRITE:
- `Domain/Entities/SubscriptionPlan.php` — replace ISP fields with SaaS tier fields
- `Domain/Entities/Subscription.php` — replace `customerReference`/`installationOrderUuid`/`bandwidthProfile` with `organizationId`/`organizationUuid`/`billingCycle`/`autoRenew`
- `Domain/Enums/SubscriptionStatus.php` — keep as-is (excellent enum)

#### Files to CREATE:
- `Domain/Entities/SubscriptionUsage.php` — period-based consumption tracker
- `Domain/ValueObjects/SubscriptionLimits.php` — immutable value object holding all plan limits for easy comparison
- `Domain/Events/SubscriptionTierChanged.php` — domain event when upgrading/downgrading
- `Domain/Events/SubscriptionLimitExceeded.php` — domain event when usage hits a cap

#### Files to MODIFY:
- `Domain/Contracts/ISubscriptionRepository.php` — update method signatures:
  - Remove: `upsertPlan`, `findPlanByCode`, `listPlans` → move to `ISubscriptionPlanRepository`
  - Remove: `findByInstallationOrderUuid`
  - Add: `findByOrganizationId(int $organizationId): ?Subscription`
  - Add: `findActiveByOrganizationId(int $organizationId): ?Subscription`
  - Add: `cancelSubscription(string $uuid, \\DateTimeImmutable $cancelledAt, ?string $reason): Subscription`
  - Add: `changePlan(string $uuid, string $newPlanCode, \\DateTimeImmutable $effectiveDate): Subscription`

- Create `Domain/Contracts/ISubscriptionPlanRepository.php`:
  - `findByCode(string $code): ?SubscriptionPlan`
  - `listActive(): array`
  - `upsert(SubscriptionPlan $plan): SubscriptionPlan`

- Create `Domain/Contracts/ISubscriptionUsageRepository.php`:
  - `recordUsage(string $subscriptionUuid, string $metric, int $increment): void`
  - `getCurrentPeriodUsage(string $subscriptionUuid): SubscriptionUsage`
  - `resetPeriod(string $subscriptionUuid, \\DateTimeImmutable $periodStart, \\DateTimeImmutable $periodEnd): void`

### 5.2 Application Layer — Use Cases

#### Files to DELETE:
- `Application/UseCases/SetupInternetPlan.php` — ISP-specific plan creation
- `Application/UseCases/RevokeSubscriptionAccessIdentity.php` — RADIUS credential revocation
- `Application/Sync/AccessSyncProfileProvisioner.php` — edge device provisioning
- `Application/Sync/CloudEdgeAccessSyncPublisher.php` — edge sync (or **retool** as generic event publisher)
- `Application/Sync/NetworkCredentialEnvelopeFactory.php` — credential encryption
- `Application/Transport/EdgeAccessEventHandler.php` — RADIUS event handler
- `Application/Listeners/ActivateSubscriptionFromInstallationListener.php` — installation workflow listener

#### Files to REWRITE:
- `Application/UseCases/ActivateSubscription.php`
  - Input: `organization_id`, `plan_code`, `billing_cycle`, `start_date`, `auto_renew`
  - Validate: Organization exists (via `IOrganizationRepository`)
  - Validate: No active subscription already exists for this org
  - Create: `Subscription` in `PENDING_ACTIVATION` → `ACTIVE`
  - Emit: `subscription.activated` event
  - Remove: `SetupInternetPlan` dependency, bandwidth profile logic
  - Remove: `CloudEdgeAccessSyncPublisher` queue (replace with generic event)

- `Application/UseCases/SuspendSubscription.php`
  - Keep as-is structurally, update notification context from \"customer\" to \"organization\"
  - Emit: `subscription.suspended` event

- `Application/UseCases/ReactivateSubscription.php`
  - Keep as-is structurally
  - Emit: `subscription.reactivated` event

- `Application/UseCases/ExpireSubscription.php`
  - Keep as-is structurally
  - Emit: `subscription.expired` event

#### Files to CREATE:
- `Application/UseCases/CreateSubscriptionPlan.php` — admin-tier plan management
- `Application/UseCases/UpdateSubscriptionPlan.php` — modify tier limits/pricing
- `Application/UseCases/ChangeSubscriptionPlan.php` — upgrade/downgrade with proration logic (Phase 2)
- `Application/UseCases/CancelSubscription.php` — soft-cancel with reason
- `Application/UseCases/RenewSubscription.php` — handle auto-renewal (cron-triggered)
- `Application/UseCases/CheckSubscriptionLimits.php` — validation service for other modules
- `Application/UseCases/RecordUsage.php` — increment usage counters
- `Application/Listeners/GenerateInvoiceOnActivation.php` — create first invoice via Billing module
- `Application/Listeners/InvoiceOnRenewal.php` — create recurring invoice
- `Application/Listeners/SendUpgradeNotification.php` — notify org admins of tier changes

### 5.3 Infrastructure Layer

#### Files to DELETE:
- `Infrastructure/DbalAccessSyncRepository.php` — edge sync DB operations

#### Files to REWRITE:
- `Infrastructure/DbalSubscriptionRepository.php`
  - Remove: `upsertPlan`, `findPlanByCode`, `listPlans`
  - Remove: `findByInstallationOrderUuid`
  - Remove: bandwidth_profile mapping
  - Add: `organization_id` and `organization_uuid` mapping
  - Add: `findByOrganizationId`, `findActiveByOrganizationId`
  - Add: `cancelSubscription`, `changePlan`
  - Update: `listSubscriptions` filters — replace `customer_reference` with `organization_id`

#### Files to CREATE:
- `Infrastructure/DbalSubscriptionPlanRepository.php` — plan CRUD
- `Infrastructure/DbalSubscriptionUsageRepository.php` — usage tracking
- `Infrastructure/SubscriptionEventPublisher.php` — replaces `CloudEdgeAccessSyncPublisher` with generic platform event publishing

### 5.4 Presentation Layer

#### Files to REWRITE:
- `Presentation/SubscriptionApiController.php`
  - Endpoints:
    - `GET /api/subscriptions` — list (admin)
    - `GET /api/subscriptions/:uuid` — detail
    - `POST /api/subscriptions` — create (activate)
    - `POST /api/subscriptions/:uuid/suspend` — suspend
    - `POST /api/subscriptions/:uuid/reactivate` — reactivate
    - `POST /api/subscriptions/:uuid/cancel` — cancel (NEW)
    - `POST /api/subscriptions/:uuid/change-plan` — change plan (NEW)
    - `GET /api/subscriptions/:uuid/usage` — current usage (NEW)
  - Remove: any bandwidth/quota/NAS related endpoints

- `Presentation/SubscriptionPageController.php`
  - Render admin subscription management pages
  - Render organization self-service billing page (view current plan, usage, upgrade options)

- `Presentation/SubscriptionPageData.php`
  - Remove: internet plan / edge device data
  - Add: plan tiers, usage statistics, upgrade/downgrade options

#### Files to DELETE:
- `Presentation/Views/Index/settings.php` — if only contains ISP settings
- Update: `Presentation/Views/Index/index.php` and `detail.php` — replace ISP fields with SaaS tier display

### 5.5 Settings & Authorization

#### `Settings/SubscriptionSettings.php` — UPDATE
```php
final class SubscriptionSettings extends ModuleSettings
{
    protected function moduleName(): string { return 'subscription'; }

    public function defaultCurrency(): string { return $this->getString('default_currency', 'USD'); }
    public function defaultBillingCycle(): string { return $this->getString('default_billing_cycle', 'monthly'); }
    public function trialDays(): int { return $this->getInt('trial_days', 14); }
    public function gracePeriodDays(): int { return $this->getInt('grace_period_days', 3); }
    public function autoSuspendOnExpiry(): bool { return $this->getBool('auto_suspend_on_expiry', true); }
    public function allowSelfServiceUpgrade(): bool { return $this->getBool('allow_self_service_upgrade', true); }
}
```

#### `Authorization/SubscriptionPermissions.php` — UPDATE
```php
final class SubscriptionPermissions
{
    public const VIEW = 'subscription.view';
    public const MANAGE = 'subscription.manage';       // Admin: create/suspend/expire
    public const CHANGE_PLAN = 'subscription.change_plan'; // Org admin: upgrade/downgrade
    public const CANCEL = 'subscription.cancel';         // Org admin: cancel
    public const VIEW_USAGE = 'subscription.view_usage'; // Org user: see own usage
    public const MANAGE_PLANS = 'subscription.manage_plans'; // Super-admin: define tiers
}
```

### 5.6 Service Provider

#### `ServiceProvider.php` — REWRITE DI definitions

Remove registrations for:
- `DbalAccessSyncRepository`
- `CloudEdgeAccessSyncPublisher`
- `NetworkCredentialEnvelopeFactory`
- `AccessSyncProfileProvisioner`
- `EdgeAccessEventHandler`
- `SetupInternetPlan`
- `RevokeSubscriptionAccessIdentity`
- `ActivateSubscriptionFromInstallationListener`

Add registrations for:
- `DbalSubscriptionPlanRepository`
- `DbalSubscriptionUsageRepository`
- `SubscriptionEventPublisher`
- `CreateSubscriptionPlan`
- `UpdateSubscriptionPlan`
- `ChangeSubscriptionPlan`
- `CancelSubscription`
- `RenewSubscription`
- `CheckSubscriptionLimits`
- `RecordUsage`
- `GenerateInvoiceOnActivation`
- `InvoiceOnRenewal`

Update event listeners:
- Remove: `installation.activation_ready`
- Add: `subscription.activated` → `GenerateInvoiceOnActivation`
- Add: `subscription.renewed` → `InvoiceOnRenewal`
- Add: `subscription.plan_changed` → `SendUpgradeNotification`

Remove: `TransportMessageHandlerProviderInterface` implementation (no more edge events).

---

## 6. Cross-Module Integration Changes

### 6.1 Organization Module (Consumer)

The `Organization` module already exposes:
- `Organization` entity with `uuid`, `name`, `status`
- `IOrganizationRepository` (inferred from existing `OrganizationRepository.php`)

**Integration point:** `ActivateSubscription` use case must inject `IOrganizationRepository` to validate `organization_id` exists and is active.

**Recommended contract addition to Organization module:**
- Expose `IOrganizationRepository::findById(int $id): ?Organization`
- Expose `IOrganizationRepository::findByUuid(string $uuid): ?Organization`

### 6.2 Billing Module (Consumer + Provider)

**Current state:** `Billing` now uses `organization_id` as the billed party key, and `billing_invoices.subscription_uuid` is the active linkage used by Subscription listeners and dunning workflows.

**Changes needed:**
1. Keep Billing and Subscription linked through `billing_invoices.subscription_uuid`.
2. Keep invoice generation listeners passing `organization_id` and `subscription_uuid` directly, without any customer aliasing.

### 6.3 Payment Module (Consumer)

**Current state:** `PaymentRecord` links to `invoice_id`.

**No direct changes needed** in Payment module for this rework. The existing `PaymentCompletedListener` in Billing will continue to work. However, Subscription may want to listen to `invoice.paid` events to track \"paid through\" dates.

**Recommended:**
- Add listener in Subscription: `invoice.paid` → update subscription `expiry_date` or mark `payment_status = 'current'`

### 6.4 Website Module (Consumer)

**Current state:** Website module reads `ISubscriptionRepository` to render plan pricing pages.

**Changes:**
- Replace `listPlans()` call with `ISubscriptionPlanRepository::listActive()`
- Render tier cards with feature checklists instead of bandwidth profiles

### 6.5 Other Future Modules (Providers of Usage Events)

Future modules will need to report consumption:
- **Assessment module** → `RecordUsage` for `assessments_used`
- **Storage module** → `RecordUsage` for `video_storage_used_mb`
- **AI Scoring module** → `RecordUsage` for `ai_scoring_credits_used`
- **Organization module** → `RecordUsage` for `worksites_used` and `users_used`

**Pattern:** These modules should publish events (`assessment.completed`, `video.uploaded`, etc.) and Subscription module listens to increment counters. This keeps module boundaries clean.

---

## 7. Event Contract Updates

### 7.1 Events Published by Subscription Module

| Event | Payload | Consumers |
|---|---|---|
| `subscription.activated` | `{subscription_uuid, organization_id, plan_code, billing_cycle}` | Billing (generate invoice), Notification (welcome email) |
| `subscription.suspended` | `{subscription_uuid, organization_id, reason}` | Notification, IAM (disable non-admin logins?) |
| `subscription.reactivated` | `{subscription_uuid, organization_id, plan_code}` | Notification, Billing |
| `subscription.expired` | `{subscription_uuid, organization_id}` | Notification, IAM (read-only mode) |
| `subscription.cancelled` | `{subscription_uuid, organization_id, reason, expires_at}` | Notification, Billing (final invoice) |
| `subscription.plan_changed` | `{subscription_uuid, organization_id, old_plan_code, new_plan_code, effective_date}` | Billing (proration invoice), Notification |
| `subscription.renewed` | `{subscription_uuid, organization_id, plan_code, period_start, period_end}` | Billing (recurring invoice) |
| `subscription.limit_exceeded` | `{subscription_uuid, organization_id, metric, limit}` | Notification (upgrade nudge), IAM (block action) |

### 7.2 Events Consumed by Subscription Module

| Event | Publisher | Handler Action |
|---|---|---|
| `invoice.paid` | Billing | Extend `expiry_date`, set `payment_status = 'current'` |
| `invoice.overdue` | Billing | Suspend subscription after grace period |
| `assessment.completed` | Assessment | Increment `assessments_used` |
| `video.uploaded` | Storage | Increment `video_storage_used_mb` |
| `ai.scoring.completed` | AI Scoring | Increment `ai_scoring_credits_used` |
| `organization.member_added` | Organization | Increment `users_used` |
| `worksite.created` | Organization | Increment `worksites_used` |

---

## 8. Schema Builder Update

### `platform/Schema/Modules/Subscription/SubscriptionSchemaBuilder.php` — REWRITE

```php
final class SubscriptionSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string { return 'subscription'; }

    public function tables(): array
    {
        return [
            'subscription_plans',
            'subscriptions',
            'subscription_usage',
        ];
    }

    public function build(Schema $schema): void
    {
        $this->createPlans($schema);
        $this->createSubscriptions($schema);
        $this->createUsage($schema);
    }

    // ... implementation
}
```

Remove all `createAccessIdentities`, `createAccessNas`, `createAccountingEvents`, `createOverrideEvents` methods.

---

## 9. Migration File Plan

Create a single migration file:

```
v2/migrations/Version202607XX0000_ReworkSubscriptionToSaaSTiers.php
```

**Steps in `up()`:**
1. Drop ISP-specific tables entirely:
   - `subscription_access_identities`
   - `subscription_access_nas`
   - `subscription_access_accounting_events`
   - `subscription_edge_override_events`
   - `subscriptions`
   - `subscription_plans`
2. Create clean, canonical tables directly:
   - `subscription_plans` (with SaaS feature limits/flags)
   - `subscriptions` (with strict FK to `organizations.id`)
   - `subscription_usage` (with usage tracking)

**Steps in `down()`:**
- Dropping the new SaaS tables and recreating the legacy empty ISP tables.

---

## 10. Canonical Implementation Roadmap

### Task 1: Schema Migration & Configuration
- [ ] Create and run `Version202607XX0000_ReworkSubscriptionToSaaSTiers.php` migration.
- [ ] Update `SubscriptionSchemaBuilder` to define the clean, new v2 tables.
- [ ] Update `SubscriptionSettings` and `SubscriptionPermissions` configuration files.

### Task 2: Domain Layer & Repositories
- [ ] Remove legacy entities (`SubscriptionAccessIdentity`, `SubscriptionAccessNas`).
- [ ] Implement canonical `SubscriptionPlan`, `Subscription`, and `SubscriptionUsage` entities.
- [ ] Establish repository interfaces: `ISubscriptionRepository`, `ISubscriptionPlanRepository`, `ISubscriptionUsageRepository`.
- [ ] Implement DBAL-backed repositories in `Infrastructure/`.

### Task 3: Use Cases & Business Logic
- [ ] Delete legacy use cases (`SetupInternetPlan`, `RevokeSubscriptionAccessIdentity`, sync classes).
- [ ] Implement use cases: `ActivateSubscription`, `SuspendSubscription`, `ReactivateSubscription`, `ExpireSubscription`, `CancelSubscription`, `RenewSubscription`, `CheckSubscriptionLimits`, `RecordUsage`.
- [ ] Implement events (`SubscriptionTierChanged`, `SubscriptionLimitExceeded`) and handlers.

### Task 4: Presentation & API Endpoints
- [ ] Update `SubscriptionApiController` to expose clean REST endpoints.
- [ ] Update `SubscriptionPageController` and data providers.
- [ ] Rewrite views to display SaaS tier details, limits, and usage progress.

---

## 11. Risks & Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Lack of test suite for new SaaS features | High | Write integration tests for `ISubscriptionRepository` and unit tests for core use cases directly. |
| Billing module integration boundaries | Medium | Direct integration: trigger invoice generation for new subscriptions using billing service contract directly. |
| Organization module contracts | Medium | Ensure `IOrganizationRepository` is used to validate organization IDs before subscription activation. |

---

## 12. File Inventory

### Files to DELETE (17 files)

```
v2/modules/Subscription/Domain/Entities/SubscriptionAccessIdentity.php
v2/modules/Subscription/Domain/Entities/SubscriptionAccessNas.php
v2/modules/Subscription/Application/UseCases/SetupInternetPlan.php
v2/modules/Subscription/Application/UseCases/RevokeSubscriptionAccessIdentity.php
v2/modules/Subscription/Application/Sync/AccessSyncProfileProvisioner.php
v2/modules/Subscription/Application/Sync/CloudEdgeAccessSyncPublisher.php
v2/modules/Subscription/Application/Sync/NetworkCredentialEnvelopeFactory.php
v2/modules/Subscription/Application/Transport/EdgeAccessEventHandler.php
v2/modules/Subscription/Application/Listeners/ActivateSubscriptionFromInstallationListener.php
v2/modules/Subscription/Infrastructure/DbalAccessSyncRepository.php
```

### Files to REWRITE (14 files)

```
v2/modules/Subscription/Domain/Entities/Subscription.php
v2/modules/Subscription/Domain/Entities/SubscriptionPlan.php
v2/modules/Subscription/Domain/Contracts/ISubscriptionRepository.php
v2/modules/Subscription/Application/UseCases/ActivateSubscription.php
v2/modules/Subscription/Application/UseCases/SuspendSubscription.php
v2/modules/Subscription/Application/UseCases/ReactivateSubscription.php
v2/modules/Subscription/Application/UseCases/ExpireSubscription.php
v2/modules/Subscription/Infrastructure/DbalSubscriptionRepository.php
v2/modules/Subscription/Presentation/SubscriptionApiController.php
v2/modules/Subscription/Presentation/SubscriptionPageController.php
v2/modules/Subscription/Presentation/SubscriptionPageData.php
v2/modules/Subscription/Settings/SubscriptionSettings.php
v2/modules/Subscription/Settings/SubscriptionSettingsProvider.php
v2/modules/Subscription/Authorization/SubscriptionPermissions.php
v2/modules/Subscription/Authorization/SubscriptionPermissionDefinitionProvider.php
v2/modules/Subscription/ServiceProvider.php
v2/platform/Schema/Modules/Subscription/SubscriptionSchemaBuilder.php
v2/docs/modules-map.md  (Subscription section)
```

### Files to CREATE (15 files)

```
v2/modules/Subscription/Domain/Entities/SubscriptionUsage.php
v2/modules/Subscription/Domain/ValueObjects/SubscriptionLimits.php
v2/modules/Subscription/Domain/Events/SubscriptionTierChanged.php
v2/modules/Subscription/Domain/Events/SubscriptionLimitExceeded.php
v2/modules/Subscription/Domain/Contracts/ISubscriptionPlanRepository.php
v2/modules/Subscription/Domain/Contracts/ISubscriptionUsageRepository.php
v2/modules/Subscription/Application/UseCases/CreateSubscriptionPlan.php
v2/modules/Subscription/Application/UseCases/UpdateSubscriptionPlan.php
v2/modules/Subscription/Application/UseCases/ChangeSubscriptionPlan.php
v2/modules/Subscription/Application/UseCases/CancelSubscription.php
v2/modules/Subscription/Application/UseCases/RenewSubscription.php
v2/modules/Subscription/Application/UseCases/CheckSubscriptionLimits.php
v2/modules/Subscription/Application/UseCases/RecordUsage.php
v2/modules/Subscription/Application/Listeners/GenerateInvoiceOnActivation.php
v2/modules/Subscription/Infrastructure/DbalSubscriptionPlanRepository.php
v2/modules/Subscription/Infrastructure/DbalSubscriptionUsageRepository.php
v2/modules/Subscription/Infrastructure/SubscriptionEventPublisher.php
v2/migrations/Version202607XX0000_ReworkSubscriptionToSaaSTiers.php
```

---

## 13. Appendix: ISP → SaaS Field Mapping

| Current Field | New Field | Notes |
|---|---|---|
| `customer_reference` | `organization_id` + `organization_uuid` | Strict FK to Organization |
| `installation_order_uuid` | — | Deleted |
| `bandwidth_profile` | — | Deleted |
| `plan_code` | `plan_code` | Kept, values change ('starter', 'pro', 'enterprise') |
| `plan_name` | `plan_name` | Kept |
| `download_kbps` | `features->max_assessments_per_month` | Mapped inside `features` JSON |
| `upload_kbps` | `features->max_users` | Mapped inside `features` JSON |
| `quota_bytes` | `features->video_storage_gb` | Mapped inside `features` JSON |
| `session_ttl_seconds` | `features->ai_scoring_credits_per_month` | Mapped inside `features` JSON |
| `billing_cycle_days` | `billing_cycle` | Enum: 'monthly' / 'annual' |
| `monthly_price` | `price` | Same concept, cycle-agnostic naming |
| `currency` | `currency` | Kept |
| `is_active` | `is_active` | Kept |

---

## 14. Success Criteria

- [ ] All ISP entities, tables, and code references are removed
- [ ] `Subscription` is keyed by `organization_id` with FK integrity
- [ ] `SubscriptionPlan` defines SaaS feature tiers (worksites, users, assessments, storage, AI credits)
- [ ] State machine (activate/suspend/reactivate/expire/cancel) works with audit + notification on every transition
- [ ] Subscription activation triggers invoice generation via Billing module
- [ ] Usage tracking table exists and can record cross-module consumption
- [ ] Permission system supports admin plan management and org self-service
- [ ] Settings support trial periods, grace periods, and auto-suspend configuration
- [ ] All old tests pass (or are removed/updated) and new use cases have unit tests
- [ ] `modules-map.md` accurately describes the new Subscription module contracts

---

*End of Plan*
