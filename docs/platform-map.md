# WorkEddy v2 Platform Map
## The Definitive Guide to Architecture, Core Services, and Module Development

This document serves as the system map for the WorkEddy v2 platform. It is designed to give developers and AI agents a complete, high-level and detail-oriented view of the system platform so they can build new modules or extend existing ones without scanning the entire core.

---

## 1. System Architecture Overview

WorkEddy v2 is structured as a **Modular Monolith** following Clean Architecture principles. It enforces strict separation of concerns through three primary namespaces, all adhering to PHP 8.1 standards:

1. **`WorkEddy\Platform\` (located in `v2/platform/`)**
   The shared runtime core. It provides infrastructure-level components like HTTP routing, queue dispatching, logging, transaction management, caching, database connection management, and system-wide middleware.
2. **`WorkEddy\Modules\` (located in `v2/modules/`)**
   Self-contained, bounded-context domain modules (e.g., `IAM`, `Storage`, `Billing`, `Audit`). Each module implements its own business logic, database repositories, controllers, views, settings, and authorization policies.
3. **`WorkEddy\Shared\` (located in `v2/shared/`)**
   Shared exceptions, core presentation templates, view renderers, and generic event interfaces that do not belong to the platform or any specific module.

### General Coding Guidelines
* **Strict Types**: Every new PHP file MUST declare strict types at the very top:
  ```php
  declare(strict_types=1);
  ```
* **Naming Conventions**:
  * Classes, Interfaces, and Service Providers: `StudlyCaps` (e.g., `PlatformServiceProvider`).
  * Methods and variables: `camelCase` (e.g., `storeUploadedFile`).
  * Environment variables, constants, and SQL tables: `UPPER_SNAKE_CASE` or `lower_snake_case` for databases (e.g., `WORKEDDY_DB_PORT`, `user_sessions`).
  * Folder naming conventions: Capitalized domains (e.g., `Domain/`, `Presentation/`, `Infrastructure/`).

---

## 2. Module Structure & Clean Architecture Layers

Each domain module under `v2/modules/{ModuleName}/` follows a standard folder structure to isolate business logic from delivery mechanisms:

```
v2/modules/{ModuleName}/
├── Domain/
│   ├── Entities/          # Rich, state-preserving domain models
│   ├── ValueObjects/      # Immutable values (e.g., Money, Status, UUID)
│   ├── Contracts/         # Interfaces for repositories and core services
│   └── Events/            # Domain event classes
├── Application/
│   ├── UseCases/          # Orchestrators of single-business operations
│   ├── DTOs/              # Request/Response Data Transfer Objects
│   ├── Services/          # Application-specific workflow controllers
│   └── Listeners/         # Event listeners executing module workflows
├── Infrastructure/
│   ├── Repositories/      # Database implementation of Domain Contracts (using DBAL)
│   ├── Jobs/              # Queue worker task handler implementations
│   └── Services/          # Integrations with external APIs, PDFs, or OS tools
├── Presentation/
│   ├── {Module}Controller.php      # JSON/API endpoints returning payloads
│   ├── {Module}PageController.php  # Web controllers rendering HTML views
│   ├── Views/             # Module-specific PHP template fragments
│   └── routes.php         # FastRoute definitions for this module
├── Settings/              # Module settings accessors & default provider (Exactly 2 files)
├── Authorization/         # Permissions list & definition provider (Exactly 2 files)
└── ServiceProvider.php    # DI registrations and module boot configurations
```

### Module Registration
To register a new module, its service provider must be added to the array in `v2/bootstrap/modules.php`:
```php
return [
    WorkEddy\Platform\PlatformServiceProvider::class,
    WorkEddy\Modules\IAM\ServiceProvider::class,
    // Add your module service provider class here:
    WorkEddy\Modules\NewModule\ServiceProvider::class,
];
```

---

## 3. The Module Service Provider Lifecycle

Every module provider registers endpoints and services using the `ModuleServiceProviderInterface` (`WorkEddy\Platform\Module\ModuleServiceProviderInterface`):

```php
namespace WorkEddy\Platform\Module;

use Psr\Container\ContainerInterface;

