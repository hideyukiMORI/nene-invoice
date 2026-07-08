<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Organization;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Database\PdoDatabaseTransactionManager;
use NeneInvoice\Organization\CreateOrganizationInput;
use NeneInvoice\Organization\CreateOrganizationUseCase;
use NeneInvoice\Organization\PdoInitialAdminRepository;
use NeneInvoice\Organization\PdoOrganizationRepository;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use NeneInvoice\User\UserEmailConflictException;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end atomicity of `createOrganization` with an initial admin, exercised
 * against a real (file-backed) SQLite transaction so a rollback is observable.
 *
 * The security-critical property: org + admin commit together or not at all. If
 * the admin insert fails (duplicate email), the organization must NOT survive —
 * no orphan tenant.
 */
final class CreateOrganizationAtomicityTest extends TestCase
{
    private string $dbPath;
    private PdoConnectionFactory $factory;
    private PdoDatabaseQueryExecutor $executor;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'nene_org_atomic_');
        self::assertIsString($path);
        $this->dbPath = $path;

        $config = new DatabaseConfig(
            url: null,
            environment: 'test',
            adapter: 'sqlite',
            host: 'localhost',
            port: 1,
            name: $this->dbPath,
            user: 'sqlite',
            password: '',
            charset: 'utf8',
        );
        $this->factory = new PdoConnectionFactory($config);

        $pdo = $this->factory->create();
        foreach (['organizations.sql', 'users.sql'] as $file) {
            $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/' . $file);
            self::assertIsString($schema);
            $pdo->exec($schema);
        }

        $this->executor = new PdoDatabaseQueryExecutor($this->factory, $pdo);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function useCase(): CreateOrganizationUseCase
    {
        return new CreateOrganizationUseCase(
            new PdoDatabaseTransactionManager($this->factory),
            fn ($exec) => new PdoOrganizationRepository($exec, new FixedClock()),
            new RecordingAuditRecorder(),
            fn ($exec) => new PdoInitialAdminRepository($exec, new FixedClock()),
        );
    }

    public function test_org_and_admin_commit_together(): void
    {
        $org = $this->useCase()->execute(1, new CreateOrganizationInput('Beta KK', 'beta', 'free', 'owner@beta.example', 'correct horse battery'));

        $orgRow = $this->executor->fetchOne('SELECT id FROM organizations WHERE slug = ?', ['beta']);
        self::assertNotNull($orgRow);

        $userRow = $this->executor->fetchOne('SELECT role, organization_id, status, password_hash FROM users WHERE email = ?', ['owner@beta.example']);
        self::assertNotNull($userRow);
        self::assertSame('admin', (string) $userRow['role']);
        self::assertSame($org->id, (int) $userRow['organization_id']);
        self::assertSame('active', (string) $userRow['status']);
        self::assertTrue(password_verify('correct horse battery', (string) $userRow['password_hash']));
    }

    public function test_duplicate_admin_email_rolls_back_the_organization(): void
    {
        // A user already owns this email in another tenant.
        $now = '2026-05-29 00:00:00';
        $this->executor->execute(
            'INSERT INTO users (email, password_hash, role, organization_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            ['taken@example.com', 'hashed', 'admin', 1, 'active', $now, $now],
        );

        try {
            $this->useCase()->execute(9, new CreateOrganizationInput('Newco', 'newco', 'free', 'taken@example.com', 'correct horse battery'));
            self::fail('Expected UserEmailConflictException.');
        } catch (UserEmailConflictException) {
            // expected
        }

        // The organization insert must have rolled back — no orphan tenant.
        $orgRow = $this->executor->fetchOne('SELECT id FROM organizations WHERE slug = ?', ['newco']);
        self::assertNull($orgRow);
    }
}
