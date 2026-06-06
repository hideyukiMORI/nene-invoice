<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateItemsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('items')
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('default_unit_price_cents', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('default_tax_rate_bps', 'integer', ['null' => false, 'default' => 1000])
            ->addColumn('is_deleted', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['organization_id'], ['name' => 'idx_items_organization_id'])
            ->create();
    }
}
