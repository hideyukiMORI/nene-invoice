<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Organization\Resolution;

use NeneInvoice\Organization\Resolution\CustomDomainResolutionStrategy;
use NeneInvoice\Organization\Resolution\EnvResolutionStrategy;
use NeneInvoice\Organization\Resolution\PathPrefixResolutionStrategy;
use NeneInvoice\Organization\Resolution\SubdomainResolutionStrategy;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class ResolutionStrategiesTest extends TestCase
{
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
    }

    public function test_env_returns_slug_when_set(): void
    {
        $strategy = new EnvResolutionStrategy('acme');
        $request  = $this->psr17->createServerRequest('GET', 'https://app.example.com/admin/invoices');

        self::assertSame('acme', $strategy->resolve($request));
    }

    public function test_env_returns_null_when_empty(): void
    {
        $strategy = new EnvResolutionStrategy('');
        $request  = $this->psr17->createServerRequest('GET', 'https://app.example.com/admin/invoices');

        self::assertNull($strategy->resolve($request));
    }

    public function test_path_prefix_extracts_first_segment(): void
    {
        $strategy = new PathPrefixResolutionStrategy();
        $request  = $this->psr17->createServerRequest('GET', 'https://app.example.com/acme/admin/invoices');

        self::assertSame('acme', $strategy->resolve($request));
    }

    public function test_path_prefix_bypasses_reserved_prefixes(): void
    {
        $strategy = new PathPrefixResolutionStrategy();

        foreach (['/health', '/auth/login', '/api/invoices', '/invoices/download/tok', '/admin/organizations'] as $path) {
            $request = $this->psr17->createServerRequest('GET', "https://app.example.com{$path}");
            self::assertNull($strategy->resolve($request), "expected bypass for {$path}");
        }
    }

    public function test_path_prefix_strips_slug_segment_for_routing(): void
    {
        $strategy = new PathPrefixResolutionStrategy();

        self::assertSame('/admin/invoices', $strategy->stripPrefix('/acme/admin/invoices'));
        self::assertSame('/admin/me', $strategy->stripPrefix('/acme/admin/me'));
        self::assertSame('/auth/login', $strategy->stripPrefix('/acme/auth/login'));
    }

    public function test_path_prefix_strip_returns_root_when_only_slug(): void
    {
        $strategy = new PathPrefixResolutionStrategy();

        self::assertSame('/', $strategy->stripPrefix('/acme'));
        self::assertSame('/', $strategy->stripPrefix('/acme/'));
    }

    public function test_subdomain_extracts_label(): void
    {
        $strategy = new SubdomainResolutionStrategy('example.com');
        $request  = $this->psr17->createServerRequest('GET', 'https://acme.example.com/admin/invoices');

        self::assertSame('acme', $strategy->resolve($request));
    }

    public function test_subdomain_returns_null_for_bare_domain(): void
    {
        $strategy = new SubdomainResolutionStrategy('example.com');
        $request  = $this->psr17->createServerRequest('GET', 'https://example.com/admin/invoices');

        self::assertNull($strategy->resolve($request));
    }

    public function test_subdomain_returns_null_when_tail_mismatch(): void
    {
        $strategy = new SubdomainResolutionStrategy('example.com');
        $request  = $this->psr17->createServerRequest('GET', 'https://acme.other.org/admin/invoices');

        self::assertNull($strategy->resolve($request));
    }

    public function test_custom_domain_returns_host(): void
    {
        $strategy = new CustomDomainResolutionStrategy();
        $request  = $this->psr17->createServerRequest('GET', 'https://billing.acme.co.jp/admin/invoices');

        self::assertSame('billing.acme.co.jp', $strategy->resolve($request));
    }
}
