<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRecurringInvoicesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('recurring_invoices')
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('client_id', 'integer', ['null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('frequency', 'string', ['limit' => 16, 'null' => false])
            ->addColumn('subtotal_cents', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('tax_cents', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('total_cents', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('next_run_on', 'date', ['null' => false])
            ->addColumn('last_run_on', 'date', ['null' => true])
            ->addColumn('is_active', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('is_deleted', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['organization_id'], ['name' => 'idx_recurring_invoices_organization_id'])
            ->addIndex(['organization_id', 'is_active', 'next_run_on'], ['name' => 'idx_recurring_invoices_due'])
            ->create();
    }
}
