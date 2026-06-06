<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
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
        private UpdateUserUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $body = JsonRequestBodyParser::parse($request);

        $roleValue = $body['role'] ?? null;
        $role = is_string($roleValue) ? Role::tryFrom($roleValue) : null;
        if ($role === null) {
            throw new ValidationException([new ValidationError('body.role', 'A valid role is required.', 'invalid')]);
        }

        $status = $body['status'] ?? null;
        if (!is_string($status) || !in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new ValidationException([new ValidationError('body.status', 'Status must be one of: active, invited.', 'invalid')]);
        }

        $passwordValue = $body['password'] ?? null;
        $password = is_string($passwordValue) && $passwordValue !== '' ? $passwordValue : null;

        $user = $this->useCase->execute(AuthContext::userId($request), $id, new UpdateUserInput($role, $status, $password));

        return $this->json->create(UserResponse::toArray($user));
    }
}
