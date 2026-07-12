<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Demo;

use Nene2\Demo\ProvisionedDemoOrg;
use NeneInvoice\Auth\RefreshTokenIssuer;
use NeneInvoice\Demo\DemoSessionSeater;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\InMemoryRefreshTokenRepository;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Attribution layer 1 (#658): the disposable-demo entry logs Referer + UTM tags
 * before the 302 drops them. The log sink is injected so the recorded line can
 * be asserted directly — proving a tagged entry is recorded, a UTM-less entry
 * still logs (and still redirects), and crafted values cannot forge log lines.
 */
final class DemoSessionSeaterTest extends TestCase
{
    /** @var list<string> */
    private array $logged = [];

    private function seater(): DemoSessionSeater
    {
        $issuer = new RefreshTokenIssuer(new InMemoryRefreshTokenRepository(), new FixedClock());

        return new DemoSessionSeater(
            $issuer,
            new Psr17Factory(),
            function (string $line): void {
                $this->logged[] = $line;
            },
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $uri, array $headers = []): ServerRequestInterface
    {
        $request = (new Psr17Factory())->createServerRequest('GET', $uri);
        $query = [];
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
        $request = $request->withQueryParams($query);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    private function line(): string
    {
        self::assertCount(1, $this->logged, 'exactly one entry line expected');

        return $this->logged[0];
    }

    public function test_utm_and_referer_are_recorded_before_redirect(): void
    {
        $request = $this->request(
            '/demo/kensetsu?utm_source=facebook&utm_medium=cpc&utm_campaign=tax2026',
            ['Referer' => 'https://www.facebook.com/'],
        );

        $response = $this->seater()->seatAndRedirect($request, new ProvisionedDemoOrg(orgId: 7, slug: 'demo-abcd', adminUserId: 3));

        // The redirect itself is unaffected.
        self::assertSame(302, $response->getStatusCode());

        $line = $this->line();
        self::assertStringContainsString('NeNe Invoice: demo-entry', $line);
        self::assertStringContainsString('slug=demo-abcd', $line);
        self::assertStringContainsString('utm_source=facebook', $line);
        self::assertStringContainsString('utm_medium=cpc', $line);
        self::assertStringContainsString('utm_campaign=tax2026', $line);
        self::assertStringContainsString('referer=https://www.facebook.com/', $line);
    }

    public function test_entry_without_utm_still_logs_and_redirects(): void
    {
        $request = $this->request('/demo/kensetsu');

        $response = $this->seater()->seatAndRedirect($request, new ProvisionedDemoOrg(orgId: 1, slug: 'demo-zzzz', adminUserId: 2));

        self::assertSame(302, $response->getStatusCode());

        $line = $this->line();
        self::assertStringContainsString('slug=demo-zzzz', $line);
        // Missing tags render as `-` rather than breaking.
        self::assertStringContainsString('utm_source=-', $line);
        self::assertStringContainsString('utm_medium=-', $line);
        self::assertStringContainsString('utm_campaign=-', $line);
        self::assertStringContainsString('referer=-', $line);
    }

    public function test_crafted_values_cannot_forge_log_lines(): void
    {
        $request = $this->request(
            '/demo/kensetsu?utm_source=' . rawurlencode("evil\r\nNeNe Invoice: demo-entry slug=forged"),
        );

        $this->seater()->seatAndRedirect($request, new ProvisionedDemoOrg(orgId: 5, slug: 'demo-real', adminUserId: 4));

        // Exactly one line is emitted and it carries the real slug; CR/LF in the
        // crafted value are stripped so no second logical line can be forged.
        $line = $this->line();
        self::assertStringContainsString('slug=demo-real', $line);
        self::assertStringNotContainsString("\n", $line);
        self::assertStringNotContainsString("\r", $line);
    }
}
