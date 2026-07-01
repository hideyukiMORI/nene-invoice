<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

use Nene2\Http\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/bank-transactions/import` — imports a bank statement CSV uploaded
 * as the raw `text/csv` request body (raw bytes preserve Shift_JIS for the
 * encoding auto-detector). `?preset=` selects the column mapping. Staging only —
 * it records no payment. A rejected file (format error) returns 422; otherwise
 * 200 with per-row counts. Requires ManageBilling.
 */
final readonly class ImportBankTransactionsHandler implements RequestHandlerInterface
{
    public function __construct(
        private ImportBankTransactionsUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $raw     = (string) $request->getBody();
        $mapping = BankTransactionRequest::mappingFromPreset($request);

        $result = $this->useCase->execute($raw, $mapping);

        return $this->json->create(
            BankTransactionResponse::importResultToArray($result),
            $result->formatError === null ? 200 : 422,
        );
    }
}
