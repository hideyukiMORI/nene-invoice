<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCompanySettingsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('company_settings')
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('legal_name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('address', 'text', ['null' => true, 'default' => null])
            ->addColumn('phone', 'string', ['limit' => 32, 'null' => true, 'default' => null])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('registration_number', 'string', ['limit' => 14, 'null' => true, 'default' => null])
            ->addColumn('bank_name', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('bank_branch', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('account_type', 'string', ['limit' => 32, 'null' => true, 'default' => null])
            ->addColumn('account_number', 'string', ['limit' => 64, 'null' => true, 'default' => null])
            ->addColumn('logo_url', 'string', ['limit' => 1024, 'null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['organization_id'], ['unique' => true, 'name' => 'uniq_company_settings_organization_id'])
            ->create();
    }
}
