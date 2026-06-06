<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

use Nene2\Http\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/line-items/suggestions` — history-based line-item suggestions for
 * the resolved organization (#315). The full set is returned for client-side
 * typeahead filtering, mirroring how the client picker loads its candidates.
 * Capability: ViewBilling (via the `/admin/line-items` prefix).
 */
final readonly class ListLineItemSuggestionsHandler implements RequestHandlerInterface
{
    public function __construct(
        private ListLineItemSuggestionsUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $suggestions = $this->useCase->execute();

        return $this->json->create([
            'items' => array_map(
                static fn (LineItemSuggestion $s): array => LineItemSuggestionResponse::toArray($s),
                $suggestions,
            ),
        ]);
    }
}