interface ModuleServiceProviderInterface
{
    public function getName(): string;
    public function getDefinitions(): array;
    public function getRouteFile(): ?string;
    public function getEventListeners(): array;
    public function getJobHandlers(): array;
    public function getPermissionDefinitionProvider(): mixed;
    public function getSettingsProvider(): mixed;
    public function getConsoleCommandProvider(): mixed;
    public function boot(ContainerInterface $container): void;
}
```

---

## 4. Summary Catalog of the `v2/platform` Directory

| Directory | Primary Contract / Class | Primary Responsibility |
| :--- | :--- | :--- |
| **Audit** | `IAuditService` | Tracks user state transitions and logs historical records. |
| **Authorization** | `IAuthorizationService` | Manages role permissions and checks privilege status. |
| **Cache** | `ICacheService` | Basic key-value caching (wrapped Symfony Cache). |
| **Clock** | `IClock` | Mockable system time wrapper (SystemClock, FrozenClock). |
| **Config** | `ConfigLoader` | Parses environment configurations and executes self-checks. |
| **Console** | `IConsoleCommandProvider` | Registers CLI tasks and runs migrations/sync commands. |
| **Container** | `PhpDiContainerFactory` | Bootstraps the PHP-DI container definitions. |
| **Cron** | `CronCommandRunner` | Coordinates system cron task triggers. |
| **Database** | `ConnectionFactory` | Creates low-level Doctrine DBAL connection handlers. |
| **Events** | `EventPublisherInterface` | Publishes synchronous or asynchronous events to listeners. |
| **Http** | `HttpKernel` | Handles request routing, middlewares, and responses. |
| **Identity** | `UuidGeneratorContract` | Provides native or Ramsey RFC-4122 UUID generation. |
| **Lock** | `LockManagerContract` | Acquires and releases Symfony-backed resource locks. |
| **Logging** | `ILoggerFactory` | Resolves channel-specific Monolog logging adapters. |
| **Module** | `ModuleRegistry` | Aggregates routes, DI definitions, and boots modules. |
| **Queue** | `IQueueService` | Enqueues jobs and processes them via `QueueWorker`. |
| **RateLimiting** | `RateLimiterContract` | Fixed-window request rate limiter. |
| **Schema** | `CanonicalSchemaBuilder` | Aggregates modular SQL schemas and compiles diffs. |
| **Session** | `ISessionService` | Manages active login contexts and CSRF tokens. |
| **Settings** | `SettingsService` | Persists and loads module default settings/tenant values. |
| **Transaction** | `TransactionManagerInterface` | Wraps database queries in atomic transactions. |
| **Transport** | `TransportStoreInterface` | Enterprise integration service bus for outbound/inbound webhooks. |

---

## 5. Module Development APIs

### 5.1 Working with the DBAL Database Connection
Use dependency injection to fetch `Doctrine\DBAL\Connection` to run SQL statements.

```php
use Doctrine\DBAL\Connection;

class DBExample {
    public function __construct(private Connection $db) {}

    public function createItem(string $uuid, string $name): void {
        $this->db->insert('my_table', [
            'uuid' => $uuid,
            'name' => $name,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
```

### 5.2 Checking and Declaring Permissions (The 2-File Pattern)

Every domain module manages access control using exactly two files in its `Authorization/` folder:

#### File 1: `{ModuleName}Permissions.php`
Stores all unique permission node string constants (using a `{module}.{action}` pattern).
```php
namespace WorkEddy\Modules\NewModule\Authorization;

final class NewModulePermissions
{
    public const VIEW = 'newmodule.view';
    public const MANAGE = 'newmodule.manage';
}
```

#### File 2: `{ModuleName}PermissionDefinitionProvider.php`
Implements `IPermissionDefinitionProvider` to define the meta profiles for permissions catalog synchronization.
```php
namespace WorkEddy\Modules\NewModule\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class NewModulePermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function definitions(): array
    {
        return [
            // The PermissionDefinition constructor supports two structures:
            // Structure A (Module first): new PermissionDefinition(module, key, label, description, classification, risk, defaultRoles, systemOnly)
            // Structure B (Key first):    new PermissionDefinition(key, label, description, module, classification, risk, defaultRoles, systemOnly)
            // It parses them intelligently based on which argument contains a dot '.'.
            new PermissionDefinition(
                'newmodule',
                NewModulePermissions::VIEW,
                'View NewModule resources',
                'Allows querying and displaying module records.',
                'read',
                'medium',
                ['super_admin', 'admin', 'viewer']
            ),
            new PermissionDefinition(
                'newmodule',
                NewModulePermissions::MANAGE,
                'Manage NewModule resources',
                'Allows creating, updating, and deleting module records.',
                'write',
                'high',
                ['super_admin', 'admin']
            ),
        ];
    }
}
```
*Note: This provider must be returned in `ServiceProvider::getPermissionDefinitionProvider()`.*

#### Enforcing Permissions inside Controllers
Enforce permissions using the platform's `IAuthorizationService`:
```php
use WorkEddy\Platform\Authorization\IAuthorizationService;
use WorkEddy\Modules\NewModule\Authorization\NewModulePermissions;

class NewModuleController {
    public function __construct(private readonly IAuthorizationService $auth) {}

    public function update(Request $request) {
        // Automatically checks the active session context and throws ForbiddenException on violation
        $this->auth->authorize(NewModulePermissions::MANAGE);
        // ... perform action ...
    }
}
```

---

### 5.3 Storing and Reading Settings (The 2-File Pattern)

Every domain module registers configurations using exactly two files in its `Settings/` folder:

#### File 1: `{ModuleName}Settings.php`
Extends `WorkEddy\Platform\Settings\ModuleSettings` to expose strongly-typed accessor methods.
```php
namespace WorkEddy\Modules\NewModule\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class NewModuleSettings extends ModuleSettings
{
    protected function moduleName(): string
    {
        return 'newmodule';
    }

    public function maxLimit(): int
    {
        return $this->getInt('max_limit');
    }

    public function isFeatureEnabled(): bool
    {
        return $this->getBool('enable_feature');
    }
}
```

#### File 2: `{ModuleName}SettingsProvider.php`
Implements `IModuleSettingsProvider` to configure defaults and custom value validation rules.
```php
namespace WorkEddy\Modules\NewModule\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class NewModuleSettingsProvider implements IModuleSettingsProvider
{
    public function getModuleName(): string
    {
        return 'newmodule';
    }

    public function getDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: 'max_limit',
                module: 'newmodule',
                type: SettingType::INTEGER,
                default: 100,
                label: 'Maximum Limit',
                description: 'The maximum allowed limit for this module.',
                validation: fn($v) => (int)$v > 0 ? true : 'Must be greater than 0'
            ),
            new SettingDefinition(
                key: 'enable_feature',
                module: 'newmodule',
                type: SettingType::BOOLEAN,
                default: false,
                label: 'Enable Feature',
                description: 'Enables advanced feature flags.'
            ),
        ];
    }
}
```
*Note: This provider must be returned in `ServiceProvider::getSettingsProvider()`.*

---

### 5.4 Dispatching and Subscribing to Events
Decouple module interactions using event listeners.

```php
// 1. Dispatch an event
use WorkEddy\Platform\Events\EventPublisherInterface;

