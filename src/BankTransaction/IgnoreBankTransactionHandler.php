<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

use Nene2\Http\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/bank-transactions/{id}/ignore` — dismisses a staged line the
 * operator will not reconcile (fees, non-AR transfers, duplicates). A line whose
 * payment is already posted cannot be ignored. Requires ManageBilling.
 */
final readonly class IgnoreBankTransactionHandler implements RequestHandlerInterface
{
    public function __construct(
        private IgnoreBankTransactionUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $transaction = $this->useCase->execute(BankTransactionRequest::pathId($request));

        return $this->json->create(BankTransactionResponse::toArray($transaction));
    }
}
