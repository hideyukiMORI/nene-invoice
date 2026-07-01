<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

use Nene2\Http\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/bank-transactions/{id}/suggestions` — ranked invoice candidates a
 * staged deposit might settle (score + reasons + display fields). Read-only; only
 * credit lines yield suggestions. Requires ViewBilling.
 */
final readonly class BankTransactionSuggestionsHandler implements RequestHandlerInterface
{
    public function __construct(
        private SuggestMatchesUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $suggestions = $this->useCase->execute(BankTransactionRequest::pathId($request));

        return $this->json->create([
            'items' => array_map(
                static fn (SuggestedMatch $s): array => BankTransactionResponse::suggestionToArray($s),
                $suggestions,
            ),
        ]);
    }
}
