<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `DELETE /admin/clients/{id}` — soft-deletes a client in the resolved
 * organization (scoped by the repository via the request-scoped org holder).
 */
final readonly class DeleteClientHandler implements RequestHandlerInterface
{
    public function __construct(
        private DeleteClientUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $this->useCase->execute(AuthContext::userId($request), $id);

        return $this->json->createEmpty(204);
    }
}