$this->eventPublisher->publish(
    eventName: 'assessment.completed',
    payload: ['assessment_uuid' => $uuid],
    idempotencyKey: $uuid
);
```

```php
// 2. Write an Async listener
use WorkEddy\Platform\Events\IAsyncEventListener;

class GeneratePdfReportListener implements IAsyncEventListener {
    public function __invoke(array $payload): void {
        $uuid = $payload['assessment_uuid'];
        // ... perform heavy PDF creation ...
    }
}
```

```php
// 3. Register in ServiceProvider.php
public function getEventListeners(): array {
    return [
        'assessment.completed' => [
            \WorkEddy\Modules\Export\Application\Listeners\GeneratePdfReportListener::class,
        ],
    ];
}
```

### 5.5 File Storage Module Integration (`Storage`)
Use the central `Storage` module to handle files.

```php
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;

class VideoUploadService {
    public function __construct(private IStorageService $storageService) {}

    public function upload(string $path, string $fileName): string {
        $request = new StoreUploadedFileRequest($path, $fileName, 'video/mp4');
        $storedFile = $this->storageService->storeUploadedFile($request);
        return $storedFile->uuid;
    }
}
```

---

## 6. Step-by-Step Scaffolding Checklist for Agents

When building a new module, execute these steps in order:

* [ ] **Step 1: Create Directories**
  Create the `v2/modules/{ModuleName}` directory structure (`Domain`, `Application`, `Infrastructure`, `Presentation`, `Authorization`, `Settings`).
* [ ] **Step 2: Write Authorization/ (Exactly 2 files)**
  Create `{ModuleName}Permissions.php` and `{ModuleName}PermissionDefinitionProvider.php`.
* [ ] **Step 3: Write Settings/ (Exactly 2 files)**
  Create `{ModuleName}Settings.php` and `{ModuleName}SettingsProvider.php`.
* [ ] **Step 4: Write ServiceProvider.php**
  Implement `ModuleServiceProviderInterface` to register container definitions, routes, event listeners, settings providers, and permissions.
* [ ] **Step 5: Register Module**
  Add your provider namespace to `v2/bootstrap/modules.php`.
* [ ] **Step 6: Scaffolding Routes & Middleware**
  Create `Presentation/routes.php`. Bind HTTP verbs, routing parameters, controller targets, and middleware lists.
* [ ] **Step 7: Write Schema & Migrations**
  If the module requires database tables, create a migration file in `v2/migrations/` using SQL schema scripts. Be sure to include `legacy_id` columns if migrating from v1 datasets.
* [ ] **Step 8: Inject Contracts**
  Inject DBAL connections, logging channels, UUID generators, transaction managers, settings, and other core interfaces to implement domain business logic.
