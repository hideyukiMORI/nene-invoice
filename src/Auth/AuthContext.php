<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Reads the authenticated principal from the verified token claims that the
 * framework `BearerTokenMiddleware` stores on the request (`nene2.auth.claims`).
 *
 * The token is issued by {@see LoginUseCase} with claims `sub` (user id),
 * `role`, and `org` (organization id; null for superadmin).
 */
final class AuthContext
{
    private const CLAIMS_ATTRIBUTE = 'nene2.auth.claims';

    public static function userId(ServerRequestInterface $request): ?int
    {
        $value = self::claim($request, 'sub');

        return is_int($value) ? $value : null;
    }

    public static function role(ServerRequestInterface $request): ?Role
    {
        $value = self::claim($request, 'role');

        return is_string($value) ? Role::tryFrom($value) : null;
    }

    public static function organizationId(ServerRequestInterface $request): ?int
    {
        $value = self::claim($request, 'org');

        return is_int($value) ? $value : null;
    }

    private static function claim(ServerRequestInterface $request, string $key): mixed
    {
        $claims = $request->getAttribute(self::CLAIMS_ATTRIBUTE);

        return is_array($claims) ? ($claims[$key] ?? null) : null;
    }
}
