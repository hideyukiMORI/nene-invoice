<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\RecurringInvoice;

use NeneInvoice\RecurringInvoice\GenerateDueRecurringInvoicesResult;
use NeneInvoice\RecurringInvoice\RecurringDueCheckMiddleware;
use NeneInvoice\RecurringInvoice\RecurringDueRunnerInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class RecurringDueCheckMiddlewareTest extends TestCase
{
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
    }

    private function okHandler(): RequestHandlerInterface
    {
        return new class ($this->psr17) implements RequestHandlerInterface {
            public function __construct(private Psr17Factory $psr17)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->psr17->createResponse(200)->withHeader('X-Test', 'ok');
            }
        };
    }

    public function test_runs_due_check_after_admin_request_and_returns_response(): void
    {
        $runner = new class () implements RecurringDueRunnerInterface {
            public int $calls = 0;

            public function runForCurrentOrg(): ?GenerateDueRecurringInvoicesResult
            {
                ++$this->calls;

                return null;
            }
        };

        $middleware = new RecurringDueCheckMiddleware($runner);
        $response   = $middleware->process($this->psr17->createServerRequest('GET', '/admin/dashboard'), $this->okHandler());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $response->getHeaderLine('X-Test'));
        self::assertSame(1, $runner->calls);
    }

    public function test_skips_non_admin_paths(): void
    {
        $runner = new class () implements RecurringDueRunnerInterface {
            public int $calls = 0;

            public function runForCurrentOrg(): ?GenerateDueRecurringInvoicesResult
            {
                ++$this->calls;

                return null;
            }
        };

        $middleware = new RecurringDueCheckMiddleware($runner);

        foreach (['/api/invoices', '/auth/login', '/health', '/'] as $path) {
            $response = $middleware->process($this->psr17->createServerRequest('GET', $path), $this->okHandler());
            self::assertSame(200, $response->getStatusCode());
        }

        self::assertSame(0, $runner->calls);
    }

    public function test_swallows_runner_failure_and_still_returns_response(): void
    {
        $runner = new class () implements RecurringDueRunnerInterface {
            public function runForCurrentOrg(): ?GenerateDueRecurringInvoicesResult
            {
                throw new RuntimeException('boom');
            }
        };

        $middleware = new RecurringDueCheckMiddleware($runner);
        $response   = $middleware->process($this->psr17->createServerRequest('GET', '/admin/invoices'), $this->okHandler());

        self::assertSame(200, $response->getStatusCode());
    }
}
