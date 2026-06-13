<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/payment-links/{id}/revoke`
 * Revokes an active payment link. Capability: ManageBilling (POST on
 * /admin/payment-links/*, via CapabilityResolver). Idempotent: revoking an
 * already-terminal link returns 200; an unknown id (or another org's link)
 * returns 404 `payment-link-not-found`.
 */
final readonly class RevokePaymentLinkHandler implements RequestHandlerInterface
{
    public function __construct(
        private RevokePaymentLinkUseCaseInterface $useCase,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id     = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $outcome = $this->useCase->execute(AuthContext::userId($request), $id);

        if ($outcome === RevokeOutcome::NotFound) {
            return $this->problemDetails->create($request, 'payment-link-not-found', 'Not Found', 404, 'Payment link not found.');
        }

        return $this->json->create([
            'payment_link_id' => $id,
            'status'          => PaymentLinkStatus::Revoked->value,
            'revoked'         => $outcome === RevokeOutcome::Revoked,
        ], 200);
    }
}
