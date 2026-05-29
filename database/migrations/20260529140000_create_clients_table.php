<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateClientsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('clients')
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('contact_name', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('billing_address', 'text', ['null' => true, 'default' => null])
            ->addColumn('registration_number', 'string', ['limit' => 14, 'null' => true, 'default' => null])
            ->addColumn('is_deleted', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['organization_id'], ['name' => 'idx_clients_organization_id'])
            ->create();
    }
}
