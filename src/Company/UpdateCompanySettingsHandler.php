<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneInvoice\Auth\AuthContext;
use NeneInvoice\Pdf\PdfStyle;
use NeneInvoice\Support\RequestField;
use NeneInvoice\Support\TextLimit;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `PUT /admin/company-settings` — upserts the issuer profile for the caller's
 * organization.
 */
final readonly class UpdateCompanySettingsHandler implements RequestHandlerInterface
{
    public function __construct(
        private UpdateCompanySettingsUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $decoded = JsonRequestBodyParser::parse($request);

        $legalName = $decoded['legal_name'] ?? null;

        if (!is_string($legalName) || $legalName === '') {
            throw new ValidationException([new ValidationError('body.legal_name', 'Legal name is required.', 'required')]);
        }
        TextLimit::check($legalName, 'body.legal_name', TextLimit::NAME);

        $this->validateBillingDefaults($decoded);
        $this->validatePdfAppearance($decoded);

        $settings = $this->useCase->execute(AuthContext::userId($request), new UpdateCompanySettingsInput(
            legalName: $legalName,
            address: RequestField::optionalString($decoded, 'address', TextLimit::NOTE),
            phone: RequestField::optionalString($decoded, 'phone', TextLimit::TINY),
            email: RequestField::optionalString($decoded, 'email'),
            registrationNumber: RequestField::optionalString($decoded, 'registration_number'),
            bankName: RequestField::optionalString($decoded, 'bank_name'),
            bankBranch: RequestField::optionalString($decoded, 'bank_branch'),
            accountType: RequestField::optionalString($decoded, 'account_type', TextLimit::TINY),
            accountNumber: RequestField::optionalString($decoded, 'account_number', TextLimit::ACCOUNT),
            logoUrl: RequestField::optionalString($decoded, 'logo_url', TextLimit::LONG),
            defaultQuoteValidityDays: RequestField::optionalInt($decoded, 'default_quote_validity_days'),
            defaultPaymentClosingDay: RequestField::optionalInt($decoded, 'default_payment_closing_day'),
            defaultPaymentMonthOffset: RequestField::optionalInt($decoded, 'default_payment_month_offset'),
            defaultPaymentPayDay: RequestField::optionalInt($decoded, 'default_payment_pay_day'),
            pdfTemplate: $this->enumOrDefault($decoded, 'pdf_template', PdfStyle::TEMPLATES, PdfStyle::TEMPLATE_STANDARD),
            pdfSpacing: $this->enumOrDefault($decoded, 'pdf_spacing', PdfStyle::SPACINGS, PdfStyle::SPACING_MEDIUM),
            pdfHeadingFont: $this->enumOrDefault($decoded, 'pdf_heading_font', PdfStyle::FONTS, PdfStyle::FONT_GOTHIC),
        ));

        return $this->json->create(CompanySettingsResponse::toArray($settings));
    }

    /**
     * Validates the billing-default integers (Issue #268). Absent / null values
     * are allowed; out-of-range values raise a 422 with field-scoped errors.
     *
     * @param array<string, mixed> $body
     *
     * @throws ValidationException
     */
    private function validateBillingDefaults(array $body): void
    {
        $ranges = [
            'default_quote_validity_days'  => [1, 3650],
            'default_payment_closing_day'  => [1, 31],
            'default_payment_month_offset' => [0, 12],
            'default_payment_pay_day'      => [1, 31],
        ];

        $errors = [];
        foreach ($ranges as $key => [$min, $max]) {
            if (!$this->isNullOrIntInRange($body[$key] ?? null, $min, $max)) {
                $errors[] = new ValidationError('body.' . $key, sprintf('%s must be an integer between %d and %d.', $key, $min, $max), 'out_of_range');
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    private function isNullOrIntInRange(mixed $value, int $min, int $max): bool
    {
        if ($value === null) {
            return true;
        }

        return is_int($value) && $value >= $min && $value <= $max;
    }

    /**
     * Validates the PDF appearance enums (Issue #449). Absent fields keep the
     * default; a present value outside the registered set raises a 422.
     *
     * @param array<string, mixed> $body
     *
     * @throws ValidationException
     */
    private function validatePdfAppearance(array $body): void
    {
        $fields = [
            'pdf_template'     => PdfStyle::TEMPLATES,
            'pdf_spacing'      => PdfStyle::SPACINGS,
            'pdf_heading_font' => PdfStyle::FONTS,
        ];

        $errors = [];
        foreach ($fields as $key => $allowed) {
            $value = $body[$key] ?? null;
            if ($value !== null && (!is_string($value) || !in_array($value, $allowed, true))) {
                $errors[] = new ValidationError('body.' . $key, sprintf('%s must be one of: %s.', $key, implode(', ', $allowed)), 'invalid_value');
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Returns the request value when it is a registered enum member, otherwise
     * the default. Invalid values are already rejected by {@see validatePdfAppearance}.
     *
     * @param array<string, mixed> $body
     * @param list<string>         $allowed
     */
    private function enumOrDefault(array $body, string $key, array $allowed, string $default): string
    {
        $value = $body[$key] ?? null;

        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }
}
