<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Cross-checks the authenticated user's organization against the organization
 * resolved from the request URL (ADR 0006). Runs after BearerTokenMiddleware
 * (so the token claims are available) and after OrgResolverMiddleware (so the
 * resolved org id is on the request attribute `nene2.org.id`).
 *
 * A member of org A must not operate on org B's URL even with a valid token.
 * Superadmin (token `org` claim null) is exempt — it manages organizations
 * cross-tenant. Routes that bypass org resolution (no `nene2.org.id`) pass
 * through; their isolation is enforced elsewhere (service scope / public token).
 */
final readonly class OrgGuardMiddleware implements MiddlewareInterface
{
    private const ORG_ID_ATTRIBUTE = 'nene2.org.id';
    private const CLAIMS_ATTRIBUTE = 'nene2.auth.claims';

    public function __construct(private ProblemDetailsResponseFactory $problemDetails)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $resolvedOrgId = $request->getAttribute(self::ORG_ID_ATTRIBUTE);

        // No resolved org (bypass route) → nothing to guard here.
        if (!is_int($resolvedOrgId)) {
            return $handler->handle($request);
        }

        $claims = $request->getAttribute(self::CLAIMS_ATTRIBUTE);

        // No verified claims (public route) → let auth/capability decide.
        if (!is_array($claims)) {
            return $handler->handle($request);
        }

        $tokenOrg = $claims['org'] ?? null;

        // Superadmin (org null) operates cross-tenant.
        if ($tokenOrg === null) {
            return $handler->handle($request);
        }

        if (!is_int($tokenOrg) || $tokenOrg !== $resolvedOrgId) {
            return $this->problemDetails->create(
                $request,
                'organization-mismatch',
                'Forbidden',
                403,
                'Your account does not belong to this organization.',
            );
        }

        return $handler->handle($request);
    }
}
