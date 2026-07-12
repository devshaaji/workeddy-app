# Billing Module Update Plan — Subscription Rework Integration

**Status:** Draft  
**Scope:** `v2/modules/Billing/` + `v2/platform/Schema/Modules/Billing/` + `v2/migrations/`  
**Prerequisite:** `IOrganizationRepository::findById()` must exist (see [Organization fix](#appendix-a-organization-fix-reference))

---

## 1. Objective

Update the `Billing` module so that invoices and quotations can be linked to `Subscription` entities via `subscription_uuid`, allowing the Subscription module (post-rework) to:

- Generate invoices on subscription activation/renewal with proper FK linkage
- Query invoices and quotations by subscription UUID
- Support usage-based billing and proration in future phases

---

## 2. Current State Summary

### 2.1 Billing Domain Entities

| Entity | Fields relevant to this plan | Issue |
|---|---|---|
| `Invoice` | `customerId` (int), `quotationId` (?int) | **No `subscriptionUuid`** |
| `Quotation` | `customerId` (int), `leadId` (?int) | **No `subscriptionUuid`** |

### 2.2 Repository Contracts

| Interface | Methods relevant to this plan | Gap |
|---|---|---|
| `IInvoiceRepository` | `create`, `findById`, `findByUuid`, `update`, `archive`, `list` (filterable by `customer_id`, `quotation_id`, `status`) | **No `findBySubscriptionUuid`** |
| `IQuotationRepository` | Same pattern + filterable by `customer_id`, `lead_id`, `status` | **No `findBySubscriptionUuid`** |

### 2.3 Database Schema (via `BillingSchemaBuilder`)

| Table | Relevant columns | Gap |
|---|---|---|
| `billing_invoices` | `customer_id`, `quotation_id` | **No `subscription_uuid`** |
| `billing_quotations` | `customer_id`, `lead_id` | **No `subscription_uuid`** |

### 2.4 Use Cases

| Use Case | Current signature | Gap |
|---|---|---|
| `GenerateInvoice::execute()` | `(int $customerId, ?string $quotationUuid, array $items, string $currency, ?int $daysUntilDue, ?int $actorId)` | **No `?string $subscriptionUuid` param** |
| `GenerateQuotation::execute()` | `(int $customerId, ?int $leadId, array $items, string $currency, ?int $daysUntilExpiry, ?int $actorId)` | **No `?string $subscriptionUuid` param** |

### 2.5 Cross-Module Dependency Issue

The Billing module has a **critical unresolved dependency** on a non-existent `Customer` module:

- `GeneratePdf` injects `\WorkEddy\Modules\Customer\Domain\Contracts\ICustomerRepository`
- `DbalInvoiceRepository` JOINs against `customers` table (`LEFT JOIN customers c ON i.customer_id = c.id`)
- `DbalQuotationRepository` JOINs against `customers` table
- `GenerateInvoice` depends on `CustomerNotificationRecipientFactory`

There is **no `v2/modules/Customer/` directory**. The `customer_id` field, `customers` table, and `ICustomerRepository` are v1 artifacts. This must be resolved as part of this plan.

---

## 3. Proposed Changes — By Layer

### 3.1 Domain Entities

#### 3.1.1 `Invoice.php` — Add `subscriptionUuid`

```php
public readonly ?string $subscriptionUuid = null,
```

Also add to `toArray()`:
```php
'subscription_uuid' => $this->subscriptionUuid,
```

#### 3.1.2 `Quotation.php` — Add `subscriptionUuid`

```php
public readonly ?string $subscriptionUuid = null,
```

Also add to `toArray()`.

#### 3.1.3 Add `BillingCustomerId` value object (Recommended)

To resolve the `customer_id` vs `organization_id` ambiguity, add:
```php
// v2/modules/Billing/Domain/ValueObjects/BillingCustomerId.php
final class BillingCustomerId
{
    public function __construct(
        public readonly int $customerId,
        public readonly ?int $organizationId = null, // v2 organization FK
    ) {}
}
```
This keeps backward compatibility while enabling the v2 migration path.

---

### 3.2 Repository Contracts

#### 3.2.1 `IInvoiceRepository.php` — Add method

```php
/**
 * @return list<Invoice>
 */
public function findBySubscriptionUuid(string $subscriptionUuid): array;
```

Also add to `list()` filters: `subscription_uuid` filter support.

#### 3.2.2 `IQuotationRepository.php` — Add method

```php
/**
 * @return list<Quotation>
 */
public function findBySubscriptionUuid(string $subscriptionUuid): array;
```

Also add to `list()` filters: `subscription_uuid` filter support.

---

### 3.3 Infrastructure (DBAL Repositories)

#### 3.3.1 `DbalInvoiceRepository.php`

In `create()` — add `subscription_uuid` to the INSERT:
```php
'subscription_uuid' => $data['subscription_uuid'] ?? null,
```

In `mapToEntity()` — hydrate:
```php
subscriptionUuid: $row['subscription_uuid'] ?? null,
```

In `list()` — add filter:
```php
if (isset($filters['subscription_uuid'])) {
    $qb->andWhere('i.subscription_uuid = :subscriptionUuid')
       ->setParameter('subscriptionUuid', $filters['subscription_uuid']);
}
```

Add new method:
```php
public function findBySubscriptionUuid(string $subscriptionUuid): array
{
    return $this->list(['subscription_uuid' => $subscriptionUuid]);
}
```

#### 3.3.2 `DbalQuotationRepository.php`

Same pattern as above — add `subscription_uuid` to create, hydrate, list, and add `findBySubscriptionUuid`.

---

### 3.4 Schema Builder

#### 3.4.1 `BillingSchemaBuilder.php`

In `createQuotations()` — add column:
```php
$table->addColumn('subscription_uuid', 'string', ['length' => 36, 'notnull' => false]);
$table->addIndex(['subscription_uuid'], 'billing_quotations_subscription_idx');
```

In `createInvoices()` — add column and index:
```php
$table->addColumn('subscription_uuid', 'string', ['length' => 36, 'notnull' => false]);
$table->addIndex(['subscription_uuid'], 'billing_invoices_subscription_idx');
```

---

### 3.5 Use Cases

#### 3.5.1 `GenerateInvoice.php` — Add `subscriptionUuid` parameter

```php
public function execute(
    int $customerId,
    ?string $quotationUuid,
    array $items,
    string $currency,
    ?int $daysUntilDue,
    ?int $actorId,
    ?string $subscriptionUuid = null,  // NEW
): Invoice {
```

In the `create()` call at the bottom, add:
```php
'subscription_uuid' => $subscriptionUuid,
```

#### 3.5.2 `GenerateQuotation.php` — Add `subscriptionUuid` parameter

Same pattern:
```php
?string $subscriptionUuid = null,
```

---

### 3.6 Service Provider — DI Updates

#### `ServiceProvider.php`

Update `GenerateInvoice` DI registration to include the new parameter (auto-wiring handles optional params if using autowire, but the current code uses factory closures):

```php
GenerateInvoice::class => static function (ContainerInterface $c) {
    return new GenerateInvoice(
        $c->get(IInvoiceRepository::class),
        $c->get(IQuotationRepository::class),
        $c->get(IAuditService::class),
        $c->get(IClock::class),
        $c->get(NotificationServiceInterface::class),
        $c->get(CustomerNotificationRecipientFactory::class),
    );
},
```
No change needed here since the new constructor parameter is optional with a default value of `null`.

---

### 3.7 Migration

Create a new migration file:
```
v2/migrations/Version20260708000001_AddSubscriptionUuidToBillingTables.php
```

**`up()`:**
```sql
ALTER TABLE billing_invoices
  ADD COLUMN subscription_uuid CHAR(36) NULL AFTER quotation_id,
  ADD INDEX billing_invoices_subscription_idx (subscription_uuid);

ALTER TABLE billing_quotations
  ADD COLUMN subscription_uuid CHAR(36) NULL AFTER lead_id,
  ADD INDEX billing_quotations_subscription_idx (subscription_uuid);
```

**`down()`:**
```sql
ALTER TABLE billing_invoices DROP INDEX billing_invoices_subscription_idx,
                            DROP COLUMN subscription_uuid;
ALTER TABLE billing_quotations DROP INDEX billing_quotations_subscription_idx,
                              DROP COLUMN subscription_uuid;
```

---

## 4. Cross-Module Dependency: Customer Module Resolution

### 4.1 Problem

The Billing module references `\WorkEddy\Modules\Customer\Domain\Contracts\ICustomerRepository` in `GeneratePdf` and uses `customers` table JOINs in both DBAL repositories. There is no `Customer` module in v2.

### 4.2 Options

| Option | Effort | Risk | Recommendation |
|---|---|---|---|
| **A. Create v2 Customer module** mapping v1 `customers` table to a v2 entity | Medium | Low — clean but adds a whole module | ✅ **Recommended** if customer data is needed independently of organizations |
| **B. Replace `ICustomerRepository` with `IOrganizationRepository`** | Low | Medium — loses customer-specific fields (first/last name, company name) | ✅ **Recommended** for the subscription integration. The subscription plan says "pass `organization_id` as `customer_id`" |
| **C. Keep as-is (assume v1 table exists)** | None | High — fragile, the table may not exist in fresh v2 deployments | ❌ Not recommended |

### 4.3 Recommended Approach

**Two-phase resolution:**

**Phase 1 (immediate, in this plan):**
- Remove the `customers` table JOINs from DBAL repositories. Replace `customerName` hydration with a lookup via `IOrganizationRepository::findById()` — the organization's `name` serves as the customer name.
- For `GeneratePdf`, replace `ICustomerRepository` with `IOrganizationRepository` and map `customer_id → organization_id`.

**Phase 2 (separate ticket):**
- If customer-specific fields (first name, last name, company name separate from org name) are needed, create a lightweight `v2/modules/Customer/` module.

---

## 5. Integration Points with Subscription Module

### 5.1 Subscription → Billing (events the Subscription module will emit that Billing should handle)

| Event | Handler | Action |
|---|---|---|
| `subscription.activated` | `GenerateInvoiceOnActivation` (in Subscription module) | Calls `GenerateInvoice::execute()` passing `subscriptionUuid` |
| `subscription.renewed` | `InvoiceOnRenewal` (in Subscription module) | Calls `GenerateInvoice::execute()` with renewal items |

### 5.2 Billing → Subscription (events Billing emits that Subscription should listen to)

| Event | Handler | Action |
|---|---|---|
| `invoice.paid` | Subscription listener (new) | Update subscription `expiry_date`, mark payment current |
| `invoice.overdue` | Subscription listener (new) | Suspend subscription after grace period |

The `invoice.paid` event publication already exists in `PaymentCompletedListener.php` — no changes needed. The Subscription module just needs to register a listener.

---

## 6. Implementation Roadmap

### Step 1: Fix the Customer module dependency
- [ ] 1.1 Replace `ICustomerRepository` dependency in `GeneratePdf` with `IOrganizationRepository`
- [ ] 1.2 Remove `customers` table JOINs from `DbalInvoiceRepository::mapToEntity()` and `findById/findByUuid`
- [ ] 1.3 Remove `customers` table JOINs from `DbalQuotationRepository::mapToEntity()` and `findById/findByUuid`
- [ ] 1.4 Update `GenerateInvoice` notification — replace `CustomerNotificationRecipientFactory` with an organization-aware equivalent (or make it optional)
- [ ] 1.5 Update `ServiceProvider.php` — remove `ICustomerRepository` from `GeneratePdf` DI

### Step 2: Add `subscription_uuid` to domain entities
- [ ] 2.1 Add `?string $subscriptionUuid = null` to `Invoice` entity + `toArray()`
- [ ] 2.2 Add `?string $subscriptionUuid = null` to `Quotation` entity + `toArray()`

### Step 3: Update schema and migration
- [ ] 3.1 Add `subscription_uuid` column + index to `BillingSchemaBuilder` for both tables
- [ ] 3.2 Create migration `Version20260708000001_AddSubscriptionUuidToBillingTables.php`

### Step 4: Update repository contracts and implementations
- [ ] 4.1 Add `findBySubscriptionUuid` to `IInvoiceRepository` + `list()` filter support
- [ ] 4.2 Add `findBySubscriptionUuid` to `IQuotationRepository` + `list()` filter support
- [ ] 4.3 Update `DbalInvoiceRepository` — hydrate, create, list, add `findBySubscriptionUuid`
- [ ] 4.4 Update `DbalQuotationRepository` — same pattern

### Step 5: Update use cases
- [ ] 5.1 Add `?string $subscriptionUuid = null` parameter to `GenerateInvoice::execute()`
- [ ] 5.2 Add `?string $subscriptionUuid = null` parameter to `GenerateQuotation::execute()`
- [ ] 5.3 Pass `subscriptionUuid` through to `$this->invoices->create()` / `$this->quotations->create()`

### Step 6: Update service provider
- [ ] 6.1 Verify DI registrations work with new optional constructor params
- [ ] 6.2 Remove any stale references to `ICustomerRepository`

### Step 7: Tests
- [ ] 7.1 Add test: `findBySubscriptionUuid` returns correct invoices
- [ ] 7.2 Add test: `GenerateInvoice` with `subscriptionUuid` stores it correctly
- [ ] 7.3 Add test: `GenerateQuotation` with `subscriptionUuid` stores it correctly
- [ ] 7.4 Update existing tests that mock `IInvoiceRepository` / `IQuotationRepository` (if any)

---

## 7. Risks & Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Removing `customers` table JOINs breaks existing invoice/quotation views that show customer name | Medium | Keep `customerName` in entity as a denormalized field, hydrate from organization name instead. Add a migration to backfill. |
| `CustomerNotificationRecipientFactory` removal breaks notification delivery | Medium | Replace with `OrganizationNotificationRecipientFactory` or make notification optional. The Notification module may need an update. |
| Existing test mocks of `IInvoiceRepository` don't include `findBySubscriptionUuid` | Low | Add the method to all test doubles. The pattern is established from the Organization fix. |
| Schema builder change would only affect fresh installs; existing DBs need the migration | Low | That's expected — the migration handles existing installs, the schema builder handles fresh installs. |

---

## 8. Success Criteria

- [ ] `Invoice` and `Quotation` entities have `subscriptionUuid` field
- [ ] `billing_invoices` and `billing_quotations` tables have `subscription_uuid` column + index
- [ ] `IInvoiceRepository::findBySubscriptionUuid()` returns linked invoices
- [ ] `IQuotationRepository::findBySubscriptionUuid()` returns linked quotations
- [ ] `GenerateInvoice::execute()` accepts and persists `subscriptionUuid`
- [ ] `GenerateQuotation::execute()` accepts and persists `subscriptionUuid`
- [ ] No remaining references to non-existent `ICustomerRepository` or `customers` table JOINs
- [ ] All existing tests pass
- [ ] `subscription.activated` → `GenerateInvoice` integration works end-to-end (tested with Subscription module)

---

## 9. File Change Inventory

### Files to MODIFY (11)

| File | Change |
|---|---|
| `Domain/Entities/Invoice.php` | Add `subscriptionUuid` property + toArray |
| `Domain/Entities/Quotation.php` | Add `subscriptionUuid` property + toArray |
| `Domain/Contracts/IInvoiceRepository.php` | Add `findBySubscriptionUuid` |
| `Domain/Contracts/IQuotationRepository.php` | Add `findBySubscriptionUuid` |
| `Infrastructure/DbalInvoiceRepository.php` | Add subscription_uuid to create, hydrate, list, and new method |
| `Infrastructure/DbalQuotationRepository.php` | Same pattern |
| `Application/UseCases/GenerateInvoice.php` | Add `subscriptionUuid` parameter |
| `Application/UseCases/GenerateQuotation.php` | Add `subscriptionUuid` parameter |
| `Application/UseCases/GeneratePdf.php` | Replace `ICustomerRepository` with `IOrganizationRepository` |
| `ServiceProvider.php` | Update DI registrations |
| `platform/Schema/Modules/Billing/BillingSchemaBuilder.php` | Add column + index to both tables |

### Files to CREATE (2)

| File | Purpose |
|---|---|
| `migrations/Version20260708000001_AddSubscriptionUuidToBillingTables.php` | Migration to add columns to existing DBs |
| `tests/Billing/SubscriptionBillingIntegrationTest.php` | Tests for subscription-linked billing operations |

### Dependencies to REMOVE (module-level)

- `\WorkEddy\Modules\Customer\Domain\Contracts\ICustomerRepository` — no longer injected
- `customers` table JOINs in DBAL repositories

---

## Appendix A: Organization Fix Reference

The `IOrganizationRepository::findById(int $id): ?Organization` method has been implemented:

| File | Status |
|---|---|
| `v2/modules/Organization/Domain/Contracts/IOrganizationRepository.php` | ✅ `findById` added to interface |
| `v2/modules/Organization/Infrastructure/OrganizationRepository.php` | ✅ DBAL implementation |
| `v2/tests/Organization/OrganizationUseCasesTest.php` | ✅ In-memory test double updated |
| `v2/tests/Organization/OrganizationStructureUseCasesTest.php` | ✅ In-memory test double updated |
| `v2/tests/Task/TaskModuleTest.php` | ✅ In-memory test double updated |
| `v2/tests/Assessment/AssessmentModuleTest.php` | ✅ In-memory test double updated |

Tests verified: **5 Organization tests + 3 Task tests + 3 Assessment tests = 11 tests, 84 assertions — all pass.**

---

*End of Plan*
