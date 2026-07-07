<?php

declare(strict_types=1);

namespace NeneInvoice\Demo;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers the disposable-demo route. Invoked with the {@see Router} during
 * runtime assembly (see {@see \NeneInvoice\ApplicationServiceProvider}).
 *
 * `GET /demo/{template}` is public and org-less (it *creates* orgs); it is gated
 * at runtime by `DEMO_MODE=1` inside the handler and bypasses org resolution
 * ({@see \NeneInvoice\Organization\Resolution\OrgResolverMiddleware}).
 */
final readonly class DemoRouteRegistrar
{
    public function __construct(private StartDemoHandler $handler)
    {
    }

    public function __invoke(Router $router): void
    {
        $handler = $this->handler;
        $router->get('/demo/{template}', static fn (ServerRequestInterface $r) => $handler->handle($r));
    }
}
