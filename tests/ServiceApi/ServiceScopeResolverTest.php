<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\ServiceApi;

use NeneInvoice\ServiceApi\ServiceScope;
use NeneInvoice\ServiceApi\ServiceScopeResolver;
use PHPUnit\Framework\TestCase;

final class ServiceScopeResolverTest extends TestCase
{
    public function test_invoice_reads_require_read_scope(): void
    {
        self::assertSame(ServiceScope::ReadInvoices, ServiceScopeResolver::resolve('/api/invoices', 'GET'));
        self::assertSame(ServiceScope::ReadInvoices, ServiceScopeResolver::resolve('/api/invoices/5', 'GET'));
    }

    public function test_payment_writes_require_write_scope(): void
    {
        self::assertSame(
            ServiceScope::WritePayments,
            ServiceScopeResolver::resolve('/api/invoices/5/payments', 'POST'),
        );
    }

    public function test_non_api_paths_need_no_service_scope(): void
    {
        self::assertNull(ServiceScopeResolver::resolve('/admin/invoices', 'GET'));
        self::assertNull(ServiceScopeResolver::resolve('/health', 'GET'));
    }
}
