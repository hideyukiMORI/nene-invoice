<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Company;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Company\CompanySettingsNotFoundException;
use NeneInvoice\Company\GetCompanySettingsUseCase;
use NeneInvoice\Company\InvalidRegistrationNumberException;
use NeneInvoice\Company\PdoCompanySettingsRepository;
use NeneInvoice\Company\UpdateCompanySettingsInput;
use NeneInvoice\Company\UpdateCompanySettingsUseCase;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class CompanySettingsTest extends TestCase
{
    private PdoCompanySettingsRepository $repository;
    private RecordingAuditRecorder $audit;
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;

    protected function setUp(): void
    {
        $this->audit = new RecordingAuditRecorder();

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

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/company_settings.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repository = new PdoCompanySettingsRepository(new PdoDatabaseQueryExecutor($factory, $pdo), $this->holder);
    }

    public function test_get_throws_when_not_configured(): void
    {
        $this->expectException(CompanySettingsNotFoundException::class);
        (new GetCompanySettingsUseCase($this->repository, $this->holder))->execute();
    }

    public function test_update_upserts_and_get_returns_settings(): void
    {
        $update = new UpdateCompanySettingsUseCase($this->repository, $this->audit, $this->holder);

        $created = $update->execute(5, new UpdateCompanySettingsInput(
            legalName: '株式会社あやね',
            registrationNumber: 'T1234567890123',
            bankName: 'みずほ',
        ));
        self::assertSame('株式会社あやね', $created->legalName);
        self::assertSame('T1234567890123', $created->registrationNumber);

        // Upsert: a second update replaces, not duplicates.
        $update->execute(5, new UpdateCompanySettingsInput(legalName: '株式会社あやね（改）'));

        $fetched = (new GetCompanySettingsUseCase($this->repository, $this->holder))->execute();
        self::assertSame('株式会社あやね（改）', $fetched->legalName);
        self::assertNull($fetched->registrationNumber);
    }

    public function test_update_rejects_malformed_registration_number(): void
    {
        $this->expectException(InvalidRegistrationNumberException::class);
        (new UpdateCompanySettingsUseCase($this->repository, $this->audit, $this->holder))->execute(5, new UpdateCompanySettingsInput(
            legalName: 'X',
            registrationNumber: 'bad',
        ));
    }

    public function test_settings_are_scoped_per_organization(): void
    {
        $update = new UpdateCompanySettingsUseCase($this->repository, $this->audit, $this->holder);
        $this->holder->set(1);
        $update->execute(5, new UpdateCompanySettingsInput(legalName: 'Org One'));
        $this->holder->set(2);
        $update->execute(5, new UpdateCompanySettingsInput(legalName: 'Org Two'));

        $get = new GetCompanySettingsUseCase($this->repository, $this->holder);
        $this->holder->set(1);
        self::assertSame('Org One', $get->execute()->legalName);
        $this->holder->set(2);
        self::assertSame('Org Two', $get->execute()->legalName);
    }
}
