<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Audit;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use NeneInvoice\Audit\AuditRecorder;
use NeneInvoice\Audit\PdoAuditLogRepository;
use PHPUnit\Framework\TestCase;

final class AuditLogTest extends TestCase
{
    private PdoAuditLogRepository $repository;

    protected function setUp(): void
    {
        $config = new DatabaseConfig(
            url: null,
            environment: 'test',
            adapter: 'sqlite',
            host: 'localhost',
            port: 1,
            name: ':memory:',
            user: 'sqlite',
            password: '',
            charset: 'utf8',
        );
        $factory = new PdoConnectionFactory($config);
        $pdo = $factory->create();

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/audit_logs.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->repository = new PdoAuditLogRepository(new PdoDatabaseQueryExecutor($factory, $pdo));
    }

    public function test_recorder_persists_before_and_after_snapshots(): void
    {
        $recorder = new AuditRecorder($this->repository);

        $recorder->record(7, 1, 'client.updated', 'client', 42, ['name' => '前'], ['name' => '後']);

        $logs = $this->repository->findByOrganization(1, 10, 0);
        self::assertCount(1, $logs);
        self::assertSame('client.updated', $logs[0]->action);
        self::assertSame(7, $logs[0]->actorUserId);
        self::assertSame(42, $logs[0]->entityId);
        self::assertSame(['name' => '前'], $logs[0]->before);
        self::assertSame(['name' => '後'], $logs[0]->after);
    }

    public function test_logs_are_scoped_and_counted_per_organization(): void
    {
        $recorder = new AuditRecorder($this->repository);
        $recorder->record(1, 1, 'client.created', 'client', 1, null, ['name' => 'A']);
        $recorder->record(1, 1, 'client.deleted', 'client', 1, ['name' => 'A'], null);
        $recorder->record(1, 2, 'client.created', 'client', 9, null, ['name' => 'B']);

        self::assertSame(2, $this->repository->countByOrganization(1));
        self::assertSame(1, $this->repository->countByOrganization(2));

        // Most recent first.
        $logs = $this->repository->findByOrganization(1, 10, 0);
        self::assertSame('client.deleted', $logs[0]->action);
    }
}
