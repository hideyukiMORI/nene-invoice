<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /auth/login` — public. Exchanges email + password for a bearer token.
 */
final readonly class LoginHandler implements RequestHandlerInterface
{
    public function __construct(
        private LoginUseCaseInterface $useCase,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $decoded = json_decode((string) $request->getBody(), true);

        if (!is_array($decoded)) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, 'Request body must be a JSON object.');
        }

        $email = $decoded['email'] ?? null;
        $password = $decoded['password'] ?? null;

        if (!is_string($email) || $email === '' || !is_string($password) || $password === '') {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, 'Both "email" and "password" are required.');
        }

        try {
            $output = $this->useCase->execute(new LoginInput($email, $password));
        } catch (InvalidCredentialsException $e) {
            return $this->problemDetails->create($request, 'invalid-credentials', 'Invalid Credentials', 401, $e->getMessage());
        }

        return $this->json->create(['token' => $output->token]);
    }
}
