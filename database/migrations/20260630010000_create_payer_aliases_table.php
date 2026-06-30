<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePayerAliasesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('payer_aliases')
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('normalized_name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('client_id', 'integer', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['organization_id', 'normalized_name'], ['unique' => true, 'name' => 'uq_payer_aliases_org_name'])
            ->addIndex(['organization_id'], ['name' => 'idx_payer_aliases_organization_id'])
            ->create();
    }
}
