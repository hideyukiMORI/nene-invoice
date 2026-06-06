<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceApi;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneInvoice\Payment\RecordPaymentInput;
use NeneInvoice\Payment\RecordPaymentUseCase;
use NeneInvoice\Support\RequestField;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /api/invoices/{id}/payments` — records a payment from NeNe Clear on a
 * confirmed bank match (ADR 0009 §3.1). Idempotent on `idempotency_key`; honors
 * `paid_at` as the bank value date; stores `external_reference`; over-allocation
 * surfaces as `422 payment-exceeds-outstanding` (handled centrally). Requires the
 * `write:payments` scope (ServiceScopeMiddleware).
 */
final readonly class RecordServicePaymentHandler implements RequestHandlerInterface
{
    public function __construct(
        private RecordPaymentUseCase $useCase,
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

        $decoded = JsonRequestBodyParser::parse($request);

        $amountValue = $decoded['amount_cents'] ?? null;
        $amountCents = is_int($amountValue) ? $amountValue : (is_numeric($amountValue) ? (int) $amountValue : 0);

        $paidAt = RequestField::optionalString($decoded, 'paid_at');
        if ($paidAt === null) {
            throw new ValidationException([new ValidationError('body.paid_at', 'paid_at (bank value date) is required.', 'required')]);
        }

        $idempotencyKey = RequestField::optionalString($decoded, 'idempotency_key');
        if ($idempotencyKey === null) {
            throw new ValidationException([new ValidationError('body.idempotency_key', 'idempotency_key is required.', 'required')]);
        }

        $result = $this->useCase->execute(null, $invoiceId, new RecordPaymentInput(
            amountCents: $amountCents,
            paidAt: $paidAt,
            method: RequestField::optionalString($decoded, 'method'),
            note: RequestField::optionalString($decoded, 'note'),
            externalReference: RequestField::optionalString($decoded, 'external_reference'),
            idempotencyKey: $idempotencyKey,
        ));

        $outstanding = max(0, $result->invoice->totalCents - $result->totalPaidCents);

        return $this->json->create([
            'payment' => [
                'payment_id' => $result->payment->id,
                'amount_cents' => $result->payment->amountCents,
                'paid_at' => $result->payment->paidAt,
                'method' => $result->payment->method,
                'external_reference' => $result->payment->externalReference,
            ],
            'invoice' => ServiceInvoiceResponse::listItem($result->invoice, $outstanding),
            'total_paid_cents' => $result->totalPaidCents,
        ], 201);
    }
}
