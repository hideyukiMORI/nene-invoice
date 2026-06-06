<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
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
        $body = JsonRequestBodyParser::parse($request);

        $email = $body['email'] ?? null;
        if (!is_string($email) || $email === '') {
            throw new ValidationException([new ValidationError('body.email', 'Email is required.', 'required')]);
        }

        $password = $body['password'] ?? null;
        if (!is_string($password) || $password === '') {
            throw new ValidationException([new ValidationError('body.password', 'Password is required.', 'required')]);
        }

        $serverParams = $request->getServerParams();
        $ip = isset($serverParams['REMOTE_ADDR']) && is_string($serverParams['REMOTE_ADDR'])
            ? $serverParams['REMOTE_ADDR']
            : null;

        try {
            $output = $this->useCase->execute(new LoginInput($email, $password, $ip));
        } catch (TooManyLoginAttemptsException $e) {
            return $this->problemDetails
                ->create($request, 'too-many-requests', 'Too Many Requests', 429, $e->getMessage())
                ->withHeader('Retry-After', (string) $e->retryAfterSeconds);
        } catch (InvalidCredentialsException $e) {
            return $this->problemDetails->create($request, 'invalid-credentials', 'Invalid Credentials', 401, $e->getMessage());
        }

        return $this->json->create(['token' => $output->token]);
    }
}
