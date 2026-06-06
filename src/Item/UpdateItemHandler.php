<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use Nene2\Http\JsonRequestBodyParser;
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
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $values = ItemField::parse(JsonRequestBodyParser::parse($request));

        $item = $this->useCase->execute(AuthContext::userId($request), $id, new UpdateItemInput(
            description: $values->description,
            defaultUnitPriceCents: $values->defaultUnitPriceCents,
            defaultTaxRateBps: $values->defaultTaxRateBps,
        ));

        return $this->json->create(ItemResponse::toArray($item));
    }
}
