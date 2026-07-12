<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Database;

use WorkEddy\Platform\Config\ConfigLoader;

final class ConnectionFactory
{
    public function __construct(private readonly ConfigLoader $config) {}

    public function create(): object
    {
        if (!class_exists(\Doctrine\DBAL\DriverManager::class)) {
            throw new \RuntimeException('Doctrine DBAL is not installed. Run composer install before creating a database connection.');
        }

        $url = (string) $this->config->get('database.url', $this->config->get('DATABASE_URL', ''));
        if ($url !== '') {
            return \Doctrine\DBAL\DriverManager::getConnection(['url' => $url]);
        }

        return \Doctrine\DBAL\DriverManager::getConnection([
            'driver' => (string) $this->config->get('database.driver', $this->config->get('DB_DRIVER', 'pdo_mysql')),
            'host' => (string) $this->config->get('database.host', $this->config->get('DB_HOST', '127.0.0.1')),
            'port' => (int) $this->config->get('database.port', $this->config->get('DB_PORT', 3306)),
            'dbname' => (string) $this->config->get('database.dbname', 'WorkEddy'),
            'user' => (string) $this->config->get('database.user', $this->config->get('DB_USERNAME', 'root')),
            'password' => (string) $this->config->get('database.password', $this->config->get('DB_PASSWORD', '')),
            'charset' => (string) $this->config->get('database.charset', $this->config->get('DB_CHARSET', 'utf8mb4')),
        ]);
    }

    public function createAnalyticsRead(): object
    {
        if (!$this->hasAnalyticsReadConfiguration()) {
            return $this->create();
        }

        $url = (string) $this->config->get('database.analytics.url', '');
        if ($url !== '') {
            return \Doctrine\DBAL\DriverManager::getConnection(['url' => $url]);
        }

        return \Doctrine\DBAL\DriverManager::getConnection([
            'driver' => (string) $this->config->get('database.analytics.driver', $this->config->get('database.driver', 'pdo_mysql')),
            'host' => (string) $this->config->get('database.analytics.host', $this->config->get('database.host', '127.0.0.1')),
            'port' => (int) $this->config->get('database.analytics.port', $this->config->get('database.port', 3306)),
            'dbname' => (string) $this->config->get('database.analytics.dbname', $this->config->get('database.dbname', 'WorkEddy')),
            'user' => (string) $this->config->get('database.analytics.user', $this->config->get('database.user', 'root')),
            'password' => (string) $this->config->get('database.analytics.password', $this->config->get('database.password', '')),
            'charset' => (string) $this->config->get('database.analytics.charset', $this->config->get('database.charset', 'utf8mb4')),
        ]);
    }

    public function analyticsQueryTimeoutMs(): int
    {
        return max(0, (int) $this->config->get('database.analytics.query_timeout_ms', 5000));
    }

    public function analyticsPrimaryFallbackLockSeconds(): int
    {
        return max(0, (int) $this->config->get('database.analytics.primary_fallback_lock_seconds', 60));
    }

    public function analyticsReadDbFailureLockSeconds(): int
    {
        return max(0, (int) $this->config->get('database.analytics.read_db_failure_lock_seconds', 30));
    }

    public function analyticsRequiresReadReplica(): bool
    {
        return (bool) $this->config->get('database.analytics.require_read_replica', false);
    }

    public function hasAnalyticsReadConfiguration(): bool
    {
        foreach (['database.analytics.url', 'database.analytics.host', 'database.analytics.dbname', 'database.analytics.user'] as $key) {
            $value = $this->config->get($key);
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }
}
