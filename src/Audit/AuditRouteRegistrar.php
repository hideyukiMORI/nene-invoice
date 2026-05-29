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
    ) {
    }

    public function __invoke(Router $router): void
    {
        $list = $this->listHandler;

        $router->get('/admin/audit-logs', static fn (ServerRequestInterface $r) => $list->handle($r));
    }
}
