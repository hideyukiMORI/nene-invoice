<?php

declare(strict_types=1);

namespace NeneInvoice\Organization\Resolution;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the org slug from the subdomain: org1.example.com → "org1".
 *
 * Configure BASE_DOMAIN=example.com. Requests to the bare base domain (no
 * subdomain) return null.
 */
final readonly class SubdomainResolutionStrategy implements OrgResolutionStrategyInterface
{
    public function __construct(private string $baseDomain)
    {
    }

    public function resolve(ServerRequestInterface $request): ?string
    {
        $host = $request->getUri()->getHost();

        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        $baseParts = explode('.', $this->baseDomain);
        $hostParts = explode('.', $host);

        // Host must have more segments than baseDomain to carry a subdomain.
        if (count($hostParts) <= count($baseParts)) {
            return null;
        }

        // The tail must match baseDomain.
        $tail = array_slice($hostParts, -count($baseParts));
        if ($tail !== $baseParts) {
            return null;
        }

        return $hostParts[0];
    }
}
