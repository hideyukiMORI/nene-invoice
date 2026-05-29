<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Enforces role-based capabilities on authenticated requests (ADR 0006).
 *
 * Runs after the framework `BearerTokenMiddleware`. Requests without verified
 * claims (public routes) and routes that need no specific capability pass
 * through. Organization scoping is added with the org-resolution middleware in a
 * later PR.
 */
final readonly class CapabilityMiddleware implements MiddlewareInterface
{
    private const CLAIMS_ATTRIBUTE = 'nene2.auth.claims';

    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $claims = $request->getAttribute(self::CLAIMS_ATTRIBUTE);

        if (!is_array($claims)) {
            return $handler->handle($request);
        }

        $required = CapabilityResolver::resolve($request->getUri()->getPath() ?: '/', $request->getMethod());

        if ($required === null) {
            return $handler->handle($request);
        }

        $roleValue = $claims['role'] ?? null;
        $role = is_string($roleValue) ? Role::tryFrom($roleValue) : null;

        if ($role === null || !$role->hasCapability($required)) {
            return $this->problemDetails->create(
                $request,
                'insufficient-capability',
                'Forbidden',
                403,
                'You do not have permission to perform this action.',
            );
        }

        return $handler->handle($request);
    }
}
