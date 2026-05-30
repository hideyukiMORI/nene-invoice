<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/invoices/{id}/payments` — lists the payments recorded against an
 * invoice in the resolved organization (scoped by the repository via the holder).
 */
final readonly class ListPaymentsHandler implements RequestHandlerInterface
{
    public function __construct(
        private ListPaymentsUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $invoiceId = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $payments = $this->useCase->execute($invoiceId);

        return $this->json->create([
            'items' => array_map(static fn (Payment $p): array => PaymentResponse::toArray($p), $payments),
            'total_paid_cents' => array_sum(array_map(static fn (Payment $p): int => $p->amountCents, $payments)),
        ]);
    }
}
