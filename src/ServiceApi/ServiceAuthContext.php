<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceApi;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Reads the **service principal** from the verified token claims set by the
 * framework `BearerTokenMiddleware` (`nene2.auth.claims`).
 *
 * A service token (issued for a sibling like NeNe Clear — ADR 0009) carries:
 *   `sub` (e.g. `service:clear`), `org` (organization scope), and
 *   `scopes` (list of {@see ServiceScope} values).
 */
final class ServiceAuthContext
{
    private const CLAIMS_ATTRIBUTE = 'nene2.auth.claims';

    public static function organizationId(ServerRequestInterface $request): ?int
    {
        $value = self::claim($request, 'org');

        return is_int($value) ? $value : null;
    }

    /** @return list<string> */
    public static function scopes(ServerRequestInterface $request): array
    {
        $value = self::claim($request, 'scopes');

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $s): bool => is_string($s)));
    }

    public static function hasScope(ServerRequestInterface $request, ServiceScope $scope): bool
    {
        return in_array($scope->value, self::scopes($request), true);
    }

    /** A service principal is any token carrying a `scopes` claim. */
    public static function isServicePrincipal(ServerRequestInterface $request): bool
    {
        return is_array(self::claim($request, 'scopes'));
    }

    private static function claim(ServerRequestInterface $request, string $key): mixed
    {
        $claims = $request->getAttribute(self::CLAIMS_ATTRIBUTE);

        return is_array($claims) ? ($claims[$key] ?? null) : null;
    }
}
