<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice\Pdf;

use Mpdf\MpdfException;
use NeneInvoice\LineItem\TaxBreakdownLine;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\Pdf\MpdfFactory;
use NeneInvoice\Pdf\PdfLogo;
use NeneInvoice\Pdf\PdfStyle;
use NeneInvoice\Support\Jst;
use RuntimeException;

/**
 * Generates a qualified invoice (適格請求書) PDF as a binary string.
 *
 * PDF totals come from the stored invoice (single source of truth — ADR 0004).
 * The per-rate breakdown is re-derived from line items via TaxCalculator so the
 * PDF matches the API response exactly.
 */
final readonly class InvoicePdfGenerator implements InvoicePdfGeneratorInterface
{
    public function __construct(
        private TaxCalculator $taxCalculator,
        private MpdfFactory $mpdfFactory,
    ) {
    }

    /** @throws RuntimeException if mPDF fails */
    public function generate(InvoicePdfData $data): string
    {
        $invoice = $data->invoiceWithLines->invoice;
        $lines   = $data->invoiceWithLines->lines;
        $company = $data->companySettings;
        $client  = $data->client;

        $inputs  = array_map(static fn ($l) => $l->toCalculationInput(), $lines);
        $tax     = $this->taxCalculator->calculate($inputs);
        $style   = PdfStyle::fromCompany($company);

        $html = $this->buildHtml(
            styleBlock: $style->stylesheet(),
            logoHtml: PdfLogo::html($company->logoUrl),
            sealHtml: self::sealHtml($data->sealImageBase64),
            invoiceNumber: $invoice->invoiceNumber ?? '（下書き）',
            issuedAt: $invoice->issuedAt ? Jst::date($invoice->issuedAt) : '—',
            dueAt: $invoice->dueAt ? substr($invoice->dueAt, 0, 10) : '—',
            isQualified: $invoice->isQualifiedInvoice,
            clientName: $client->name,
            clientAddress: $client->billingAddress ?? '',
            companyName: $company->legalName,
            companyAddress: $company->address ?? '',
            companyRegistrationNumber: $company->registrationNumber ?? '',
            companyBank: $this->formatBankInfo($company),
            lines: $lines,
            breakdown: $tax->breakdown,
            subtotalCents: $invoice->subtotalCents,
            taxCents: $invoice->taxCents,
            totalCents: $invoice->totalCents,
            notes: $invoice->notes ?? '',
        );

        try {
            $mpdf = $this->mpdfFactory->create($style, $invoice->invoiceNumber ?? '請求書');
            $mpdf->WriteHTML($html);

            return $mpdf->Output('', 'S');
        } catch (MpdfException $e) {
            throw new RuntimeException('PDF generation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param list<\NeneInvoice\LineItem\LineItem> $lines
     * @param list<TaxBreakdownLine>               $breakdown
     */
    private function buildHtml(
        string $styleBlock,
        string $logoHtml,
        string $sealHtml,
        string $invoiceNumber,
        string $issuedAt,
        string $dueAt,
        bool $isQualified,
        string $clientName,
        string $clientAddress,
        string $companyName,
        string $companyAddress,
        string $companyRegistrationNumber,
        string $companyBank,
        array $lines,
        array $breakdown,
        int $subtotalCents,
        int $taxCents,
        int $totalCents,
        string $notes,
    ): string {
        $title = $isQualified ? '適格請求書' : '請求書';
        $esc   = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $lineRows = '';
        foreach ($lines as $i => $line) {
            $rate    = number_format($line->taxRateBps / 100, 0) . '%';
            $lineRows .= sprintf(
                '<tr><td class="tc">%d</td><td>%s</td><td class="tr">%s</td>'
                . '<td class="tr">%s</td><td class="tc">%s</td><td class="tr">%s</td></tr>',
                $i + 1,
                $esc($line->description),
                number_format($line->quantity),
                self::yen($line->unitPriceCents),
                $rate,
                self::yen($line->lineSubtotalCents()),
            );
        }

        $breakdownRows = '';
        foreach ($breakdown as $b) {
            $rate = number_format($b->taxRateBps / 100, 0) . '%';
            $breakdownRows .= sprintf(
                '<tr><td>%s 対象額</td><td class="tr">%s</td></tr>'
                . '<tr><td>消費税（%s）</td><td class="tr">%s</td></tr>',
                $rate,
                self::yen($b->taxableAmountCents),
                $rate,
                self::yen($b->taxCents),
            );
        }

        $registrationRow = $companyRegistrationNumber !== ''
            ? '<tr><td>登録番号</td><td>' . $esc($companyRegistrationNumber) . '</td></tr>'
            : '';

        $clientAddressHtml = $clientAddress !== ''
            ? '<p>' . $esc($clientAddress) . '</p>'
            : '';

        $bankHtml = $companyBank !== ''
            ? '<p class="bank"><strong>振込先</strong><br>' . $esc($companyBank) . '</p>'
            : '';

        $notesHtml = $notes !== ''
            ? '<p class="notes"><strong>備考</strong><br>' . nl2br($esc($notes)) . '</p>'
            : '';

        // Heredoc does not interpolate static calls ({self::yen(...)}), so the
        // amounts are formatted into plain variables first.
        $subtotalYen = self::yen($subtotalCents);
        $taxTotalYen = self::yen($taxCents);
        $totalYen    = self::yen($totalCents);

        return <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
{$styleBlock}
</head>
<body>
<h1>{$title}</h1>
<div class="header-meta">
  発行日: {$esc($issuedAt)}&nbsp;&nbsp;
  支払期限: {$esc($dueAt)}&nbsp;&nbsp;
  請求書番号: {$esc($invoiceNumber)}
</div>
<table class="parties">
  <tr>
    <td class="buyer">
      <strong style="font-size:13pt;">{$esc($clientName)} 御中</strong>
      {$clientAddressHtml}
    </td>
    <td width="5%"></td>
    <td class="seller">
      <table>
        {$logoHtml}
        <tr><td colspan="2"><strong>{$esc($companyName)}</strong></td></tr>
        <tr><td colspan="2" style="font-size:8pt;">{$esc($companyAddress)}</td></tr>
        {$registrationRow}
        {$sealHtml}
      </table>
    </td>
  </tr>
</table>
<p class="greeting">下記の通りご請求申し上げます。</p>
<table class="items">
  <thead>
    <tr>
      <th class="tc" width="5%">No.</th>
      <th>品目</th>
      <th class="tr" width="8%">数量</th>
      <th class="tr" width="14%">単価</th>
      <th class="tc" width="7%">税率</th>
      <th class="tr" width="14%">小計</th>
    </tr>
  </thead>
  <tbody>{$lineRows}</tbody>
</table>
<table class="summary">
  {$breakdownRows}
  <tr><td>小計</td><td class="tr">{$subtotalYen}</td></tr>
  <tr><td>消費税合計</td><td class="tr">{$taxTotalYen}</td></tr>
  <tr class="total-row"><td>ご請求金額</td><td class="tr">{$totalYen}</td></tr>
</table>
{$bankHtml}
{$notesHtml}
</body>
</html>
HTML;
    }

    private static function yen(int $cents): string
    {
        return '&yen;' . number_format($cents);
    }

    /** Issuer seal (社印) as a stamped data-URI image, or '' when none is set. */
    private static function sealHtml(?string $sealImageBase64): string
    {
        if ($sealImageBase64 === null) {
            return '';
        }

        return '<tr><td colspan="2" class="seal-cell">'
            . '<img class="seal" src="data:image/png;base64,' . $sealImageBase64 . '"></td></tr>';
    }

    private function formatBankInfo(\NeneInvoice\Company\CompanySettings $company): string
    {
        $parts = array_filter([
            $company->bankName,
            $company->bankBranch !== null ? $company->bankBranch . '支店' : null,
            $company->accountType,
            $company->accountNumber,
        ]);

        return implode(' ', $parts);
    }
}
