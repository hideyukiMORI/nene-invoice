<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/bank-transactions/{id}/confirm` — confirms an operator's match of a
 * staged deposit to an invoice (body: `invoice_id`) and records the payment,
 * reusing the tax-signed-off RecordPayment path. Over-payment is rejected
 * (`payment-exceeds-outstanding`). Requires ManageBilling.
 */
final readonly class ConfirmBankTransactionMatchHandler implements RequestHandlerInterface
{
    public function __construct(
        private ConfirmMatchUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body      = JsonRequestBodyParser::parse($request);
        $invoiceId = BankTransactionRequest::requireInvoiceId($body);

        $result = $this->useCase->execute(
            AuthContext::userId($request),
            BankTransactionRequest::pathId($request),
            $invoiceId,
        );

        return $this->json->create(BankTransactionResponse::confirmResultToArray($result));
    }
}
