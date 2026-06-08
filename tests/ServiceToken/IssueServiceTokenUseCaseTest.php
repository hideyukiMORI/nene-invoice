<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\ServiceToken;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ServiceToken\IssueServiceTokenInput;
use NeneInvoice\ServiceToken\IssueServiceTokenUseCase;
use NeneInvoice\ServiceToken\PdoServiceTokenRepository;
use NeneInvoice\ServiceToken\ServiceTokenRepositoryInterface;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use NeneInvoice\Tests\Support\RecordingTokenIssuer;
use PHPUnit\Framework\TestCase;

final class IssueServiceTokenUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;
    private RecordingAuditRecorder $audit;
    private PdoServiceTokenRepository $repository;
    private RecordingTokenIssuer $issuer;
    private IssueServiceTokenUseCase $useCase;

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
        $pdo = $factory->create();
        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/service_tokens.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $executor = new PdoDatabaseQueryExecutor($factory, $pdo);
        $this->orgId = new RequestScopedHolder();
        $this->orgId->set(5);
        $this->repository = new PdoServiceTokenRepository($executor, $this->orgId);
        $this->audit = new RecordingAuditRecorder();

        $this->issuer = new RecordingTokenIssuer();

        $orgHolder = $this->orgId;
        $audit = $this->audit;

        $this->useCase = new IssueServiceTokenUseCase(
            $this->issuer,
            new ImmediateTransactionManager($executor),
            static fn (DatabaseQueryExecutorInterface $exec): ServiceTokenRepositoryInterface => new PdoServiceTokenRepository($exec, $orgHolder),
            static fn (DatabaseQueryExecutorInterface $exec) => $audit,
            new FixedClock('2026-06-09T00:00:00Z'),
            $orgHolder,
        );
    }

    public function test_issue_persists_registry_row_and_returns_plaintext_once(): void
    {
        $result = $this->useCase->execute(7, new IssueServiceTokenInput(
            label: 'NeNe Clear prod',
            scopes: ['read:invoices', 'write:payments'],
            subject: 'service:clear',
            ttlSeconds: 2_592_000,
        ));

        // Plaintext token returned exactly once; jti embedded by the issuer.
        self::assertNotNull($result->token->id);
        self::assertSame('signed.' . $result->token->jti, $result->plaintextToken);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $result->token->jti);

        // Persisted row carries metadata, org from the holder, and actor.
        $stored = $this->repository->findById($result->token->id);
        self::assertNotNull($stored);
        self::assertSame(5, $stored->organizationId);
        self::assertSame('NeNe Clear prod', $stored->label);
        self::assertSame(['read:invoices', 'write:payments'], $stored->scopes);
        self::assertSame(7, $stored->createdBy);
        self::assertNull($stored->revokedAt);
        // 30-day TTL from the fixed clock.
        self::assertSame('2026-07-09 00:00:00', $stored->expiresAt);
    }

    public function test_issued_jwt_claims_carry_org_scopes_jti_and_expiry(): void
    {
        $this->useCase->execute(7, new IssueServiceTokenInput(
            label: 'cli',
            scopes: ['read:invoices'],
            subject: 'service:clear',
            ttlSeconds: 3600,
        ));

        $claims = $this->issuer->claims;
        self::assertSame('service:clear', $claims['sub']);
        self::assertSame(5, $claims['org']);
        self::assertSame(['read:invoices'], $claims['scopes']);
        self::assertArrayHasKey('jti', $claims);
        $expectedIat = (new \DateTimeImmutable('2026-06-09T00:00:00Z'))->getTimestamp();
        self::assertSame($expectedIat, $claims['iat']);
        self::assertSame($expectedIat + 3600, $claims['exp']);
    }

    public function test_audit_record_excludes_secret_material(): void
    {
        $this->useCase->execute(7, new IssueServiceTokenInput(
            label: 'audited',
            scopes: ['read:invoices'],
            subject: 'service:clear',
            ttlSeconds: 3600,
        ));

        self::assertCount(1, $this->audit->records);
        $record = $this->audit->records[0];
        self::assertSame('service_token.issued', $record['action']);
        self::assertSame('service_token', $record['entity_type']);
        self::assertSame(5, $record['organization_id']);
        self::assertSame(7, $record['actor_user_id']);
        self::assertArrayNotHasKey('token', $record['after']);
        self::assertArrayNotHasKey('jti', $record['after']);
        self::assertSame('audited', $record['after']['label']);
    }
}
