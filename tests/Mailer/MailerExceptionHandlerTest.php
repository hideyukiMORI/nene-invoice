<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Mailer;

use Nene2\Error\ProblemDetailsResponseFactory;
use NeneInvoice\Mailer\MailerException;
use NeneInvoice\Mailer\MailerExceptionHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * A mail-transport failure must surface as a 502 `email-delivery-failed`
 * Problem Details response — never a generic 500 (#621): a prospect clicking
 * 送信 in the demo should see a graceful "could not deliver", not an outage.
 */
final class MailerExceptionHandlerTest extends TestCase
{
    private MailerExceptionHandler $handler;
    private Psr17Factory $psr17;
    private string $previousErrorLog;

    protected function setUp(): void
    {
        $this->psr17   = new Psr17Factory();
        $this->handler = new MailerExceptionHandler(
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-invoice.dev/problems/'),
        );

        // The handler error_log()s the transport error for ops; keep it out of
        // the test runner's output.
        $this->previousErrorLog = (string) ini_set('error_log', '/dev/null');
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousErrorLog);
    }

    public function test_supports_mailer_exception_only(): void
    {
        self::assertTrue($this->handler->supports(new MailerException('SMTP send failed')));
        self::assertFalse($this->handler->supports(new RuntimeException('anything else')));
    }

    public function test_maps_transport_failure_to_502_problem_details(): void
    {
        $request  = $this->psr17->createServerRequest('POST', 'https://host.example/admin/invoices/818/send-email');
        $response = $this->handler->handle(new MailerException('SMTP send failed: getaddrinfo for mailpit failed'), $request);

        self::assertSame(502, $response->getStatusCode());
        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));

        $payload = json_decode((string) $response->getBody(), true);
        self::assertIsArray($payload);
        self::assertSame('https://nene-invoice.dev/problems/email-delivery-failed', $payload['type']);
        self::assertSame(502, $payload['status']);
    }

    public function test_response_does_not_leak_transport_internals(): void
    {
        $request  = $this->psr17->createServerRequest('POST', 'https://host.example/admin/invoices/818/send-email');
        $response = $this->handler->handle(new MailerException('SMTP send failed: connect to smtp.internal.example:465'), $request);

        self::assertStringNotContainsString('smtp.internal.example', (string) $response->getBody());
    }
}
