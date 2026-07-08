<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Http;

use NeneInvoice\Http\CacheControlMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CacheControlMiddlewareTest extends TestCase
{
    public function test_adds_no_store_when_response_has_no_cache_control(): void
    {
        $response = (new CacheControlMiddleware())->process(
            new ServerRequest('GET', '/admin/me'),
            self::handlerReturning((new Psr17Factory())->createResponse(200)),
        );

        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function test_keeps_explicit_cache_control_untouched(): void
    {
        $inner = (new Psr17Factory())
            ->createResponse(302)
            ->withHeader('Cache-Control', 'no-store, no-cache');

        $response = (new CacheControlMiddleware())->process(
            new ServerRequest('POST', '/demo/tax-accountant'),
            self::handlerReturning($inner),
        );

        self::assertSame('no-store, no-cache', $response->getHeaderLine('Cache-Control'));
    }

    public function test_keeps_explicit_caching_directive_untouched(): void
    {
        $inner = (new Psr17Factory())
            ->createResponse(200)
            ->withHeader('Cache-Control', 'private, max-age=60');

        $response = (new CacheControlMiddleware())->process(
            new ServerRequest('GET', '/admin/me'),
            self::handlerReturning($inner),
        );

        self::assertSame('private, max-age=60', $response->getHeaderLine('Cache-Control'));
    }

    private static function handlerReturning(ResponseInterface $response): RequestHandlerInterface
    {
        return new class ($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }
}
