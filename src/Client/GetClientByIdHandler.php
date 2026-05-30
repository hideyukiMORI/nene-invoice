<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/clients/{id}` — reads one client in the resolved organization.
 * The org is set by OrgResolverMiddleware and enforced in the repository, so
 * clients outside the caller's organization (or missing) return 404.
 */
final readonly class GetClientByIdHandler implements RequestHandlerInterface
{
    public function __construct(
        private GetClientByIdUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $client = $this->useCase->execute($id);

        return $this->json->create(ClientResponse::toArray($client));
    }
}
