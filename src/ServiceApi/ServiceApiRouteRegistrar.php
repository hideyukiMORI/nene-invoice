<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceApi;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers the NeNe Clear service surface under `/api/*` (ADR 0009). Auth is the
 * service token (`read:invoices` scope) enforced by ServiceScopeMiddleware;
 * org scoping comes from the token. Read-only for now (write API is a follow-up).
 */
final readonly class ServiceApiRouteRegistrar
{
    public function __construct(
        private ListServiceInvoicesHandler $listHandler,
        private GetServiceInvoiceHandler $getHandler,
        private RecordServicePaymentHandler $recordPaymentHandler,
        private VoidServicePaymentHandler $voidPaymentHandler,
        private GetServiceClientHandler $getClientHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $list = $this->listHandler;
        $get = $this->getHandler;
        $recordPayment = $this->recordPaymentHandler;
        $voidPayment = $this->voidPaymentHandler;
        $getClient = $this->getClientHandler;

        $router->get('/api/invoices', static fn (ServerRequestInterface $r) => $list->handle($r));
        $router->get('/api/invoices/{id}', static fn (ServerRequestInterface $r) => $get->handle($r));
        $router->post('/api/invoices/{id}/payments', static fn (ServerRequestInterface $r) => $recordPayment->handle($r));
        $router->post('/api/invoices/{id}/payments/{paymentId}/void', static fn (ServerRequestInterface $r) => $voidPayment->handle($r));
        $router->get('/api/clients/{id}', static fn (ServerRequestInterface $r) => $getClient->handle($r));
    }
}
