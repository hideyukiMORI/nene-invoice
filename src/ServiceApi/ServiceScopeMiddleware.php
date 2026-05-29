<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceApi;

use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Enforces service-token scopes on `/api/*` (ADR 0009). Runs after the framework
 * `BearerTokenMiddleware` (which already 401s a missing/invalid token on the
 * protected `/api/` prefix). Here we additionally require the token to be a
 * **service principal** carrying the required scope — a human/operator token
 * (no `scopes` claim) is rejected from the service surface.
 */
final readonly class ServiceScopeMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $required = ServiceScopeResolver::resolve($request->getUri()->getPath() ?: '/', $request->getMethod());

        if ($required === null) {
            return $handler->handle($request);
        }

        if (!ServiceAuthContext::isServicePrincipal($request) || !ServiceAuthContext::hasScope($request, $required)) {
            return $this->problemDetails->create(
                $request,
                'insufficient-scope',
                'Forbidden',
                403,
                'The service token lacks the required scope for this operation.',
            );
        }

        if (ServiceAuthContext::organizationId($request) === null) {
            return $this->problemDetails->create(
                $request,
                'insufficient-scope',
                'Forbidden',
                403,
                'The service token is not scoped to an organization.',
            );
        }

        return $handler->handle($request);
    }
}
