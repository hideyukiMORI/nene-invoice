<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Demo;

use NeneInvoice\Demo\DemoBrowserErrorPage;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The public demo link is opened by non-technical visitors in a browser, so its
 * errors must render as a designed explanation page (redesign reference theme,
 * #614) with its own CSP — not raw Problem Details JSON — while API-shaped
 * clients and the success redirect stay byte-identical (#612).
 */
final class DemoBrowserErrorPageTest extends TestCase
{
    private Psr17Factory $psr17;
    private DemoBrowserErrorPage $page;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
        $this->page = new DemoBrowserErrorPage($this->psr17, 30, 3600);
    }

    private function request(string $accept): ServerRequestInterface
    {
        $request = $this->psr17->createServerRequest('GET', '/demo/kensetsu');

        return $accept === '' ? $request : $request->withHeader('Accept', $accept);
    }

    private function problem(int $status): ResponseInterface
    {
        $response = $this->psr17->createResponse($status)
            ->withHeader('Content-Type', 'application/problem+json');
        $response->getBody()->write('{"type":"problem"}');

        return $response;
    }

    public function test_browser_429_renders_limit_countdown_and_disabled_retry(): void
    {
        $result = $this->page->apply(
            $this->request('text/html,application/xhtml+xml;q=0.9,*/*;q=0.8'),
            $this->problem(429)->withHeader('Retry-After', '3519'),
        );

        self::assertSame(429, $result->getStatusCode());
        self::assertStringStartsWith('text/html', $result->getHeaderLine('Content-Type'));
        self::assertSame('3519', $result->getHeaderLine('Retry-After'), 'Retry-After must survive negotiation');
        self::assertSame('no-store', $result->getHeaderLine('Cache-Control'));

        $body = (string) $result->getBody();
        self::assertStringContainsString('1時間あたり30回まで', $body);
        self::assertStringContainsString('id="clock">58:39<', $body, 'initial clock is server-rendered (works without JS)');
        self::assertStringContainsString('var remain = 3519;', $body, 'countdown script seeds from Retry-After');
        self::assertStringContainsString('id="retry" type="button" disabled', $body, 'retry stays disabled until the window resets');
        self::assertStringContainsString('429 · DEMO_RATE_LIMIT', $body);
    }

    public function test_html_page_carries_its_own_csp_allowing_inline_assets(): void
    {
        $result = $this->page->apply($this->request('text/html'), $this->problem(429)->withHeader('Retry-After', '60'));

        $csp = $result->getHeaderLine('Content-Security-Policy');
        self::assertStringContainsString("style-src 'unsafe-inline'", $csp);
        self::assertStringContainsString("script-src 'unsafe-inline'", $csp);
        self::assertStringContainsString("default-src 'none'", $csp, 'app-wide default-src self would block the inline design');
    }

    public function test_browser_503_renders_capacity_page_with_enabled_retry_and_no_countdown(): void
    {
        $result = $this->page->apply($this->request('text/html'), $this->problem(503));

        $body = (string) $result->getBody();
        self::assertSame(503, $result->getStatusCode());
        self::assertStringContainsString('満席', $body);
        self::assertStringContainsString('id="retry" type="button">', $body, 'capacity clears on its own — retry starts enabled');
        self::assertStringNotContainsString('id="clock"', $body);
        self::assertStringContainsString('503 · DEMO_CAPACITY', $body);
    }

    public function test_browser_404_renders_unavailable_page_without_echoing_input(): void
    {
        $result = $this->page->apply($this->request('text/html'), $this->problem(404));

        $body = (string) $result->getBody();
        self::assertSame(404, $result->getStatusCode());
        self::assertStringContainsString('ご利用いただけません', $body);
        self::assertStringNotContainsString('kensetsu', $body, 'fixed copy only — never echo the template segment');
        self::assertStringNotContainsString('id="retry"', $body, 'reloading a dead link would just 404 again');
        self::assertStringContainsString('404 · DEMO_NOT_FOUND', $body);
    }

    public function test_api_clients_keep_problem_details_untouched(): void
    {
        $problem = $this->problem(429)->withHeader('Retry-After', '60');

        self::assertSame($problem, $this->page->apply($this->request('application/json'), $problem));
        self::assertSame($problem, $this->page->apply($this->request('*/*'), $problem));
        self::assertSame($problem, $this->page->apply($this->request(''), $problem));
    }

    public function test_success_redirect_is_untouched_even_for_browsers(): void
    {
        $redirect = $this->psr17->createResponse(302)->withHeader('Location', '/demo-abc/dashboard');

        self::assertSame($redirect, $this->page->apply($this->request('text/html'), $redirect));
    }
}
