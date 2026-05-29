<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePaymentsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('payments')
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('invoice_id', 'integer', ['null' => false])
            ->addColumn('amount_cents', 'integer', ['null' => false])
            ->addColumn('paid_at', 'datetime', ['null' => false])
            ->addColumn('method', 'string', ['limit' => 32, 'null' => true, 'default' => null])
            ->addColumn('note', 'text', ['null' => true, 'default' => null])
            ->addColumn('is_deleted', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['organization_id'], ['name' => 'idx_payments_organization_id'])
            ->addIndex(['invoice_id'], ['name' => 'idx_payments_invoice_id'])
            ->create();
    }
}
