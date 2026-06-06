<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneInvoice\Auth\AuthContext;
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
        private UpdateCompanySettingsUseCase $useCase,
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

        $this->validateBillingDefaults($decoded);

        $settings = $this->useCase->execute(AuthContext::userId($request), new UpdateCompanySettingsInput(
            legalName: $legalName,
            address: $this->optional($decoded, 'address'),
            phone: $this->optional($decoded, 'phone'),
            email: $this->optional($decoded, 'email'),
            registrationNumber: $this->optional($decoded, 'registration_number'),
            bankName: $this->optional($decoded, 'bank_name'),
            bankBranch: $this->optional($decoded, 'bank_branch'),
            accountType: $this->optional($decoded, 'account_type'),
            accountNumber: $this->optional($decoded, 'account_number'),
            logoUrl: $this->optional($decoded, 'logo_url'),
            defaultQuoteValidityDays: $this->optionalInt($decoded, 'default_quote_validity_days'),
            defaultPaymentClosingDay: $this->optionalInt($decoded, 'default_payment_closing_day'),
            defaultPaymentMonthOffset: $this->optionalInt($decoded, 'default_payment_month_offset'),
            defaultPaymentPayDay: $this->optionalInt($decoded, 'default_payment_pay_day'),
        ));

        return $this->json->create(CompanySettingsResponse::toArray($settings));
    }

    /** @param array<string, mixed> $body */
    private function optional(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $body */
    private function optionalInt(array $body, string $key): ?int
    {
        $value = $body[$key] ?? null;

        return is_int($value) ? $value : null;
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
}
