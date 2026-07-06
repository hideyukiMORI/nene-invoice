<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Audit;

use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditPayloadMode;
use Nene2\Audit\AuditRecorder;
use Nene2\Audit\AuditRecorderInterface;
use Nene2\Audit\AuditTableConfig;
use Nene2\Audit\PdoAuditEventRepository;
use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use Nene2\Http\UtcClock;
use NeneInvoice\Audit\AuditLogFilter;
use NeneInvoice\Audit\PdoAuditLogRepository;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end coverage of the framework audit module (`Nene2\Audit`, ADR 0014)
 * wired onto Invoice's existing `audit_logs` table via {@see AuditTableConfig}:
 * events are written by the framework recorder/repository and read back through
 * the product's thin {@see PdoAuditLogRepository} (org scoping + actor-email
 * join). Proves the config maps onto Invoice's physical columns and that reads
 * preserve the previous JSON shape, ordering, and integer id types.
 */
final class AuditLogTest extends TestCase
{
    private PdoAuditLogRepository $repository;
    private AuditRecorderInterface $recorder;
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

        // findAll resolves the actor email from the users table.
        $usersSchema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/users.sql');
        self::assertIsString($usersSchema);
        $pdo->exec($usersSchema);

        $this->pdo = $pdo;
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);

        $executor = new PdoDatabaseQueryExecutor($factory, $pdo);
        $events = new PdoAuditEventRepository($executor, self::tableConfig());
        $this->recorder = new AuditRecorder($events, new UtcClock());
        $this->repository = new PdoAuditLogRepository($events, $executor, $this->holder);
    }

    private static function tableConfig(): AuditTableConfig
    {
        return new AuditTableConfig(
            table: 'audit_logs',
            mode: AuditPayloadMode::BeforeAfter,
            actionColumn: 'action',
            entityTypeColumn: 'entity_type',
            entityIdColumn: 'entity_id',
            actorColumn: 'actor_user_id',
            organizationColumn: 'organization_id',
            occurredAtColumn: 'created_at',
            metadataColumn: null,
            beforeColumn: 'before_json',
            afterColumn: 'after_json',
            payloadColumn: null,
            idIsAutoIncrement: true,
        );
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
        $this->recorder->record(new AuditEvent(
            action: 'client.updated',
            entityType: 'client',
            entityId: 42,
            actorId: 7,
            organizationId: 1,
            before: ['name' => '前'],
            after: ['name' => '後'],
        ));

        $logs = $this->repository->findAll(new AuditLogFilter(), 10, 0);
        self::assertCount(1, $logs);
        self::assertSame('client.updated', $logs[0]->action);
        self::assertSame(7, $logs[0]->actorUserId);
        self::assertSame(42, $logs[0]->entityId);
        self::assertSame(['name' => '前'], $logs[0]->before);
        self::assertSame(['name' => '後'], $logs[0]->after);
    }

    public function test_event_is_written_to_the_products_physical_columns(): void
    {
        $this->recorder->record(new AuditEvent(
            action: 'invoice.issued',
            entityType: 'invoice',
            entityId: 5,
            actorId: 7,
            organizationId: 1,
            before: null,
            after: ['status' => 'issued'],
        ));

        $stmt = $this->pdo->query(
            'SELECT action, entity_type, entity_id, actor_user_id, organization_id, before_json, after_json, created_at FROM audit_logs',
        );
        self::assertNotFalse($stmt);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        self::assertIsArray($row);
        self::assertSame('invoice.issued', $row['action']);
        self::assertSame('invoice', $row['entity_type']);
        self::assertSame(5, (int) $row['entity_id']);
        self::assertSame(7, (int) $row['actor_user_id']);
        self::assertSame(1, (int) $row['organization_id']);
        self::assertNull($row['before_json']);
        self::assertSame('{"status":"issued"}', $row['after_json']);
        self::assertNotSame('', (string) $row['created_at']);
    }

    public function test_resolves_actor_email_and_falls_back_to_null(): void
    {
        $this->insertUser(7, 'operator@example.com');

        $this->recorder->record(new AuditEvent(action: 'invoice.issued', entityType: 'invoice', entityId: 5, actorId: 7, organizationId: 1, before: null, after: ['status' => 'issued']));
        // Actor 99 has no users row → email stays null (caller shows #id).
        $this->recorder->record(new AuditEvent(action: 'invoice.created', entityType: 'invoice', entityId: 6, actorId: 99, organizationId: 1, before: null, after: ['status' => 'draft']));

        $logs = $this->repository->findAll(new AuditLogFilter(), 10, 0);
        // Newest first: actor 99 (no email), then actor 7 (resolved).
        self::assertNull($logs[0]->actorEmail);
        self::assertSame('operator@example.com', $logs[1]->actorEmail);
    }

    public function test_logs_are_scoped_and_counted_per_organization(): void
    {
        $this->recorder->record(new AuditEvent(action: 'client.created', entityType: 'client', entityId: 1, actorId: 1, organizationId: 1, before: null, after: ['name' => 'A']));
        $this->recorder->record(new AuditEvent(action: 'client.deleted', entityType: 'client', entityId: 1, actorId: 1, organizationId: 1, before: ['name' => 'A'], after: null));
        $this->recorder->record(new AuditEvent(action: 'client.created', entityType: 'client', entityId: 9, actorId: 1, organizationId: 2, before: null, after: ['name' => 'B']));

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
        $this->recorder->record(new AuditEvent(action: 'invoice.created', entityType: 'invoice', entityId: 1, actorId: 5, organizationId: 1, before: null, after: ['status' => 'draft']));
        $this->recorder->record(new AuditEvent(action: 'invoice.issued', entityType: 'invoice', entityId: 1, actorId: 6, organizationId: 1, before: null, after: ['status' => 'issued']));
        $this->recorder->record(new AuditEvent(action: 'client.created', entityType: 'client', entityId: 2, actorId: 5, organizationId: 1, before: null, after: ['name' => 'A']));

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
