<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Auth;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use NeneInvoice\Auth\PdoLoginThrottle;
use NeneInvoice\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class PdoLoginThrottleTest extends TestCase
{
    private PdoLoginThrottle $throttle;

    protected function setUp(): void
    {
        $config = new DatabaseConfig(
            url: null,
            environment: 'test',
            adapter: 'sqlite',
            host: 'localhost',
            port: 1,
            name: ':memory:',
            user: 'sqlite',
            password: '',
            charset: 'utf8',
        );
        $factory = new PdoConnectionFactory($config);
        $pdo     = $factory->create();

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/login_attempts.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->throttle = new PdoLoginThrottle(new PdoDatabaseQueryExecutor($factory, $pdo), new FixedClock());
    }

    public function test_counts_only_failures_at_or_after_the_cutoff(): void
    {
        $this->throttle->recordFailure('203.0.113.1');
        $this->throttle->recordFailure('203.0.113.1');
        $this->throttle->recordFailure('203.0.113.2');

        self::assertSame(2, $this->throttle->countFailuresSince('203.0.113.1', '1970-01-01 00:00:00'));
        self::assertSame(1, $this->throttle->countFailuresSince('203.0.113.2', '1970-01-01 00:00:00'));
        self::assertSame(0, $this->throttle->countFailuresSince('203.0.113.1', '2999-01-01 00:00:00'));
    }

    public function test_clear_removes_only_the_given_ip(): void
    {
        $this->throttle->recordFailure('203.0.113.1');
        $this->throttle->recordFailure('203.0.113.2');

        $this->throttle->clearFailures('203.0.113.1');

        self::assertSame(0, $this->throttle->countFailuresSince('203.0.113.1', '1970-01-01 00:00:00'));
        self::assertSame(1, $this->throttle->countFailuresSince('203.0.113.2', '1970-01-01 00:00:00'));
    }
}
