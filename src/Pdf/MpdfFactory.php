<?php

declare(strict_types=1);

namespace NeneInvoice\Pdf;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\MpdfException;

/**
 * Builds a configured mPDF instance for document rendering, with the bundled
 * IPAex fonts registered so headings can switch between ゴシック / 明朝
 * (Issue #449). Shared by the invoice and quote PDF generators.
 *
 * mPDF ships no Japanese font; IPAexGothic / IPAexMincho (IPA Font License) are
 * bundled under resources/fonts/ and merged into mPDF's font config here.
 *
 * Body text must also use IPAex. mPDF's `mode => 'ja'` turns on
 * autoScriptToLang / autoLangToFont, which routes every CJK run to the bundled
 * Sun-ExtA — a *Chinese* font whose glyph shapes (化, 直, 込 …) are wrong for
 * Japanese readers (found on the first real quote, 2026-08-27). So the language
 * auto-mapping is switched off and IPAexGothic is the document default; CSS
 * `font-family` alone decides the face.
 */
final readonly class MpdfFactory
{
    private const FONT_DIR = __DIR__ . '/../../resources/fonts';

    /**
     * @throws MpdfException
     */
    public function create(PdfStyle $style, string $title): Mpdf
    {
        $defaultConfig     = (new ConfigVariables())->getDefaults();
        $defaultFontConfig = (new FontVariables())->getDefaults();

        $mpdf = new Mpdf([
            'mode'             => 'UTF-8', // exact case: mPDF treats anything but 'UTF-8' as a language code
            'format'           => 'A4',
            'tempDir'          => sys_get_temp_dir(),
            'fontDir'          => array_merge($defaultConfig['fontDir'], [self::FONT_DIR]),
            'fontdata'         => $defaultFontConfig['fontdata'] + [
                'ipaexgothic' => ['R' => 'ipaexg.ttf'],
                'ipaexmincho' => ['R' => 'ipaexm.ttf'],
            ],
            'default_font'     => 'ipaexgothic',
            // Never let mPDF pick a font by script/lang: that is the path to Sun-ExtA.
            'autoScriptToLang' => false,
            'autoLangToFont'   => false,
            ...$style->pageMargins(),
        ]);
        $mpdf->SetTitle($title);

        return $mpdf;
    }
}
