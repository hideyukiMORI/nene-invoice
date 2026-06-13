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
            'mode'     => 'ja',
            'format'   => 'A4',
            'tempDir'  => sys_get_temp_dir(),
            'fontDir'  => array_merge($defaultConfig['fontDir'], [self::FONT_DIR]),
            'fontdata' => $defaultFontConfig['fontdata'] + [
                'ipaexgothic' => ['R' => 'ipaexg.ttf'],
                'ipaexmincho' => ['R' => 'ipaexm.ttf'],
            ],
            ...$style->pageMargins(),
        ]);
        $mpdf->SetTitle($title);

        return $mpdf;
    }
}
