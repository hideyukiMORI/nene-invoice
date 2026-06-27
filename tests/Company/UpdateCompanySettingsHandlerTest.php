<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Company;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use Nene2\Validation\ValidationException;
use NeneInvoice\Company\UpdateCompanySettingsHandler;
use NeneInvoice\Company\UpdateCompanySettingsUseCase;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryCompanySettingsRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Boundary coverage for the billing-default ranges validated in
 * {@see UpdateCompanySettingsHandler::validateBillingDefaults} (Issue #268):
 * validity 1–3650, closing-day 1–31, month-offset 0–12, pay-day 1–31. Null is
 * always allowed; out-of-range and non-integer values are 422s.
 */
final class UpdateCompanySettingsHandlerTest extends TestCase
{
    private Psr17Factory $psr17;
    private InMemoryCompanySettingsRepository $repository;
    private UpdateCompanySettingsHandler $handler;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
        $holder = new RequestScopedHolder();
        $holder->set(1);
        $this->repository = new InMemoryCompanySettingsRepository($holder);
        $useCase = new UpdateCompanySettingsUseCase(
            $this->repository,
            new ImmediateTransactionManager(),
            fn () => $this->repository,
            fn () => new RecordingAuditRecorder(),
            $holder,
        );
        $this->handler = new UpdateCompanySettingsHandler($useCase, new JsonResponseFactory($this->psr17, $this->psr17));
    }

    /** @param array<string, mixed> $body */
    private function request(array $body): ServerRequestInterface
    {
        return $this->psr17->createServerRequest('PUT', '/admin/company-settings')
            ->withAttribute('nene2.auth.claims', ['sub' => 7, 'org' => 1, 'role' => 'admin'])
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode($body)));
    }

    #[DataProvider('outOfRangeCases')]
    public function test_rejects_billing_default_out_of_range(string $field, int $value): void
    {
        $this->expectException(ValidationException::class);
        $this->handler->handle($this->request(['legal_name' => 'Example KK', $field => $value]));
    }

    /** @return iterable<string, array{string, int}> */
    public static function outOfRangeCases(): iterable
    {
        yield 'validity below min (0)'    => ['default_quote_validity_days', 0];
        yield 'validity above max (3651)' => ['default_quote_validity_days', 3651];
        yield 'closing below min (0)'     => ['default_payment_closing_day', 0];
        yield 'closing above max (32)'    => ['default_payment_closing_day', 32];
        yield 'month offset below min'    => ['default_payment_month_offset', -1];
        yield 'month offset above max'    => ['default_payment_month_offset', 13];
        yield 'pay day below min (0)'     => ['default_payment_pay_day', 0];
        yield 'pay day above max (32)'    => ['default_payment_pay_day', 32];
    }

    #[DataProvider('inRangeBoundaries')]
    public function test_accepts_billing_default_at_range_boundary(string $field, int $value): void
    {
        $response = $this->handler->handle($this->request(['legal_name' => 'Example KK', $field => $value]));

        self::assertSame(200, $response->getStatusCode());
    }

    /** @return iterable<string, array{string, int}> */
    public static function inRangeBoundaries(): iterable
    {
        yield 'validity min (1)'      => ['default_quote_validity_days', 1];
        yield 'validity max (3650)'   => ['default_quote_validity_days', 3650];
        yield 'closing min (1)'       => ['default_payment_closing_day', 1];
        yield 'closing max (31)'      => ['default_payment_closing_day', 31];
        yield 'month offset min (0)'  => ['default_payment_month_offset', 0];
        yield 'month offset max (12)' => ['default_payment_month_offset', 12];
        yield 'pay day min (1)'       => ['default_payment_pay_day', 1];
        yield 'pay day max (31)'      => ['default_payment_pay_day', 31];
    }

    public function test_null_billing_defaults_are_accepted(): void
    {
        $response = $this->handler->handle($this->request([
            'legal_name'                   => 'Example KK',
            'default_quote_validity_days'  => null,
            'default_payment_closing_day'  => null,
            'default_payment_month_offset' => null,
            'default_payment_pay_day'      => null,
        ]));

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_non_integer_billing_default_is_rejected(): void
    {
        // A JSON string ("15") is not an int → out_of_range, not silently coerced.
        $this->expectException(ValidationException::class);
        $this->handler->handle($this->request(['legal_name' => 'Example KK', 'default_payment_closing_day' => '15']));
    }
}
