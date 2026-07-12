<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Pdf;

use NeneInvoice\Pdf\PdfLogo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PdfLogoTest extends TestCase
{
    public function test_embeds_a_base64_image_data_uri(): void
    {
        // 1x1 transparent PNG.
        $dataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $html = PdfLogo::html($dataUri);

        self::assertStringContainsString('<img class="company-logo"', $html);
        self::assertStringContainsString('src="' . $dataUri . '"', $html);
    }

    /**
     * Remote URLs, local paths and other schemes must never be rendered: mPDF
     * would fetch them server-side (SSRF surface / HETEML network restriction).
     */
    #[DataProvider('unsafeValueProvider')]
    public function test_ignores_values_that_are_not_base64_image_data_uris(?string $logoUrl): void
    {
        self::assertSame('', PdfLogo::html($logoUrl));
    }

    /** @return iterable<string, array{?string}> */
    public static function unsafeValueProvider(): iterable
    {
        yield 'null'          => [null];
        yield 'empty'        => [''];
        yield 'https url'    => ['https://example.com/logo.png'];
        yield 'http url'     => ['http://example.com/logo.png'];
        yield 'file path'    => ['/var/www/logo.png'];
        yield 'file scheme'  => ['file:///etc/passwd'];
        yield 'relative'     => ['assets/logo.png'];
        yield 'data non-image' => ['data:text/html;base64,PHNjcmlwdD4='];
        yield 'data svg'     => ['data:image/svg+xml;base64,PHN2Zz48L3N2Zz4='];
        yield 'not base64'   => ['data:image/png,notbase64'];
    }
}
