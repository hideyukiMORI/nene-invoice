<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('role', 'string', ['limit' => 32, 'null' => false, 'default' => 'admin'])
            ->addColumn('organization_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('status', 'string', ['limit' => 16, 'null' => false, 'default' => 'active'])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['email'], ['unique' => true, 'name' => 'uniq_users_email'])
            ->addIndex(['organization_id'], ['name' => 'idx_users_organization_id'])
            ->create();
    }
}
