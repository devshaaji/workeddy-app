<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Config;

use Doctrine\DBAL\DriverManager;
use Throwable;

final class RuntimeEnvironmentValidator
{
    private const REQUIRED_CONFIG = [
        'app',
        'database',
        'cache',
        'logging',
        'lock',
        'migrations',
        'queue',
    ];

    public function __construct(
        private readonly ConfigLoader $config,
        private readonly string $rootPath,
    ) {}

    public static function assertBootable(string $rootPath): void
    {
        $validator = new self(new ConfigLoader($rootPath . '/config'), $rootPath);
        $result = $validator->diagnose(strict: false);
        if ($result->passed(strict: false)) {
            return;
        }

        $messages = array_map(
            static fn(array $check): string => sprintf('%s: %s', $check['name'], $check['message']),
            array_filter($result->checks, static fn(array $check): bool => $check['status'] !== 'pass'),
        );

        throw new \RuntimeException("WorkEddy runtime configuration is not bootable:\n- " . implode("\n- ", $messages));
    }

    public function diagnose(bool $strict = false): RuntimeDiagnosticResult
    {
        $checks = [
            $this->envPresent('APP_KEY'),
            $this->envPresent('APP_ENV'),
            $this->databaseEnvPresent(),
        ];

        foreach (self::REQUIRED_CONFIG as $name) {
            $checks[] = $this->configPresent($name);
        }

        array_push(
            $checks,
            $this->appKeyLength(),
            $this->runtimeIsWorkEddy(),
            $this->productionDebugDisabled(),
            $this->databaseDriverSupported(),
            $this->cacheDriverSupported(),
            $this->lockDriverSupported(),
            $this->queueDriverSupported(),
        );

        foreach ($this->runtimeDirectories() as $name => $path) {
            $checks[] = $this->directoryWritable($name, $path);
        }

        if ($strict) {
            $checks[] = $this->strictDatabaseSchemaReady();
            array_push($checks, ...$this->strictOperationalState());
        }

        return new RuntimeDiagnosticResult($checks);
    }

    /**
     * @return array{name:string,status:string,message:string,severity:string,context:array<string, mixed>}
     */
    private function envPresent(string $name): array
    {
        $value = trim((string) $this->env($name, ''));

        return $value !== ''
            ? $this->check("env.{$name}", 'pass', "{$name} is configured.")
            : $this->check("env.{$name}", 'fail', "{$name} is required and must not be empty.");
    }

    private function databaseEnvPresent(): array
    {
        if (trim((string) $this->env('DATABASE_URL', '')) !== '') {
            return $this->check('env.database', 'pass', 'DATABASE_URL is configured.');
        }

        $missing = [];
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER'] as $name) {
            $aliases = $name === 'DB_NAME' ? ['DB_NAME', 'DB_DATABASE'] : ($name === 'DB_USER' ? ['DB_USER', 'DB_USERNAME'] : [$name]);
            $present = false;
            foreach ($aliases as $alias) {
                if (trim((string) $this->env($alias, '')) !== '') {
                    $present = true;
                    break;
                }
            }

            if (!$present) {
                $missing[] = implode('/', $aliases);
            }
        }

