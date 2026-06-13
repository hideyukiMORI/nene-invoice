<?php

declare(strict_types=1);

namespace NeneInvoice\Quote\Pdf;

use Mpdf\MpdfException;
use NeneInvoice\LineItem\TaxBreakdownLine;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\Pdf\MpdfFactory;
use NeneInvoice\Pdf\PdfStyle;
use NeneInvoice\Support\Jst;
use RuntimeException;

/**
 * Generates a quote (見積書) PDF as a binary string.
 *
 * Totals come from the stored quote (single source of truth — ADR 0004). The
 * per-rate breakdown is re-derived from line items via TaxCalculator.
 */
final readonly class QuotePdfGenerator
{
    public function __construct(
        private TaxCalculator $taxCalculator,
        private MpdfFactory $mpdfFactory,
    ) {
    }

    /** @throws RuntimeException if mPDF fails */
    public function generate(QuotePdfData $data): string
    {
        $quote   = $data->quoteWithLines->quote;
        $lines   = $data->quoteWithLines->lines;
        $company = $data->companySettings;
        $client  = $data->client;

        $inputs  = array_map(static fn ($l) => $l->toCalculationInput(), $lines);
        $tax     = $this->taxCalculator->calculate($inputs);
        $style   = PdfStyle::fromCompany($company);

        $html = $this->buildHtml(
            styleBlock: $style->stylesheet(),
            quoteNumber: $quote->quoteNumber,
            issuedAt: $quote->issuedAt ? Jst::date($quote->issuedAt) : '—',
            validUntil: $quote->validUntil ? substr($quote->validUntil, 0, 10) : '—',
            clientName: $client->name,
            clientAddress: $client->billingAddress ?? '',
            companyName: $company->legalName,
            companyAddress: $company->address ?? '',
            companyRegistrationNumber: $company->registrationNumber ?? '',
            companyBank: $this->formatBankInfo($company),
            lines: $lines,
            breakdown: $tax->breakdown,
            subtotalCents: $quote->subtotalCents,
            taxCents: $quote->taxCents,
            totalCents: $quote->totalCents,
            notes: $quote->notes ?? '',
        );

        try {
            $mpdf = $this->mpdfFactory->create($style, $quote->quoteNumber);
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
        string $quoteNumber,
        string $issuedAt,
        string $validUntil,
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
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $lineRows = '';
        foreach ($lines as $i => $line) {
            $rate      = number_format($line->taxRateBps / 100, 0) . '%';
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
            $rate           = number_format($b->taxRateBps / 100, 0) . '%';
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
<h1>見積書</h1>
<div class="header-meta">
  発行日: {$esc($issuedAt)}&nbsp;&nbsp;
  有効期限: {$esc($validUntil)}&nbsp;&nbsp;
  見積番号: {$esc($quoteNumber)}
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
        <tr><td colspan="2"><strong>{$esc($companyName)}</strong></td></tr>
        <tr><td colspan="2" style="font-size:8pt;">{$esc($companyAddress)}</td></tr>
        {$registrationRow}
      </table>
    </td>
  </tr>
</table>
<p class="greeting">下記の通りお見積り申し上げます。</p>
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
  <tr class="total-row"><td>お見積金額</td><td class="tr">{$totalYen}</td></tr>
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
