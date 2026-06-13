<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

final readonly class PaymentLinkRouteRegistrar
{
    public function __construct(
        private GeneratePaymentLinkHandler $generateHandler,
        private RevokePaymentLinkHandler $revokeHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $generate = $this->generateHandler;
        $revoke   = $this->revokeHandler;

        $router->post('/admin/invoices/{id}/payment-links', static fn (ServerRequestInterface $r) => $generate->handle($r));
        $router->post('/admin/payment-links/{id}/revoke', static fn (ServerRequestInterface $r) => $revoke->handle($r));
    }
}
