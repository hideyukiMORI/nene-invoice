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
 * `POST /auth/logout` — public (cookie-authenticated). Revokes the refresh-token
 * family server-side and clears the cookies (ADR 0014). Idempotent: it returns
 * 204 and clears the cookies even when no/invalid cookie is presented, so the
 * client can always reach a signed-out state.
 */
final readonly class LogoutHandler implements RequestHandlerInterface
{
    public function __construct(
        private LogoutUseCaseInterface $useCase,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            CsrfGuard::assert($request);
        } catch (CsrfValidationException $e) {
            return $this->problemDetails->create($request, 'csrf-token-invalid', 'Forbidden', 403, $e->getMessage());
        }

        $this->useCase->execute(SessionCookies::refreshToken($request));
        $base = BasePath::appBaseFromRequest($request);

        return $this->json->createEmpty(204)
            ->withAddedHeader('Set-Cookie', SessionCookies::clearRefresh($base))
            ->withAddedHeader('Set-Cookie', SessionCookies::clearCsrf($base));
    }
}
