<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Company;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Company\CompanySettings;
use NeneInvoice\Company\PdoCompanySettingsRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PdoCompanySettingsRepository: upsert logic (save on empty → insert,
 * save again → update), org isolation, and field round-trip. The organization is
 * read from the request-scoped holder; save() forces it.
 */
final class PdoCompanySettingsRepositoryTest extends TestCase
{
    private PdoCompanySettingsRepository $repo;
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;

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

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/company_settings.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repo = new PdoCompanySettingsRepository(new PdoDatabaseQueryExecutor($factory, $pdo), $this->holder);
    }

    public function test_find_returns_null_when_not_set(): void
    {
        self::assertNull($this->repo->find());
    }

    public function test_save_inserts_and_read_back(): void
    {
        $settings = new CompanySettings(
            organizationId: 1,
            legalName: '株式会社テスト',
            address: '東京都渋谷区1-1-1',
            phone: '03-0000-0000',
            email: 'info@example.com',
            registrationNumber: 'T1234567890123',
            bankName: 'テスト銀行',
            bankBranch: '渋谷支店',
            accountType: '普通',
            accountNumber: '1234567',
        );

        $this->repo->save($settings);

        $found = $this->repo->find();
        self::assertNotNull($found);
        self::assertSame('株式会社テスト', $found->legalName);
        self::assertSame('T1234567890123', $found->registrationNumber);
        self::assertSame('テスト銀行', $found->bankName);
        self::assertSame('1234567', $found->accountNumber);
    }

    public function test_save_updates_existing_record(): void
    {
        $this->repo->save(new CompanySettings(organizationId: 1, legalName: '旧社名'));
        $this->repo->save(new CompanySettings(organizationId: 1, legalName: '新社名', registrationNumber: 'T9999999999999'));

        $found = $this->repo->find();
        self::assertNotNull($found);
        self::assertSame('新社名', $found->legalName);
        self::assertSame('T9999999999999', $found->registrationNumber);
    }

    public function test_orgs_are_isolated(): void
    {
        $this->holder->set(1);
        $this->repo->save(new CompanySettings(organizationId: 1, legalName: 'Org 1'));
        $this->holder->set(2);
        $this->repo->save(new CompanySettings(organizationId: 2, legalName: 'Org 2'));

        $this->holder->set(1);
        $orgOne = $this->repo->find();
        self::assertNotNull($orgOne);
        self::assertSame('Org 1', $orgOne->legalName);

        $this->holder->set(2);
        $orgTwo = $this->repo->find();
        self::assertNotNull($orgTwo);
        self::assertSame('Org 2', $orgTwo->legalName);

        $this->holder->set(3);
        self::assertNull($this->repo->find());
    }

    public function test_optional_fields_are_nullable(): void
    {
        $this->repo->save(new CompanySettings(organizationId: 1, legalName: 'Minimal'));

        $found = $this->repo->find();
        self::assertNotNull($found);
        self::assertNull($found->address);
        self::assertNull($found->registrationNumber);
        self::assertNull($found->bankName);
    }
}
