<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use Nene2\Http\JsonRequestBodyParser;
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
        private CreateItemUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $values = ItemField::parse(JsonRequestBodyParser::parse($request));

        $item = $this->useCase->execute(AuthContext::userId($request), new CreateItemInput(
            description: $values->description,
            defaultUnitPriceCents: $values->defaultUnitPriceCents,
            defaultTaxRateBps: $values->defaultTaxRateBps,
        ));

        return $this->json->create(ItemResponse::toArray($item), 201);
    }
}
