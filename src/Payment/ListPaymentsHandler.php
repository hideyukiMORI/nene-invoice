<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/invoices/{id}/payments` — lists the payments recorded against an
 * invoice in the caller's organization.
 */
final readonly class ListPaymentsHandler implements RequestHandlerInterface
{
    public function __construct(
        private ListPaymentsUseCase $useCase,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request);

        if ($organizationId === null) {
            return $this->problemDetails->create($request, 'organization-not-resolved', 'Organization Required', 400, 'This action requires an organization context.');
        }

        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $invoiceId = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $payments = $this->useCase->execute($organizationId, $invoiceId);

        return $this->json->create([
            'items' => array_map(static fn (Payment $p): array => PaymentResponse::toArray($p), $payments),
            'total_paid_cents' => array_sum(array_map(static fn (Payment $p): int => $p->amountCents, $payments)),
        ]);
    }
}
