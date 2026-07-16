<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneInvoice\Http\BasePath;
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

        $base = BasePath::appBaseFromRequest($request);

        return $this->withSessionCookies($this->json->create(['token' => $output->token]), $output->refreshToken, $base);
    }

    /**
     * Seats the rotated refresh token in its httpOnly cookie and issues a fresh
     * double-submit CSRF cookie (readable by JS) alongside it (ADR 0014). Cookie
     * paths are scoped to the install base (ADR 0015).
     */
    private function withSessionCookies(ResponseInterface $response, IssuedRefreshToken $refreshToken, string $base): ResponseInterface
    {
        $csrfToken = RefreshTokenSecret::generateCsrfToken();

        return $response
            ->withAddedHeader('Set-Cookie', SessionCookies::setRefresh($refreshToken->rawToken, $refreshToken->expiresAtTimestamp, $base))
            ->withAddedHeader('Set-Cookie', SessionCookies::setCsrf($csrfToken, $refreshToken->expiresAtTimestamp, $base));
    }
}
