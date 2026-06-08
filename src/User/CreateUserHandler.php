<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
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
        private CreateUserUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = JsonRequestBodyParser::parse($request);

        $email = $body['email'] ?? null;
        if (!is_string($email) || $email === '') {
            throw new ValidationException([new ValidationError('body.email', 'Email is required.', 'required')]);
        }

        $password = $body['password'] ?? null;
        if (!is_string($password) || $password === '') {
            throw new ValidationException([new ValidationError('body.password', 'Password is required.', 'required')]);
        }
        PasswordPolicy::assert($password);

        $roleValue = $body['role'] ?? null;
        $role = is_string($roleValue) ? Role::tryFrom($roleValue) : null;
        if ($role === null) {
            throw new ValidationException([new ValidationError('body.role', 'A valid role is required.', 'invalid')]);
        }

        $user = $this->useCase->execute(AuthContext::userId($request), new CreateUserInput($email, $password, $role));

        return $this->json->create(UserResponse::toArray($user), 201);
    }
}
