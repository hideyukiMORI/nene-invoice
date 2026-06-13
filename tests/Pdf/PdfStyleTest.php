<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Pdf;

use NeneInvoice\Company\CompanySettings;
use NeneInvoice\Pdf\PdfStyle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PdfStyleTest extends TestCase
{
    public function test_default_uses_standard_medium_gothic(): void
    {
        $style = PdfStyle::default();

        self::assertSame(PdfStyle::TEMPLATE_STANDARD, $style->template);
        self::assertSame(PdfStyle::SPACING_MEDIUM, $style->spacing);
        self::assertSame(PdfStyle::FONT_GOTHIC, $style->headingFont);
    }

    public function test_from_company_reads_stored_values(): void
    {
        $style = PdfStyle::fromCompany($this->company('modern', 'large', 'mincho'));

        self::assertSame('modern', $style->template);
        self::assertSame('large', $style->spacing);
        self::assertSame('mincho', $style->headingFont);
    }

    public function test_unknown_stored_values_fall_back_to_defaults(): void
    {
        // PDF generation must never break on a bad enum left in the database.
        $style = PdfStyle::fromCompany($this->company('bogus', 'huge', 'comic-sans'));

        self::assertSame(PdfStyle::TEMPLATE_STANDARD, $style->template);
        self::assertSame(PdfStyle::SPACING_MEDIUM, $style->spacing);
        self::assertSame(PdfStyle::FONT_GOTHIC, $style->headingFont);
    }

    public function test_heading_font_family_maps_to_bundled_fonts(): void
    {
        self::assertSame('ipaexgothic', (new PdfStyle('standard', 'medium', 'gothic'))->headingFontFamily());
        self::assertSame('ipaexmincho', (new PdfStyle('standard', 'medium', 'mincho'))->headingFontFamily());
    }

    public function test_spacing_changes_page_margins(): void
    {
        $small  = (new PdfStyle('standard', 'small', 'gothic'))->pageMargins();
        $medium = (new PdfStyle('standard', 'medium', 'gothic'))->pageMargins();
        $large  = (new PdfStyle('standard', 'large', 'gothic'))->pageMargins();

        self::assertLessThan($medium['margin_top'], $small['margin_top']);
        self::assertGreaterThan($medium['margin_top'], $large['margin_top']);
        self::assertSame(['margin_left', 'margin_right', 'margin_top', 'margin_bottom'], array_keys($medium));
    }

    public function test_stylesheet_applies_heading_font(): void
    {
        self::assertStringContainsString('font-family: ipaexmincho', (new PdfStyle('standard', 'medium', 'mincho'))->stylesheet());
        self::assertStringContainsString('font-family: ipaexgothic', (new PdfStyle('standard', 'medium', 'gothic'))->stylesheet());
    }

    #[DataProvider('templateMarkerProvider')]
    public function test_stylesheet_carries_template_marker(string $template, string $marker): void
    {
        self::assertStringContainsString($marker, (new PdfStyle($template, 'medium', 'gothic'))->stylesheet());
    }

    /** @return iterable<string, array{string, string}> */
    public static function templateMarkerProvider(): iterable
    {
        yield 'standard keeps the bordered table' => ['standard', '#e8e8e8'];
        yield 'modern uses the accent colour'     => ['modern', '#1a4d7a'];
        yield 'classic spaces the title'          => ['classic', 'letter-spacing'];
    }

    #[DataProvider('allCombinationsProvider')]
    public function test_no_template_hides_content(string $template, string $spacing, string $font): void
    {
        // Compliance guard: templates are CSS-only and may never hide a row, or a
        // 適格請求書 required field could vanish. The structural classes that carry
        // those fields must always be present and never display:none / hidden.
        $css = (new PdfStyle($template, $spacing, $font))->stylesheet();

        self::assertStringNotContainsString('display: none', $css);
        self::assertStringNotContainsString('display:none', $css);
        self::assertStringNotContainsString('visibility: hidden', $css);
        foreach (['.items', '.summary', '.total-row', '.seller'] as $class) {
            self::assertStringContainsString($class, $css);
        }
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function allCombinationsProvider(): iterable
    {
        foreach (PdfStyle::TEMPLATES as $template) {
            foreach (PdfStyle::SPACINGS as $spacing) {
                foreach (PdfStyle::FONTS as $font) {
                    yield "$template/$spacing/$font" => [$template, $spacing, $font];
                }
            }
        }
    }

    private function company(string $template, string $spacing, string $font): CompanySettings
    {
        return new CompanySettings(
            organizationId: 1,
            legalName: '株式会社ネネ商会',
            pdfTemplate: $template,
            pdfSpacing: $spacing,
            pdfHeadingFont: $font,
        );
    }
}
