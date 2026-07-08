<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Http;

use NeneInvoice\Http\AuthorizationHeaderFallback;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class AuthorizationHeaderFallbackTest extends TestCase
{
    public function test_adopts_x_authorization_when_authorization_is_absent(): void
    {
        $request = (new ServerRequest('GET', '/admin/me'))
            ->withHeader('X-Authorization', 'Bearer token-via-fallback');

        $result = AuthorizationHeaderFallback::apply($request);

        self::assertSame('Bearer token-via-fallback', $result->getHeaderLine('Authorization'));
    }

    public function test_keeps_standard_authorization_when_both_are_present(): void
    {
        $request = (new ServerRequest('GET', '/admin/me'))
            ->withHeader('Authorization', 'Bearer standard')
            ->withHeader('X-Authorization', 'Bearer mirrored');

        $result = AuthorizationHeaderFallback::apply($request);

        self::assertSame('Bearer standard', $result->getHeaderLine('Authorization'));
    }

    public function test_no_op_when_neither_header_is_present(): void
    {
        $request = new ServerRequest('GET', '/admin/me');

        $result = AuthorizationHeaderFallback::apply($request);

        self::assertFalse($result->hasHeader('Authorization'));
        self::assertSame($request, $result);
    }

    public function test_no_op_when_fallback_header_is_empty(): void
    {
        $request = (new ServerRequest('GET', '/admin/me'))
            ->withHeader('X-Authorization', '');

        $result = AuthorizationHeaderFallback::apply($request);

        self::assertFalse($result->hasHeader('Authorization'));
    }
}
