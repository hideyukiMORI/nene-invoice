<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use NeneInvoice\Invoice\InvoiceResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/invoices/{id}/payments` — records a payment against an issued
 * invoice and returns the payment plus the invoice in its resulting state.
 */
final readonly class RecordPaymentHandler implements RequestHandlerInterface
{
    public function __construct(
        private RecordPaymentUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $invoiceId = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $decoded = json_decode((string) $request->getBody(), true);
        $decoded = is_array($decoded) ? $decoded : [];

        $amountValue = $decoded['amount_cents'] ?? null;
        $amountCents = is_int($amountValue) ? $amountValue : (is_numeric($amountValue) ? (int) $amountValue : 0);

        $paidAtValue = $decoded['paid_at'] ?? null;
        $paidAt = is_string($paidAtValue) && $paidAtValue !== '' ? $paidAtValue : null;

        $methodValue = $decoded['method'] ?? null;
        $method = is_string($methodValue) && $methodValue !== '' ? $methodValue : null;

        $noteValue = $decoded['note'] ?? null;
        $note = is_string($noteValue) && $noteValue !== '' ? $noteValue : null;

        // Optional idempotency key: a retried submission with the same key returns
        // the original payment instead of double-recording it (diagnostic R2-3).
        $keyValue = $decoded['idempotency_key'] ?? null;
        $idempotencyKey = is_string($keyValue) && $keyValue !== '' ? $keyValue : null;

        $result = $this->useCase->execute(
            AuthContext::userId($request),
            $invoiceId,
            new RecordPaymentInput($amountCents, $paidAt, $method, $note, idempotencyKey: $idempotencyKey),
        );

        return $this->json->create([
            'payment' => PaymentResponse::toArray($result->payment),
            'invoice' => InvoiceResponse::toArray($result->invoice),
            'total_paid_cents' => $result->totalPaidCents,
        ], 201);
    }
}
