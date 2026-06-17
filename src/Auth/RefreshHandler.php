<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Http\BasePath;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /auth/refresh` — public (cookie-authenticated). Exchanges the httpOnly
 * refresh cookie for a fresh in-memory access token and rotates the refresh
 * token (ADR 0014). Failure fails closed: 401 and the cookies are cleared, so
 * the SPA falls back to the login screen exactly as before.
 */
final readonly class RefreshHandler implements RequestHandlerInterface
{
    public function __construct(
        private RefreshSessionUseCaseInterface $useCase,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $rawToken = SessionCookies::refreshToken($request);

        // No cookie → simply not signed in. Fail closed without disclosing more.
        if ($rawToken === null) {
            return $this->failClosed($request);
        }

        try {
            CsrfGuard::assert($request);
        } catch (CsrfValidationException $e) {
            return $this->problemDetails->create($request, 'csrf-token-invalid', 'Forbidden', 403, $e->getMessage());
        }

        try {
            $session = $this->useCase->execute($rawToken);
        } catch (InvalidRefreshTokenException | RefreshTokenReuseException $e) {
            return $this->failClosed($request);
        }

        $base = BasePath::fromRequest($request);
        $csrfToken = RefreshTokenSecret::generateCsrfToken();

        return $this->json->create(['token' => $session->accessToken])
            ->withAddedHeader('Set-Cookie', SessionCookies::setRefresh($session->refreshToken->rawToken, $session->refreshToken->expiresAtTimestamp, $base))
            ->withAddedHeader('Set-Cookie', SessionCookies::setCsrf($csrfToken, $session->refreshToken->expiresAtTimestamp, $base));
    }

    private function failClosed(ServerRequestInterface $request): ResponseInterface
    {
        $base = BasePath::fromRequest($request);

        return $this->problemDetails
            ->create($request, 'invalid-refresh-token', 'Unauthorized', 401, 'The session could not be refreshed.')
            ->withAddedHeader('Set-Cookie', SessionCookies::clearRefresh($base))
            ->withAddedHeader('Set-Cookie', SessionCookies::clearCsrf($base));
    }
}
