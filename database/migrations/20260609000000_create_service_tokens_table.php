<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateServiceTokensTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('service_tokens')
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('jti', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('subject', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('label', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('scopes', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('created_by', 'integer', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addColumn('revoked_at', 'datetime', ['null' => true])
            ->addIndex(['jti'], ['unique' => true, 'name' => 'uniq_service_tokens_jti'])
            ->addIndex(['organization_id'], ['name' => 'idx_service_tokens_organization_id'])
            ->create();
    }
}
