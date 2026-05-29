<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateLineItemsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('line_items')
            ->addColumn('parent_type', 'string', ['limit' => 16, 'null' => false])
            ->addColumn('parent_id', 'integer', ['null' => false])
            ->addColumn('description', 'string', ['limit' => 1024, 'null' => false])
            ->addColumn('quantity', 'integer', ['null' => false])
            ->addColumn('unit_price_cents', 'integer', ['null' => false])
            ->addColumn('tax_rate_bps', 'integer', ['null' => false])
            ->addColumn('sort_order', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['parent_type', 'parent_id'], ['name' => 'idx_line_items_parent'])
            ->create();
    }
}
