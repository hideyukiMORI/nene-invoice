<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/items` — creates an item-master row in the caller's organization.
 */
final readonly class CreateItemHandler implements RequestHandlerInterface
{
    public function __construct(
        private CreateItemUseCase $useCase,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $decoded = json_decode((string) $request->getBody(), true);

        if (!is_array($decoded)) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, 'Request body must be a JSON object.');
        }

        $parsed = ItemField::parse($decoded);

        if ($parsed['error'] !== null || $parsed['input'] === null) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, $parsed['error'] ?? 'Invalid item payload.');
        }

        $values = $parsed['input'];

        $item = $this->useCase->execute(AuthContext::userId($request), new CreateItemInput(
            description: $values->description,
            defaultUnitPriceCents: $values->defaultUnitPriceCents,
            defaultTaxRateBps: $values->defaultTaxRateBps,
        ));

        return $this->json->create(ItemResponse::toArray($item), 201);
    }
}
