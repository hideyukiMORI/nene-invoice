<?php

declare(strict_types=1);

namespace NeneInvoice\Demo;

use Nene2\Demo\StartDisposableDemoHandler;
use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers the disposable-demo route, wrapping the framework handler with
 * browser content negotiation ({@see DemoBrowserErrorPage}, #612). The
 * framework's own {@see \Nene2\Demo\DemoRouteRegistrar} is not used because it
 * is typed to the bare handler; everything else (gate, throttle, capacity,
 * provisioning) stays in {@see StartDisposableDemoHandler}.
 *
 * `GET /demo/{template}` is public and org-less (it *creates* orgs); it is gated
 * at runtime by `DEMO_MODE` inside the handler and bypasses org resolution
 * ({@see \NeneInvoice\Organization\Resolution\OrgResolverMiddleware}).
 */
final readonly class DemoRouteRegistrar
{
    public function __construct(
        private StartDisposableDemoHandler $handler,
        private DemoBrowserErrorPage $errorPage,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $handler = $this->handler;
        $errorPage = $this->errorPage;

        $router->get(
            '/demo/{' . StartDisposableDemoHandler::TEMPLATE_PARAMETER . '}',
            static fn (ServerRequestInterface $request) => $errorPage->apply($request, $handler->handle($request)),
        );
    }
}
