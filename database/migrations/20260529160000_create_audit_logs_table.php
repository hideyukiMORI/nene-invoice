<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAuditLogsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('audit_logs')
            ->addColumn('actor_user_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('organization_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('action', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('entity_type', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('entity_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('before_json', 'text', ['null' => true, 'default' => null])
            ->addColumn('after_json', 'text', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addIndex(['organization_id'], ['name' => 'idx_audit_logs_organization_id'])
            ->addIndex(['entity_type', 'entity_id'], ['name' => 'idx_audit_logs_entity'])
            ->create();
    }
}
