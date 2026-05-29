<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQuotesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('quotes')
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('client_id', 'integer', ['null' => false])
            ->addColumn('quote_number', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('status', 'string', ['limit' => 16, 'null' => false, 'default' => 'draft'])
            ->addColumn('issued_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('valid_until', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('subtotal_cents', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('tax_cents', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('total_cents', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('notes', 'text', ['null' => true, 'default' => null])
            ->addColumn('is_deleted', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['organization_id'], ['name' => 'idx_quotes_organization_id'])
            ->addIndex(['organization_id', 'quote_number'], ['unique' => true, 'name' => 'uniq_quotes_org_number'])
            ->create();
    }
}
