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
 * errors must render as an explanation page, not raw Problem Details JSON —
 * while API-shaped clients and the success redirect stay byte-identical (#612).
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

    public function test_browser_429_becomes_html_with_limit_and_retry_minutes(): void
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
        self::assertStringContainsString('1時間に30回まで', $body);
        self::assertStringContainsString('約59分後', $body);
    }

    public function test_browser_503_becomes_capacity_page(): void
    {
        $result = $this->page->apply($this->request('text/html'), $this->problem(503));

        self::assertSame(503, $result->getStatusCode());
        self::assertStringContainsString('満席', (string) $result->getBody());
    }

    public function test_browser_404_becomes_unavailable_page_without_echoing_input(): void
    {
        $result = $this->page->apply($this->request('text/html'), $this->problem(404));

        $body = (string) $result->getBody();
        self::assertSame(404, $result->getStatusCode());
        self::assertStringContainsString('ご利用いただけません', $body);
        self::assertStringNotContainsString('kensetsu', $body, 'fixed copy only — never echo the template segment');
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
