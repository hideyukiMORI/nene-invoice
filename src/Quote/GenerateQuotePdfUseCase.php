<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\Client\ClientRepositoryInterface;
use NeneInvoice\Company\CompanySettings;
use NeneInvoice\Company\CompanySettingsRepositoryInterface;
use NeneInvoice\Company\Seal\CompanySealRepositoryInterface;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\Quote\Pdf\QuotePdfData;

/**
 * Assembles all data needed to render a quote PDF: quote with lines,
 * company settings (issuer), and client (buyer).
 */
final readonly class GenerateQuotePdfUseCase implements GenerateQuotePdfUseCaseInterface
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private QuoteRepositoryInterface $quotes,
        private LineItemRepositoryInterface $lineItems,
        private CompanySettingsRepositoryInterface $companySettings,
        private ClientRepositoryInterface $clients,
        private CompanySealRepositoryInterface $seals,
        private RequestScopedHolder $orgId,
    ) {
    }

    /** @throws QuoteNotFoundException */
    public function execute(int $quoteId): QuotePdfData
    {
        $organizationId = $this->orgId->get();

        $quote = $this->quotes->findById($quoteId);

        if ($quote === null) {
            throw new QuoteNotFoundException($quoteId);
        }

        $lines     = $this->lineItems->findByParent(LineItemParent::Quote, $quoteId);
        $withLines = new QuoteWithLines($quote, $lines);

        $company = $this->companySettings->find()
            ?? new CompanySettings(
                organizationId: $organizationId,
                legalName: '（会社情報未設定）',
            );

        $client = $this->clients->findById($quote->clientId)
            ?? new Client(
                organizationId: $organizationId,
                name: '（取引先情報なし）',
            );

        return new QuotePdfData($withLines, $company, $client, $this->seals->find());
    }
}
