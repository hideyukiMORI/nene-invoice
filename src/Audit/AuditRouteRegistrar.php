<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers the audit-log read route. Requires `manage_users` (admin / superadmin)
 * via CapabilityResolver; scoped to the caller's organization.
 */
final readonly class AuditRouteRegistrar
{
    public function __construct(
        private ListAuditLogsHandler $listHandler,
        private ExportAuditLogsCsvHandler $exportHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $list   = $this->listHandler;
        $export = $this->exportHandler;

        // Static 'export' segment is registered before the bare list route.
        $router->get('/admin/audit-logs/export', static fn (ServerRequestInterface $r) => $export->handle($r));
        $router->get('/admin/audit-logs', static fn (ServerRequestInterface $r) => $list->handle($r));
    }
}
