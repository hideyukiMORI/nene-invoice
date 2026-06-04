<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Audit;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditLog;
use NeneInvoice\Audit\AuditLogFilter;
use NeneInvoice\Audit\ExportAuditLogsCsvUseCase;
use NeneInvoice\Tests\Support\InMemoryAuditLogRepository;
use PHPUnit\Framework\TestCase;

final class ExportAuditLogsCsvUseCaseTest extends TestCase
{
    private InMemoryAuditLogRepository $logs;
    private ExportAuditLogsCsvUseCase $useCase;
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->logs = new InMemoryAuditLogRepository($this->holder);
        $this->useCase = new ExportAuditLogsCsvUseCase($this->logs);
    }

    public function test_exports_a_bom_csv_with_header_and_rows(): void
    {
        $this->logs->append(new AuditLog(
            action: 'invoice.issued',
            entityType: 'invoice',
            actorUserId: 7,
            organizationId: 1,
            entityId: 5,
            before: ['status' => 'draft'],
            after: ['status' => 'issued'],
        ));

        $csv = $this->useCase->execute(new AuditLogFilter());

        // UTF-8 BOM for Excel.
        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        self::assertStringContainsString('日時,アクション,対象種別,対象ID,操作者ID,操作者メール,変更前,変更後', $csv);
        self::assertStringContainsString('invoice.issued', $csv);
        self::assertStringContainsString('"{""status"":""draft""}"', $csv);
    }

    public function test_respects_the_filter(): void
    {
        $this->logs->append(new AuditLog(action: 'invoice.issued', entityType: 'invoice', actorUserId: 7, organizationId: 1, entityId: 5, after: ['x' => 1]));
        $this->logs->append(new AuditLog(action: 'client.created', entityType: 'client', actorUserId: 7, organizationId: 1, entityId: 9, after: ['x' => 2]));

        $csv = $this->useCase->execute(new AuditLogFilter(entityType: 'client'));

        self::assertStringContainsString('client.created', $csv);
        self::assertStringNotContainsString('invoice.issued', $csv);
    }
}
