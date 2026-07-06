<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\ServiceToken;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ServiceToken\PdoServiceTokenRepository;
use NeneInvoice\ServiceToken\RevokeServiceTokenUseCase;
use NeneInvoice\ServiceToken\ServiceToken;
use NeneInvoice\ServiceToken\ServiceTokenNotFoundException;
use NeneInvoice\ServiceToken\ServiceTokenRepositoryInterface;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class RevokeServiceTokenUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;
    private RecordingAuditRecorder $audit;
    private PdoServiceTokenRepository $repository;
    private RevokeServiceTokenUseCase $useCase;

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

        $orgHolder = $this->orgId;
        $audit = $this->audit;

        $this->useCase = new RevokeServiceTokenUseCase(
            new ImmediateTransactionManager($executor),
            static fn (DatabaseQueryExecutorInterface $exec): ServiceTokenRepositoryInterface => new PdoServiceTokenRepository($exec, $orgHolder),
            $audit,
            new FixedClock('2026-06-09T12:00:00Z'),
            $orgHolder,
        );
    }

    private function seedToken(): int
    {
        return $this->repository->save(new ServiceToken(
            id: null,
            organizationId: 5,
            jti: 'jti-x',
            subject: 'service:clear',
            label: 'NeNe Clear',
            scopes: ['read:invoices'],
            createdBy: 7,
            createdAt: '2026-06-09 00:00:00',
            expiresAt: '2026-07-09 00:00:00',
            revokedAt: null,
        ));
    }

    public function test_revoke_marks_token_and_audits(): void
    {
        $id = $this->seedToken();

        $this->useCase->execute(7, $id);

        $stored = $this->repository->findById($id);
        self::assertNotNull($stored);
        self::assertSame('2026-06-09 12:00:00', $stored->revokedAt);

        self::assertCount(1, $this->audit->records);
        self::assertSame('service_token.revoked', $this->audit->records[0]['action']);
        self::assertSame('service_token', $this->audit->records[0]['entity_type']);
        self::assertSame($id, $this->audit->records[0]['entity_id']);
    }

    public function test_revoke_unknown_token_throws(): void
    {
        $this->expectException(ServiceTokenNotFoundException::class);
        $this->useCase->execute(7, 404);
    }
}