        return $missing === []
            ? $this->check('env.database', 'pass', 'Database connection environment is configured.')
            : $this->check('env.database', 'fail', 'Database environment is incomplete.', ['missing' => $missing]);
    }

    /**
     * @return array{name:string,status:string,message:string,severity:string,context:array<string, mixed>}
     */
    private function configPresent(string $name): array
    {
        return $this->config->all($name) !== []
            ? $this->check("config.{$name}", 'pass', "{$name}.php loaded.")
            : $this->check("config.{$name}", 'fail', "{$name}.php must exist and return an array.");
    }

    private function appKeyLength(): array
    {
        $length = strlen(trim((string) $this->env('APP_KEY', '')));

        return $length >= 32
            ? $this->check('app.key_length', 'pass', 'APP_KEY length is acceptable.', ['length' => $length])
            : $this->check('app.key_length', 'fail', 'APP_KEY must contain at least 32 random characters.', ['length' => $length]);
    }

    private function runtimeIsWorkEddy(): array
    {
        $runtime = strtolower((string) $this->env('APP_RUNTIME', $this->config->get('app.runtime', 'WorkEddy')));

        return $runtime === 'WorkEddy'
            ? $this->check('app.runtime', 'pass', 'WorkEddy runtime is selected.', ['runtime' => $runtime])
            : $this->check('app.runtime', 'fail', 'APP_RUNTIME must be WorkEddy for the WorkEddy project.', ['runtime' => $runtime]);
    }

    private function productionDebugDisabled(): array
    {
        $env = strtolower((string) $this->env('APP_ENV', 'production'));
        $debug = filter_var($this->env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);

        return $env === 'production' && $debug
            ? $this->check('app.production_debug', 'fail', 'APP_DEBUG must be false in production.')
            : $this->check('app.production_debug', 'pass', 'Production debug setting is safe.');
    }

    private function databaseDriverSupported(): array
    {
        $driver = (string) ($this->env('DB_DRIVER') ?: $this->config->get('database.driver', ''));
        $supported = in_array($driver, ['pdo_mysql', 'pdo_pgsql', 'pdo_sqlite'], true);

        return $supported
            ? $this->check('database.driver', 'pass', 'Database driver is supported.', ['driver' => $driver])
            : $this->check('database.driver', 'fail', 'Database driver must be pdo_mysql, pdo_pgsql, or pdo_sqlite.', ['driver' => $driver]);
    }

    private function cacheDriverSupported(): array
    {
        $driver = strtolower((string) $this->config->get('cache.driver', 'filesystem'));

        return in_array($driver, ['filesystem', 'file'], true)
            ? $this->check('cache.driver', 'pass', 'Cache driver is supported.', ['driver' => $driver])
            : $this->check('cache.driver', 'fail', 'WorkEddy cache driver must be filesystem.', ['driver' => $driver]);
    }

    private function lockDriverSupported(): array
    {
        $driver = strtolower((string) $this->config->get('lock.driver', 'flock'));

        return $driver === 'flock'
            ? $this->check('lock.driver', 'pass', 'Lock driver is supported.', ['driver' => $driver])
            : $this->check('lock.driver', 'fail', 'WorkEddy lock driver must be flock.', ['driver' => $driver]);
    }

    private function queueDriverSupported(): array
    {
        $driver = strtolower((string) $this->config->get('queue.driver', 'database'));

        if (!in_array($driver, ['database', 'inline'], true)) {
            return $this->check('queue.driver', 'fail', 'Queue driver must be database or inline.', ['driver' => $driver]);
        }

        if ($driver === 'database') {
            return $this->check('queue.driver', 'pass', 'Queue driver is durable.', ['driver' => $driver]);
        }

        $env = strtolower((string) $this->env('APP_ENV', 'production'));
        $profile = strtolower((string) $this->env('APP_PROFILE', $this->config->get('app.profile', 'shared')));
        $inlineAllowed = $env === 'testing' || (in_array($env, ['development', 'local'], true) && $profile !== 'shared');

        return $inlineAllowed
            ? $this->check('queue.driver', 'pass', 'Inline queue is allowed for local/test runtime only.', ['driver' => $driver, 'env' => $env, 'profile' => $profile])
            : $this->check('queue.driver', 'fail', 'Inline queue is not durable enough for WorkEddy shared or production runtime. Use WorkEddy_QUEUE_DRIVER=database.', ['driver' => $driver, 'env' => $env, 'profile' => $profile]);
    }

    /**
     * @return array<string, string>
     */
    private function runtimeDirectories(): array
    {
        return [
            'cache' => (string) $this->config->get('cache.path', $this->rootPath . '/var/cache'),
            'locks' => (string) $this->config->get('lock.path', $this->rootPath . '/var/locks'),
            'logs' => dirname((string) $this->config->get('logging.channels.app.path', $this->rootPath . '/storage/log/app.log')),
        ];
    }

    private function directoryWritable(string $name, string $path): array
    {
        $resolved = $this->resolvePath($path);
        if (!is_dir($resolved)) {
            return $this->check("dir.{$name}", 'warn', 'Directory does not exist yet.', ['path' => $resolved]);
        }

        return is_writable($resolved)
            ? $this->check("dir.{$name}", 'pass', 'Directory is writable.', ['path' => $resolved])
            : $this->check("dir.{$name}", 'fail', 'Directory must be writable by the runtime user.', ['path' => $resolved]);
    }

    private function strictDatabaseSchemaReady(): array
    {
        if (!class_exists(DriverManager::class)) {
            return $this->check('database.schema_ready', 'fail', 'Doctrine DBAL is not installed.');
        }

        try {
            $connection = DriverManager::getConnection($this->databaseParams());
            $schema = $connection->createSchemaManager();
            $tables = array_map('strtolower', $schema->listTableNames());
            $missing = [];
            foreach (['sync_events', 'users', 'platform_jobs'] as $table) {
                if (!in_array($table, $tables, true)) {
                    $missing[] = $table;
                }
            }

            $migrationTable = (string) $this->config->get('migrations.table_storage.table_name', 'doctrine_migration_versions');
            if (!in_array(strtolower($migrationTable), $tables, true)) {
                $missing[] = $migrationTable;
            }

            $connection->close();

            if ($missing !== []) {
                return $this->check('database.schema_ready', 'fail', 'Database schema is missing required WorkEddy tables.', ['missing_tables' => $missing]);
            }
        } catch (Throwable $throwable) {
            return $this->check('database.schema_ready', 'fail', 'Database schema readiness could not be verified: ' . $throwable->getMessage());
        }

        return $this->check('database.schema_ready', 'pass', 'Database schema contains required WorkEddy tables.');
    }

    /**
     * @return list<array{name:string,status:string,message:string,severity:string,context:array<string, mixed>}>
     */
    private function strictOperationalState(): array
    {
        if (!class_exists(DriverManager::class)) {
            return [
                $this->check('operations.live_state', 'fail', 'Doctrine DBAL is not installed.'),
            ];
        }

        try {
            $connection = DriverManager::getConnection($this->databaseParams());
            $schema = $connection->createSchemaManager();
            $tables = array_map('strtolower', $schema->listTableNames());

            $checks = [
                $this->strictQueueBacklog($connection, $tables),
                $this->strictSyncInboxState($connection, $tables),
                $this->strictIdempotencyLocks($connection, $tables),
                $this->strictAnalyticsReadiness($tables),
            ];

            $connection->close();

            return $checks;
        } catch (Throwable $throwable) {
            return [
                $this->check('operations.live_state', 'fail', 'Live operational state could not be verified: ' . $throwable->getMessage()),
            ];
        }
    }

    /**
     * @param list<string> $tables
     */
    private function strictQueueBacklog(object $connection, array $tables): array
    {
        if (!in_array('platform_jobs', $tables, true)) {
            return $this->check('queue.backlog', 'fail', 'platform_jobs table is missing.');
        }

        $rows = $connection->fetchAllAssociative('SELECT status, COUNT(*) total FROM platform_jobs GROUP BY status');
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $this->check('queue.backlog', 'pass', 'Platform queue backlog is readable.', ['counts' => $counts]);
    }

    /**
     * @param list<string> $tables
     */
    private function strictSyncInboxState(object $connection, array $tables): array
    {
        if (!in_array('sync_events', $tables, true)) {
            return $this->check('sync.events_state', 'fail', 'sync_events table is missing.');
        }

        $events = (int) $connection->fetchOne('SELECT COUNT(*) FROM sync_events');

        return $this->check('sync.events_state', 'pass', 'Sync event store is readable.', ['events' => $events]);
    }

    /**
     * @param list<string> $tables
     */
    private function strictIdempotencyLocks(object $connection, array $tables): array
    {
        if (!in_array('idempotency_records', $tables, true)) {
            return $this->check('idempotency.stale_locks', 'fail', 'idempotency_records table is missing.');
        }

        $stale = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM idempotency_records WHERE status = 'processing' AND locked_until < :now",
            ['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u')],
        );

        return $this->check(
            'idempotency.stale_locks',
            $stale > 10 ? 'warn' : 'pass',
            'Idempotency lock state is readable.',
            ['stale_locks' => $stale],
        );
    }

    /**
     * @param list<string> $tables
     */
    private function strictAnalyticsReadiness(array $tables): array
    {
        $missing = [];
        foreach (['analytics_sales_facts', 'analytics_inventory_movement_facts', 'analytics_daily_outlet_metrics'] as $table) {
            if (!in_array($table, $tables, true)) {
                $missing[] = $table;
            }
        }

        if ($missing !== []) {
            return $this->check('analytics.readiness', 'fail', 'Analytics tables are missing.', ['missing_tables' => $missing]);
        }

        $fallbackLock = (int) $this->config->get('database.analytics.primary_fallback_lock_seconds', 60);
        $readDbLock = (int) $this->config->get('database.analytics.read_db_failure_lock_seconds', 30);

        return $this->check('analytics.readiness', 'pass', 'Analytics read model and fallback lock configuration are present.', [
            'primary_fallback_lock_seconds' => $fallbackLock,
            'read_db_failure_lock_seconds' => $readDbLock,
            'requires_read_replica' => (bool) $this->config->get('database.analytics.require_read_replica', false),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseParams(): array
    {
        $url = trim((string) $this->env('DATABASE_URL', $this->config->get('database.url', '')));
        if ($url !== '') {
            return ['url' => $url];
        }

        return [
            'driver' => (string) $this->env('DB_DRIVER', $this->config->get('database.driver', 'pdo_mysql')),
            'host' => (string) $this->env('DB_HOST', $this->config->get('database.host', '127.0.0.1')),
            'port' => (int) $this->env('DB_PORT', $this->config->get('database.port', 3306)),
            'dbname' => (string) $this->env('DB_NAME', $this->env('DB_DATABASE', $this->config->get('database.dbname', 'WorkEddy'))),
            'user' => (string) $this->env('DB_USER', $this->env('DB_USERNAME', $this->config->get('database.user', 'root'))),
            'password' => (string) $this->env('DB_PASSWORD', $this->config->get('database.password', '')),
            'charset' => (string) $this->env('DB_CHARSET', $this->config->get('database.charset', 'utf8mb4')),
        ];
    }

    private function env(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $value = getenv($key);

        return $value === false ? $default : $value;
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return $this->rootPath;
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return $this->rootPath . '/' . $path;
    }

    /**
     * @param array<string, mixed> $context
     * @return array{name:string,status:string,message:string,severity:string,context:array<string, mixed>}
     */
    private function check(string $name, string $status, string $message, array $context = []): array
    {
        return [
            'name' => $name,
            'status' => $status,
            'message' => $message,
            'severity' => $status === 'fail' ? 'error' : ($status === 'warn' ? 'warning' : 'info'),
            'context' => $context,
        ];
    }
}
