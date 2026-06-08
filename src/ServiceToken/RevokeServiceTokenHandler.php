<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `DELETE /admin/service-tokens/{id}` — revokes a service token in the caller's
 * organization. Idempotent: re-revoking an already-revoked token still 204s.
 * Requires ManageUsers.
 */
final readonly class RevokeServiceTokenHandler implements RequestHandlerInterface
{
    public function __construct(
        private RevokeServiceTokenUseCaseInterface $useCase,
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
