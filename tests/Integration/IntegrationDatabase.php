<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Integration;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Throwable;

/**
 * Connects the integration suite to a real MySQL / PostgreSQL database (the two
 * production targets — issue #396) with the production PDO settings (native
 * prepared statements). The schema is applied out-of-band by CI via Phinx
 * (`phinx migrate`) before the suite runs.
 *
 * Each adapter is configured from `<ADAPTER>_TEST_*` env vars and returns null
 * when the adapter is not configured or its PDO driver is missing — so the tests
 * skip and the default `composer test` stays serverless on SQLite.
 */
final class IntegrationDatabase
{
    public static function pgsql(): ?PdoDatabaseQueryExecutor
    {
        return self::fromEnv('PGSQL', 'pgsql', 'pdo_pgsql', 5432, 'utf8');
    }

    public static function mysql(): ?PdoDatabaseQueryExecutor
    {
        return self::fromEnv('MYSQL', 'mysql', 'pdo_mysql', 3306, 'utf8mb4');
    }

    private static function fromEnv(
        string $prefix,
        string $adapter,
        string $driver,
        int $defaultPort,
        string $charset,
    ): ?PdoDatabaseQueryExecutor {
        $host = self::env($prefix . '_TEST_HOST');

        if ($host === null || !extension_loaded($driver)) {
            return null;
        }

        $config = new DatabaseConfig(
            url: null,
            environment: 'test',
            adapter: $adapter,
            host: $host,
            port: (int) (self::env($prefix . '_TEST_PORT') ?? (string) $defaultPort),
            name: self::env($prefix . '_TEST_DB') ?? 'nene_invoice_test',
            user: self::env($prefix . '_TEST_USER') ?? 'root',
            password: self::env($prefix . '_TEST_PASSWORD') ?? '',
            charset: $charset,
        );

        $executor = new PdoDatabaseQueryExecutor(new PdoConnectionFactory($config));

        try {
            $executor->fetchOne('SELECT 1');
        } catch (Throwable) {
            return null;
        }

        return $executor;
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
