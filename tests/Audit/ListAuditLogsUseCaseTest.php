<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Audit;

use NeneInvoice\Audit\AuditLog;
use NeneInvoice\Audit\ListAuditLogsUseCase;
use NeneInvoice\Tests\Support\InMemoryAuditLogRepository;
use PHPUnit\Framework\TestCase;

final class ListAuditLogsUseCaseTest extends TestCase
{
    private InMemoryAuditLogRepository $logs;
    private ListAuditLogsUseCase $useCase;

    protected function setUp(): void
    {
        $this->logs = new InMemoryAuditLogRepository();
        $this->useCase = new ListAuditLogsUseCase($this->logs);
    }

    public function test_lists_only_the_callers_organization_most_recent_first(): void
    {
        $this->logs->append(new AuditLog(action: 'invoice.created', entityType: 'invoice', organizationId: 1, entityId: 10));
        $this->logs->append(new AuditLog(action: 'invoice.issued', entityType: 'invoice', organizationId: 1, entityId: 10));
        $this->logs->append(new AuditLog(action: 'client.created', entityType: 'client', organizationId: 2, entityId: 99));

        $result = $this->useCase->execute(1, 20, 0);

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

        $page = $this->useCase->execute(1, 2, 2);

        self::assertSame(5, $page->total);
        self::assertCount(2, $page->items);
    }

    public function test_empty_for_organization_with_no_logs(): void
    {
        $result = $this->useCase->execute(7, 20, 0);

        self::assertSame(0, $result->total);
        self::assertSame([], $result->items);
    }
}
