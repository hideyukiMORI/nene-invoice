<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Audit;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use Nene2\Http\UtcClock;
use NeneInvoice\Audit\AuditLogFilter;
use NeneInvoice\Audit\AuditRecorder;
use NeneInvoice\Audit\PdoAuditLogRepository;
use PHPUnit\Framework\TestCase;

final class AuditLogTest extends TestCase
{
    private PdoAuditLogRepository $repository;
    private \PDO $pdo;
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;

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

        // findAll LEFT JOINs users to resolve the actor email.
        $usersSchema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/users.sql');
        self::assertIsString($usersSchema);
        $pdo->exec($usersSchema);

        $this->pdo = $pdo;
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repository = new PdoAuditLogRepository(new PdoDatabaseQueryExecutor($factory, $pdo), $this->holder);
    }

    private function insertUser(int $id, string $email): void
    {
        $this->pdo->exec(sprintf(
            "INSERT INTO users (id, email, password_hash, role, organization_id, status, created_at, updated_at)
             VALUES (%d, '%s', 'x', 'admin', 1, 'active', '2026-05-01 00:00:00', '2026-05-01 00:00:00')",
            $id,
            $email,
        ));
    }

    public function test_recorder_persists_before_and_after_snapshots(): void
    {
        $recorder = new AuditRecorder($this->repository, new UtcClock());

        $recorder->record(7, 1, 'client.updated', 'client', 42, ['name' => '前'], ['name' => '後']);

        $logs = $this->repository->findAll(new AuditLogFilter(), 10, 0);
        self::assertCount(1, $logs);
        self::assertSame('client.updated', $logs[0]->action);
        self::assertSame(7, $logs[0]->actorUserId);
        self::assertSame(42, $logs[0]->entityId);
        self::assertSame(['name' => '前'], $logs[0]->before);
        self::assertSame(['name' => '後'], $logs[0]->after);
    }

    public function test_resolves_actor_email_and_falls_back_to_null(): void
    {
        $this->insertUser(7, 'operator@example.com');

        $recorder = new AuditRecorder($this->repository, new UtcClock());
        $recorder->record(7, 1, 'invoice.issued', 'invoice', 5, null, ['status' => 'issued']);
        // Actor 99 has no users row → email stays null (caller shows #id).
        $recorder->record(99, 1, 'invoice.created', 'invoice', 6, null, ['status' => 'draft']);

        $logs = $this->repository->findAll(new AuditLogFilter(), 10, 0);
        // Newest first: actor 99 (no email), then actor 7 (resolved).
        self::assertNull($logs[0]->actorEmail);
        self::assertSame('operator@example.com', $logs[1]->actorEmail);
    }

    public function test_logs_are_scoped_and_counted_per_organization(): void
    {
        // append keeps the organization on the log itself, so it is recorded
        // with an explicit org regardless of the read-side holder.
        $recorder = new AuditRecorder($this->repository, new UtcClock());
        $recorder->record(1, 1, 'client.created', 'client', 1, null, ['name' => 'A']);
        $recorder->record(1, 1, 'client.deleted', 'client', 1, ['name' => 'A'], null);
        $recorder->record(1, 2, 'client.created', 'client', 9, null, ['name' => 'B']);

        $this->holder->set(1);
        self::assertSame(2, $this->repository->count(new AuditLogFilter()));
        $this->holder->set(2);
        self::assertSame(1, $this->repository->count(new AuditLogFilter()));

        // Most recent first (org 1).
        $this->holder->set(1);
        $logs = $this->repository->findAll(new AuditLogFilter(), 10, 0);
        self::assertSame('client.deleted', $logs[0]->action);
    }

    public function test_filters_by_entity_type_action_actor_and_date_range(): void
    {
        $recorder = new AuditRecorder($this->repository, new UtcClock());
        $recorder->record(5, 1, 'invoice.created', 'invoice', 1, null, ['status' => 'draft']);
        $recorder->record(6, 1, 'invoice.issued', 'invoice', 1, null, ['status' => 'issued']);
        $recorder->record(5, 1, 'client.created', 'client', 2, null, ['name' => 'A']);

        self::assertSame(2, $this->repository->count(new AuditLogFilter(entityType: 'invoice')));
        self::assertSame(1, $this->repository->count(new AuditLogFilter(action: 'invoice.issued')));
        self::assertSame(2, $this->repository->count(new AuditLogFilter(actorUserId: 5)));

        // A future lower bound excludes everything; a wide range includes all.
        self::assertSame(0, $this->repository->count(new AuditLogFilter(createdFrom: '2999-01-01 00:00:00')));
        self::assertSame(3, $this->repository->count(new AuditLogFilter(createdFrom: '2000-01-01 00:00:00', createdTo: '2999-12-31 23:59:59')));

        $filtered = $this->repository->findAll(new AuditLogFilter(entityType: 'invoice', actorUserId: 5), 10, 0);
        self::assertCount(1, $filtered);
        self::assertSame('invoice.created', $filtered[0]->action);
    }
}
