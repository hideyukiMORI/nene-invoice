<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Auth\AuthContext;
use NeneInvoice\Auth\Role;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/users` — admin creates a user in their own organization.
 */
final readonly class CreateUserHandler implements RequestHandlerInterface
{
    public function __construct(
        private CreateUserUseCase $useCase,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request);

        if ($organizationId === null) {
            return $this->problemDetails->create($request, 'organization-not-resolved', 'Organization Required', 400, 'This action requires an organization context.');
        }

        $decoded = json_decode((string) $request->getBody(), true);

        if (!is_array($decoded)) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, 'Request body must be a JSON object.');
        }

        $email = $decoded['email'] ?? null;
        $password = $decoded['password'] ?? null;
        $roleValue = $decoded['role'] ?? null;

        if (!is_string($email) || $email === '' || !is_string($password) || $password === '') {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, 'Both "email" and "password" are required.');
        }

        $role = is_string($roleValue) ? Role::tryFrom($roleValue) : null;

        if ($role === null) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, 'A valid "role" is required.');
        }

        $user = $this->useCase->execute($organizationId, new CreateUserInput($email, $password, $role));

        return $this->json->create(UserResponse::toArray($user), 201);
    }
}
