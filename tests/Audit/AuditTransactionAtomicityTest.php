<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Audit;

use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditPayloadMode;
use Nene2\Audit\AuditRecorderFactory;
use Nene2\Audit\AuditTableConfig;
use Nene2\Config\DatabaseConfig;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Database\PdoDatabaseTransactionManager;
use Nene2\Http\UtcClock;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Atomicity of the framework audit write (ADR 0014, Issue #352): a record made
 * through {@see AuditRecorderFactory::forExecutor()} inside a transaction is
 * bound to that transaction's executor, so if the surrounding business
 * operation fails the audit row rolls back with it — the trail never records an
 * operation that did not commit.
 *
 * Exercised against a real (file-backed) SQLite transaction so the rollback is
 * observable across connections.
 */
final class AuditTransactionAtomicityTest extends TestCase
{
    private string $dbPath;
    private PdoConnectionFactory $factory;
    private PdoDatabaseQueryExecutor $executor;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'nene_audit_atomic_');
        self::assertIsString($path);
        $this->dbPath = $path;

        $config = new DatabaseConfig(
            url: null,
            environment: 'test',
            adapter: 'sqlite',
            host: 'localhost',
            port: 1,
            name: $this->dbPath,
            user: 'sqlite',
            password: '',
            charset: 'utf8',
        );
        $this->factory = new PdoConnectionFactory($config);

        $pdo = $this->factory->create();
        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/audit_logs.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->executor = new PdoDatabaseQueryExecutor($this->factory, $pdo);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function transactionManager(): DatabaseTransactionManagerInterface
    {
        return new PdoDatabaseTransactionManager($this->factory);
    }

    private static function tableConfig(): AuditTableConfig
    {
        return new AuditTableConfig(
            table: 'audit_logs',
            mode: AuditPayloadMode::BeforeAfter,
            actorColumn: 'actor_user_id',
            occurredAtColumn: 'created_at',
            metadataColumn: null,
            beforeColumn: 'before_json',
            afterColumn: 'after_json',
            idIsAutoIncrement: true,
        );
    }

    public function test_audit_row_rolls_back_when_the_transaction_fails(): void
    {
        $tx = $this->transactionManager();
        $factory = new AuditRecorderFactory(new UtcClock(), self::tableConfig());

        try {
            $tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($factory): void {
                $factory->forExecutor($exec)->record(new AuditEvent(
                    action: 'client.created',
                    entityType: 'client',
                    entityId: 1,
                    actorId: 7,
                    organizationId: 1,
                    before: null,
                    after: ['name' => 'A'],
                ));

                // The business mutation fails after the audit write inside the
                // same transaction.
                throw new RuntimeException('business failure');
            });
        } catch (RuntimeException) {
            // Expected: the business failure propagates out of the transaction.
        }

        // The audit row written inside the failed transaction must have rolled
        // back with it — no orphan trail entry.
        $row = $this->executor->fetchOne('SELECT COUNT(*) AS cnt FROM audit_logs');
        self::assertNotNull($row);
        self::assertSame(0, (int) $row['cnt']);
    }

    public function test_audit_row_commits_when_the_transaction_succeeds(): void
    {
        $tx = $this->transactionManager();
        $factory = new AuditRecorderFactory(new UtcClock(), self::tableConfig());

        $tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($factory): void {
            $factory->forExecutor($exec)->record(new AuditEvent(
                action: 'client.created',
                entityType: 'client',
                entityId: 1,
                actorId: 7,
                organizationId: 1,
                before: null,
                after: ['name' => 'A'],
            ));
        });

        $row = $this->executor->fetchOne('SELECT COUNT(*) AS cnt FROM audit_logs');
        self::assertNotNull($row);
        self::assertSame(1, (int) $row['cnt']);
    }
}
