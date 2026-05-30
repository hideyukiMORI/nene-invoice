<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use NeneInvoice\Auth\Role;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `PATCH /admin/users/{id}` — admin updates a user's role / status / password
 * within their own organization.
 */
final readonly class UpdateUserHandler implements RequestHandlerInterface
{
    private const ALLOWED_STATUSES = ['active', 'invited'];

    public function __construct(
        private UpdateUserUseCase $useCase,
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

        $roleValue = $decoded['role'] ?? null;
        $status = $decoded['status'] ?? null;

        $role = is_string($roleValue) ? Role::tryFrom($roleValue) : null;

        if ($role === null) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, 'A valid "role" is required.');
        }

        if (!is_string($status) || !in_array($status, self::ALLOWED_STATUSES, true)) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, '"status" must be one of: active, invited.');
        }

        $passwordValue = $decoded['password'] ?? null;
        $password = is_string($passwordValue) && $passwordValue !== '' ? $passwordValue : null;

        $user = $this->useCase->execute(AuthContext::userId($request), $id, new UpdateUserInput($role, $status, $password));

        return $this->json->create(UserResponse::toArray($user));
    }
}
