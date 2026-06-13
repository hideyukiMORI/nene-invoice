<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePaymentLinksTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('payment_links')
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('invoice_id', 'integer', ['null' => false])
            ->addColumn('token_hash', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('gateway', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('gateway_session_id', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 16, 'null' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addColumn('paid_at', 'datetime', ['null' => true])
            ->addColumn('revoked_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['token_hash'], ['unique' => true, 'name' => 'uniq_payment_links_token_hash'])
            ->addIndex(['invoice_id'], ['name' => 'idx_payment_links_invoice_id'])
            ->addIndex(['gateway_session_id'], ['name' => 'idx_payment_links_gateway_session_id'])
            ->addIndex(['organization_id'], ['name' => 'idx_payment_links_organization_id'])
            ->create();
    }
}
