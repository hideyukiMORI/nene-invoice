<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTemplatesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('templates')
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('notes', 'text', ['null' => true, 'default' => null])
            ->addColumn('is_deleted', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['organization_id'], ['name' => 'idx_templates_organization_id'])
            ->create();
    }
}
