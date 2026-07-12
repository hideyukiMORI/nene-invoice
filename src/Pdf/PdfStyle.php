<?php

declare(strict_types=1);

namespace NeneInvoice\Pdf;

use NeneInvoice\Company\CompanySettings;

/**
 * Presentation settings for a 見積書 / 請求書 PDF (Issue #449): layout template,
 * spacing scale and heading font, derived from {@see CompanySettings}.
 *
 * Compliance note: these are CSS-only. The HTML structure (and therefore every
 * 適格請求書 required field) is identical across all templates — no template,
 * spacing or font choice can add or remove a row. See accounting-compliance.md.
 *
 * Unknown stored values fall back to the defaults rather than throwing: PDF
 * generation must never break on a bad enum (writes are validated upstream).
 */
final readonly class PdfStyle
{
    public const TEMPLATE_STANDARD = 'standard';
    public const TEMPLATE_MODERN   = 'modern';
    public const TEMPLATE_CLASSIC  = 'classic';

    /** @var list<string> */
    public const TEMPLATES = [self::TEMPLATE_STANDARD, self::TEMPLATE_MODERN, self::TEMPLATE_CLASSIC];

    public const SPACING_SMALL  = 'small';
    public const SPACING_MEDIUM = 'medium';
    public const SPACING_LARGE  = 'large';

    /** @var list<string> */
    public const SPACINGS = [self::SPACING_SMALL, self::SPACING_MEDIUM, self::SPACING_LARGE];

    public const FONT_GOTHIC = 'gothic';
    public const FONT_MINCHO = 'mincho';

    /** @var list<string> */
    public const FONTS = [self::FONT_GOTHIC, self::FONT_MINCHO];

    public function __construct(
        public string $template,
        public string $spacing,
        public string $headingFont,
    ) {
    }

    public static function default(): self
    {
        return new self(self::TEMPLATE_STANDARD, self::SPACING_MEDIUM, self::FONT_GOTHIC);
    }

    public static function fromCompany(CompanySettings $company): self
    {
        return new self(
            self::normalize($company->pdfTemplate, self::TEMPLATES, self::TEMPLATE_STANDARD),
            self::normalize($company->pdfSpacing, self::SPACINGS, self::SPACING_MEDIUM),
            self::normalize($company->pdfHeadingFont, self::FONTS, self::FONT_GOTHIC),
        );
    }

    /**
     * mPDF page margins (mm) keyed by margin_left/right/top/bottom.
     *
     * @return array{margin_left: int, margin_right: int, margin_top: int, margin_bottom: int}
     */
    public function pageMargins(): array
    {
        return match ($this->spacing) {
            self::SPACING_SMALL => ['margin_left' => 12, 'margin_right' => 12, 'margin_top' => 16, 'margin_bottom' => 16],
            self::SPACING_LARGE => ['margin_left' => 20, 'margin_right' => 20, 'margin_top' => 25, 'margin_bottom' => 25],
            default             => ['margin_left' => 15, 'margin_right' => 15, 'margin_top' => 20, 'margin_bottom' => 20],
        };
    }

    /** Registered mPDF font name used for headings. */
    public function headingFontFamily(): string
    {
        return $this->headingFont === self::FONT_MINCHO ? 'ipaexmincho' : 'ipaexgothic';
    }

    /**
     * Full `<style>` block for the document, parameterized by template, spacing
     * and heading font. Both the invoice and quote generators share it.
     */
    public function stylesheet(): string
    {
        $f = $this->spacingFactor();

        // Block gaps (mm), scaled from the medium baseline.
        $g = static fn (float $base): string => self::mm($base * $f);

        $headingMb   = $g(4.0);
        $metaMb      = $g(5.0);
        $partiesMb   = $g(5.0);
        $greetingMb  = $g(3.0);
        $itemsMb     = $g(5.0);
        $summaryMb   = $g(5.0);
        $blockMt     = $g(4.0);
        $cellPadV    = $g(1.5);
        $cellPad     = $cellPadV . ' ' . $g(2.0);
        $summaryPad  = $g(1.0) . ' ' . $g(2.0);
        $sellerPad   = $g(0.5) . ' ' . $g(1.0);

        $headingFamily = $this->headingFontFamily();
        $template      = $this->templateCss();

        // The default (unset) font-family keeps mPDF's mode=ja CJK font for body
        // text; only headings switch to the bundled IPAex font.
        return <<<CSS
<style>
body { font-size: 10pt; color: #111; }
h1 { font-family: {$headingFamily}; font-size: 22pt; text-align: center; margin: 0 0 {$headingMb}; padding-bottom: {$cellPadV}; }
.header-meta { text-align: right; font-size: 9pt; margin-bottom: {$metaMb}; }
.parties { width: 100%; border-collapse: collapse; margin-bottom: {$partiesMb}; }
.buyer { width: 55%; vertical-align: top; }
.seller { width: 40%; vertical-align: top; font-size: 9pt; }
.seller table { width: 100%; border-collapse: collapse; }
.seller td { padding: {$sellerPad}; }
.logo-cell { padding-bottom: 1.5mm; }
.company-logo { max-width: 45mm; max-height: 16mm; }
.seal-cell { text-align: right; padding-top: 1mm; }
.seal { width: 20mm; height: 20mm; }
.greeting { margin-bottom: {$greetingMb}; }
.items { width: 100%; border-collapse: collapse; margin-bottom: {$itemsMb}; }
.items th { font-family: {$headingFamily}; padding: {$cellPad}; }
.items td { padding: {$cellPad}; }
.tc { text-align: center; }
.tr { text-align: right; }
.summary { width: 55%; margin-left: auto; border-collapse: collapse; margin-bottom: {$summaryMb}; }
.summary td { padding: {$summaryPad}; border-bottom: 0.25pt solid #ccc; }
.summary td.tr { text-align: right; }
.total-row td { font-weight: bold; font-size: 12pt; border-top: 1pt solid #333; }
.notes { font-size: 9pt; color: #444; margin-top: {$blockMt}; }
.bank { font-size: 9pt; margin-top: {$blockMt}; }
{$template}
</style>
CSS;
    }

    /** Template-specific overrides appended after the shared base rules. */
    private function templateCss(): string
    {
        return match ($this->template) {
            self::TEMPLATE_MODERN => <<<'CSS'
h1 { text-align: left; color: #1a4d7a; border-bottom: 2pt solid #1a4d7a; }
.items th { background: transparent; border: none; border-bottom: 1.2pt solid #1a4d7a; text-align: left; }
.items th.tc, .items th.tr { text-align: inherit; }
.items td { border: none; border-bottom: 0.4pt solid #ddd; }
CSS,
            self::TEMPLATE_CLASSIC => <<<'CSS'
h1 { letter-spacing: 0.4em; border-top: 1pt solid #333; border-bottom: 1pt solid #333; padding-top: 2mm; }
.items th { background: #dcdcdc; border: 0.6pt solid #666; }
.items td { border: 0.6pt solid #666; }
.summary td { border-bottom: 0.4pt solid #999; }
CSS,
            default => <<<'CSS'
h1 { border-bottom: 1pt solid #333; }
.items th { background: #e8e8e8; border: 0.5pt solid #999; }
.items td { border: 0.5pt solid #bbb; }
CSS,
        };
    }

    private function spacingFactor(): float
    {
        return match ($this->spacing) {
            self::SPACING_SMALL => 0.6,
            self::SPACING_LARGE => 1.5,
            default             => 1.0,
        };
    }

    /** Formats a millimetre value compactly (e.g. 2.0 → "2mm", 0.9 → "0.9mm"). */
    private static function mm(float $value): string
    {
        $rounded = round($value, 1);

        return rtrim(rtrim(number_format($rounded, 1, '.', ''), '0'), '.') . 'mm';
    }

    /**
     * @param list<string> $allowed
     */
    private static function normalize(?string $value, array $allowed, string $default): string
    {
        return $value !== null && in_array($value, $allowed, true) ? $value : $default;
    }
}
