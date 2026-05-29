<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers quote routes. Reads require `view_billing`, mutations require
 * `manage_billing` (CapabilityMiddleware); all scoped to the caller's organization.
 */
final readonly class QuoteRouteRegistrar
{
    public function __construct(
        private ListQuotesHandler $listHandler,
        private GetQuoteByIdHandler $getHandler,
        private CreateQuoteHandler $createHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $list = $this->listHandler;
        $get = $this->getHandler;
        $create = $this->createHandler;

        $router->get('/admin/quotes', static fn (ServerRequestInterface $r) => $list->handle($r));
        $router->post('/admin/quotes', static fn (ServerRequestInterface $r) => $create->handle($r));
        $router->get('/admin/quotes/{id}', static fn (ServerRequestInterface $r) => $get->handle($r));
    }
}
