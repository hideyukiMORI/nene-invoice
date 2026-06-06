<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/users/{id}` — admin reads one user in their own organization.
 * Users outside the caller's organization (or missing) return 404. The
 * organization is resolved upstream (OrgResolverMiddleware) into the
 * request-scoped holder.
 */
final readonly class GetUserByIdHandler implements RequestHandlerInterface
{
    public function __construct(
        private GetUserByIdUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $user = $this->useCase->execute($id);

        return $this->json->create(UserResponse::toArray($user));
    }
}
