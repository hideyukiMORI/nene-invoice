<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Audit;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditLog;
use NeneInvoice\Audit\AuditLogFilter;
use NeneInvoice\Audit\ListAuditLogsUseCase;
use NeneInvoice\Tests\Support\InMemoryAuditLogRepository;
use PHPUnit\Framework\TestCase;

final class ListAuditLogsUseCaseTest extends TestCase
{
    private InMemoryAuditLogRepository $logs;
    private ListAuditLogsUseCase $useCase;
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->logs = new InMemoryAuditLogRepository($this->holder);
        $this->useCase = new ListAuditLogsUseCase($this->logs);
    }

    public function test_lists_only_the_callers_organization_most_recent_first(): void
    {
        $this->logs->append(new AuditLog(action: 'invoice.created', entityType: 'invoice', organizationId: 1, entityId: 10));
        $this->logs->append(new AuditLog(action: 'invoice.issued', entityType: 'invoice', organizationId: 1, entityId: 10));
        $this->logs->append(new AuditLog(action: 'client.created', entityType: 'client', organizationId: 2, entityId: 99));

        $result = $this->useCase->execute(new AuditLogFilter(), 20, 0);

        self::assertSame(2, $result->total);
        self::assertCount(2, $result->items);
        self::assertSame('invoice.issued', $result->items[0]->action); // newest first
        self::assertSame('invoice.created', $result->items[1]->action);
    }

    public function test_pagination_applies_limit_and_offset(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->logs->append(new AuditLog(action: 'invoice.created', entityType: 'invoice', organizationId: 1, entityId: $i));
        }

        $page = $this->useCase->execute(new AuditLogFilter(), 2, 2);

        self::assertSame(5, $page->total);
        self::assertCount(2, $page->items);
    }

    public function test_empty_for_organization_with_no_logs(): void
    {
        $this->holder->set(7);
        $result = $this->useCase->execute(new AuditLogFilter(), 20, 0);

        self::assertSame(0, $result->total);
        self::assertSame([], $result->items);
    }

    public function test_filters_by_entity_type_action_and_actor(): void
    {
        $this->logs->append(new AuditLog(action: 'invoice.created', entityType: 'invoice', actorUserId: 5, organizationId: 1, entityId: 10));
        $this->logs->append(new AuditLog(action: 'invoice.issued', entityType: 'invoice', actorUserId: 6, organizationId: 1, entityId: 10));
        $this->logs->append(new AuditLog(action: 'client.created', entityType: 'client', actorUserId: 5, organizationId: 1, entityId: 99));

        $byEntity = $this->useCase->execute(new AuditLogFilter(entityType: 'invoice'), 20, 0);
        self::assertSame(2, $byEntity->total);

        $byAction = $this->useCase->execute(new AuditLogFilter(action: 'client.created'), 20, 0);
        self::assertSame(1, $byAction->total);
        self::assertSame('client', $byAction->items[0]->entityType);

        $byActor = $this->useCase->execute(new AuditLogFilter(actorUserId: 5), 20, 0);
        self::assertSame(2, $byActor->total);

        $combined = $this->useCase->execute(new AuditLogFilter(entityType: 'invoice', actorUserId: 5), 20, 0);
        self::assertSame(1, $combined->total);
        self::assertSame('invoice.created', $combined->items[0]->action);
    }
}
