<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/bank-transactions` — lists staged bank lines in the resolved
 * organization, newest first, optionally filtered by `?status=`
 * (unmatched / matched / posted / ignored). Requires ViewBilling.
 */
final readonly class ListBankTransactionsHandler implements RequestHandlerInterface
{
    public function __construct(
        private ListBankTransactionsUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $status     = BankTransactionRequest::statusFilter($request);
        $pagination = PaginationQueryParser::parse($request);

        $result = $this->useCase->execute($status, $pagination->limit, $pagination->offset);

        return $this->json->create((new PaginationResponse(
            items: array_map(
                static fn (BankTransaction $t): array => BankTransactionResponse::toArray($t),
                $result['items'],
            ),
            limit: $pagination->limit,
            offset: $pagination->offset,
            total: $result['total'],
        ))->toArray());
    }
}
