<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Auth;

use NeneInvoice\Auth\CsrfGuard;
use NeneInvoice\Auth\CsrfValidationException;
use NeneInvoice\Auth\SessionCookies;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class CsrfGuardTest extends TestCase
{
    public function test_passes_when_header_matches_cookie(): void
    {
        $request = $this->request(cookie: 'tok-123', header: 'tok-123');

        CsrfGuard::assert($request);

        $this->addToAssertionCount(1);
    }

    public function test_rejects_missing_header(): void
    {
        $this->expectException(CsrfValidationException::class);
        CsrfGuard::assert($this->request(cookie: 'tok-123', header: null));
    }

    public function test_rejects_missing_cookie(): void
    {
        $this->expectException(CsrfValidationException::class);
        CsrfGuard::assert($this->request(cookie: null, header: 'tok-123'));
    }

    public function test_rejects_mismatch(): void
    {
        $this->expectException(CsrfValidationException::class);
        CsrfGuard::assert($this->request(cookie: 'tok-123', header: 'tok-XXX'));
    }

    private function request(?string $cookie, ?string $header): ServerRequestInterface
    {
        $request = (new Psr17Factory())->createServerRequest('POST', '/auth/refresh');

        if ($cookie !== null) {
            $request = $request->withCookieParams([SessionCookies::CSRF_COOKIE => $cookie]);
        }

        if ($header !== null) {
            $request = $request->withHeader(CsrfGuard::HEADER, $header);
        }

        return $request;
    }
}
