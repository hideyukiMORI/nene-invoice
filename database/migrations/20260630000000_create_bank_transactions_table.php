<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateBankTransactionsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('bank_transactions')
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('value_date', 'date', ['null' => false])
            ->addColumn('direction', 'string', ['limit' => 8, 'null' => false])
            ->addColumn('amount_cents', 'integer', ['null' => false])
            ->addColumn('payer_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('description', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('bank_reference', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 16, 'null' => false, 'default' => 'unmatched'])
            ->addColumn('matched_invoice_id', 'integer', ['null' => true])
            ->addColumn('matched_payment_id', 'integer', ['null' => true])
            ->addColumn('imported_at', 'datetime', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['organization_id'], ['name' => 'idx_bank_transactions_organization_id'])
            ->addIndex(['organization_id', 'status'], ['name' => 'idx_bank_transactions_status'])
            ->addIndex(['organization_id', 'bank_reference'], ['name' => 'idx_bank_transactions_bank_reference'])
            ->create();
    }
}
