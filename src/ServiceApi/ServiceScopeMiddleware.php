<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceApi;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ServiceToken\ServiceTokenAuthorizerInterface;
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
 *
 * `/api/*` bypasses OrgResolverMiddleware (org comes from the service token, not
 * the URL), so this middleware sets the request-scoped org holder from the
 * token's `org` claim — the same holder org-scoped repositories read (ADR 0006).
 *
 * It also enforces **revocation**: a token carrying a `jti` is rejected once its
 * registry row is revoked (or missing). Legacy tokens without a `jti` predate the
 * registry and rely on the JWT signature + `exp` only.
 */
final readonly class ServiceScopeMiddleware implements MiddlewareInterface
{
    /**
     * @param RequestScopedHolder<int> $orgId
     */
    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
        private RequestScopedHolder $orgId,
        private ServiceTokenAuthorizerInterface $authorizer,
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

        $organizationId = ServiceAuthContext::organizationId($request);

        if ($organizationId === null) {
            return $this->problemDetails->create(
                $request,
                'insufficient-scope',
                'Forbidden',
                403,
                'The service token is not scoped to an organization.',
            );
        }

        // Revocation: a registered token (carrying a jti) must still be active.
        // Legacy tokens without a jti predate the registry and are not checked here.
        $jti = ServiceAuthContext::tokenId($request);

        if ($jti !== null && !$this->authorizer->isActive($jti)) {
            return $this->problemDetails->create(
                $request,
                'service-token-revoked',
                'Unauthorized',
                401,
                'The service token has been revoked.',
            );
        }

        // Make the service token's org available to org-scoped repositories.
        $this->orgId->set($organizationId);

        return $handler->handle($request);
    }
}
