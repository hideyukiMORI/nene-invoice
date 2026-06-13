<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

use Nene2\Routing\Router;
use NeneInvoice\Payment\Gateway\PayjpWebhookHandler;
use Psr\Http\Message\ServerRequestInterface;

final readonly class PaymentLinkRouteRegistrar
{
    public function __construct(
        private GeneratePaymentLinkHandler $generateHandler,
        private RevokePaymentLinkHandler $revokeHandler,
        private PayPageHandler $payPageHandler,
        private ChargePaymentLinkHandler $chargeHandler,
        private PayjpWebhookHandler $webhookHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $generate = $this->generateHandler;
        $revoke   = $this->revokeHandler;
        $payPage  = $this->payPageHandler;
        $charge   = $this->chargeHandler;
        $webhook  = $this->webhookHandler;

        // Admin (ManageBilling via CapabilityResolver).
        $router->post('/admin/invoices/{id}/payment-links', static fn (ServerRequestInterface $r) => $generate->handle($r));
        $router->post('/admin/payment-links/{id}/revoke', static fn (ServerRequestInterface $r) => $revoke->handle($r));

        // Public (token-authenticated, no session) — hosted card payment (SAQ-A).
        $router->get('/pay/{token}', static fn (ServerRequestInterface $r) => $payPage->handle($r));
        $router->post('/pay/{token}/charge', static fn (ServerRequestInterface $r) => $charge->handle($r));

        // Public webhook (X-Payjp-Webhook-Token authenticated) — settlement backstop.
        $router->post('/webhooks/payjp', static fn (ServerRequestInterface $r) => $webhook->handle($r));
    }
}
