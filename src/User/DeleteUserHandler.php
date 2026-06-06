<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `DELETE /admin/users/{id}` — admin removes a user in their own organization.
 * Callers cannot delete their own account.
 */
final readonly class DeleteUserHandler implements RequestHandlerInterface
{
    public function __construct(
        private DeleteUserUseCaseInterface $useCase,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $callerUserId = AuthContext::userId($request);

        if ($callerUserId === null) {
            return $this->problemDetails->create($request, 'organization-not-resolved', 'Organization Required', 400, 'This action requires an organization context.');
        }

        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $this->useCase->execute($callerUserId, $id);

        return $this->json->createEmpty(204);
    }
}
