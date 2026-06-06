<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `PATCH /admin/items/{id}` — updates an item-master row in the caller's
 * organization.
 */
final readonly class UpdateItemHandler implements RequestHandlerInterface
{
    public function __construct(
        private UpdateItemUseCase $useCase,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $decoded = json_decode((string) $request->getBody(), true);

        if (!is_array($decoded)) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, 'Request body must be a JSON object.');
        }

        $parsed = ItemField::parse($decoded);

        if ($parsed['error'] !== null || $parsed['input'] === null) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, $parsed['error'] ?? 'Invalid item payload.');
        }

        $values = $parsed['input'];

        $item = $this->useCase->execute(AuthContext::userId($request), $id, new UpdateItemInput(
            description: $values->description,
            defaultUnitPriceCents: $values->defaultUnitPriceCents,
            defaultTaxRateBps: $values->defaultTaxRateBps,
        ));

        return $this->json->create(ItemResponse::toArray($item));
    }
}
