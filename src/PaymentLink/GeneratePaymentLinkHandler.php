<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/invoices/{id}/payment-links`
 * Issues a time-limited payment link for the invoice (auto-revoking any prior
 * active link). Capability: ManageBilling (POST on /admin/invoices/*, via
 * CapabilityResolver). The organization is resolved upstream into the holder.
 */
final readonly class GeneratePaymentLinkHandler implements RequestHandlerInterface
{
    public function __construct(
        private GeneratePaymentLinkUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params    = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $invoiceId = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $result = $this->useCase->execute(AuthContext::userId($request), $invoiceId);

        return $this->json->create([
            'payment_link_id' => $result['paymentLinkId'],
            'url'             => '/pay/' . $result['rawToken'],
            'expires_at'      => $result['expiresAt'],
        ], 201);
    }
}
