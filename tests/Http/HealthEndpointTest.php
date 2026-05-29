<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Http;

use NeneInvoice\Http\RuntimeContainerFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * End-to-end smoke test of the consumer bootstrap: building the container
 * resolves the application handler, and `GET /health` returns an OK payload.
 */
final class HealthEndpointTest extends TestCase
{
    public function test_health_reports_ok_with_database_check_when_booted(): void
    {
        // phpunit.xml.dist configures a SQLite :memory: database, so the
        // DatabaseHealthCheck passes and the overall status is "ok".
        $container = (new RuntimeContainerFactory(dirname(__DIR__, 2)))->create();

        $application = $container->get(RequestHandlerInterface::class);
        self::assertInstanceOf(RequestHandlerInterface::class, $application);

        $request = (new Psr17Factory())->createServerRequest('GET', '/health');
        $response = $application->handle($request);

        self::assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        self::assertIsArray($payload);
        self::assertSame('ok', $payload['status'] ?? null);
        self::assertArrayHasKey('service', $payload);

        self::assertArrayHasKey('checks', $payload);
        $checks = $payload['checks'];
        self::assertIsArray($checks);
        self::assertSame('ok', $checks['database'] ?? null);
    }
}
