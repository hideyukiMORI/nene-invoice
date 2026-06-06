<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/items/{id}` — reads one item in the resolved organization. The org
 * is set by OrgResolverMiddleware and enforced in the repository, so items
 * outside the caller's organization (or missing) return 404.
 */
final readonly class GetItemByIdHandler implements RequestHandlerInterface
{
    public function __construct(
        private GetItemByIdUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $item = $this->useCase->execute($id);

        return $this->json->create(ItemResponse::toArray($item));
    }
}
