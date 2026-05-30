<?php

declare(strict_types=1);

namespace NeneInvoice\Dashboard;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

final readonly class DashboardRouteRegistrar
{
    public function __construct(private GetDashboardHandler $handler)
    {
    }

    public function __invoke(Router $router): void
    {
        $handler = $this->handler;
        $router->get('/admin/dashboard', static fn (ServerRequestInterface $r) => $handler->handle($r));
    }
}
