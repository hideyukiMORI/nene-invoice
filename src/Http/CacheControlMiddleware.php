<?php

declare(strict_types=1);

namespace NeneInvoice\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Adds `Cache-Control: no-store` to every API response that does not set the
 * header itself, so authenticated invoicing data is never stored by shared
 * caches.
 *
 * RFC 9111 §3.5 forbids shared caches from storing responses to requests that
 * carry an `Authorization` header, but on Tier A shared hosting the SPA
 * delivers the Bearer token via the `X-Authorization` mirror
 * ({@see AuthorizationHeaderFallback}, #596), which intermediaries do not
 * recognize — that safety net does not apply on this path, so the application
 * must opt out of caching explicitly.
 *
 * An explicit `Cache-Control` set by a handler (e.g. the demo 302's
 * `no-store`, or a future conditional-GET/ETag response) is left untouched.
 * The SPA shell and static assets are served outside this pipeline and keep
 * their existing cache behaviour.
 */
final readonly class CacheControlMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if ($response->hasHeader('Cache-Control')) {
            return $response;
        }

        return $response->withHeader('Cache-Control', 'no-store');
    }
}
