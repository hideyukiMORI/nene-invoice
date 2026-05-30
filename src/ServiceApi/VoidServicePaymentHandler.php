<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceApi;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Payment\VoidPaymentUseCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /api/invoices/{id}/payments/{paymentId}/void` — reverses a payment on a
 * reconciliation reversal (ADR 0009 §3.2). Void-with-audit (no hard delete);
 * recomputes invoice status; idempotent. Requires the `write:payments` scope.
 */
final readonly class VoidServicePaymentHandler implements RequestHandlerInterface
{
    public function __construct(
        private VoidPaymentUseCase $useCase,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = ServiceAuthContext::organizationId($request);

        if ($organizationId === null) {
            return $this->problemDetails->create($request, 'insufficient-scope', 'Forbidden', 403, 'The service token is not scoped to an organization.');
        }

        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $invoiceId = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;
        $paymentId = is_array($params) && isset($params['paymentId']) ? (int) $params['paymentId'] : 0;

        $decoded = json_decode((string) $request->getBody(), true);
        $reasonValue = is_array($decoded) ? ($decoded['reason'] ?? null) : null;
        $reason = is_string($reasonValue) && $reasonValue !== '' ? $reasonValue : null;

        $result = $this->useCase->execute(null, $invoiceId, $paymentId, $reason);

        $outstanding = max(0, $result->invoice->totalCents - $result->totalPaidCents);

        return $this->json->create([
            'payment' => [
                'payment_id' => $result->payment->id,
                'amount_cents' => $result->payment->amountCents,
                'paid_at' => $result->payment->paidAt,
                'method' => $result->payment->method,
                'external_reference' => $result->payment->externalReference,
                'voided' => true,
            ],
            'invoice' => ServiceInvoiceResponse::listItem($result->invoice, $outstanding),
            'total_paid_cents' => $result->totalPaidCents,
        ]);
    }
}
