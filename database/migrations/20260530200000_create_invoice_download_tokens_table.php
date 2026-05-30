<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateInvoiceDownloadTokensTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('invoice_download_tokens')
            ->addColumn('invoice_id', 'integer', ['null' => false])
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('token_hash', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addIndex(['token_hash'], ['unique' => true, 'name' => 'uniq_download_tokens_hash'])
            ->addIndex(['invoice_id'], ['name' => 'idx_download_tokens_invoice_id'])
            ->create();
    }
}
