<?php

declare(strict_types=1);

namespace NeneInvoice\Pdf;

/**
 * Renders the issuer logo (`logo_url`) as a self-contained seller-block row for
 * the 見積書 / 請求書 PDF (Issue #510). Shared by the invoice and quote generators.
 *
 * Security: only base64 `data:` image URIs are embedded. Remote URLs
 * (http/https), local file paths and any other scheme are deliberately NOT
 * rendered — mPDF would perform a server-side fetch, which is (a) an SSRF surface
 * that a prior security assessment relied on being absent (see
 * docs/security/2026-06-08-assessment-round3.md) and (b) unreliable on
 * network-restricted shared hosting such as HETEML. Embedding only self-contained
 * data URIs keeps the "no server-side fetch" property intact while still letting a
 * stored logo appear on the document. The src is attribute-escaped.
 *
 * Compliance: the logo is decorative — it adds no 適格請求書 required field and
 * removes none — so it is compliance-neutral (docs/explanation/accounting-compliance.md;
 * see the PdfStyle presentation note).
 */
final class PdfLogo
{
    /** A single-line base64 raster data URI (png / jpeg / gif / webp). */
    private const DATA_URI = '#^data:image/(?:png|jpe?g|gif|webp);base64,[A-Za-z0-9+/]+={0,2}$#';

    /**
     * Seller-block table row (`<tr>`) with the embedded logo, or '' when the
     * stored value is null or not a safe base64 image data URI.
     */
    public static function html(?string $logoUrl): string
    {
        if ($logoUrl === null || preg_match(self::DATA_URI, $logoUrl) !== 1) {
            return '';
        }

        $src = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');

        return '<tr><td colspan="2" class="logo-cell">'
            . '<img class="company-logo" src="' . $src . '"></td></tr>';
    }
}
