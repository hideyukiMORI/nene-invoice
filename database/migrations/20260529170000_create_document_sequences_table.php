<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateDocumentSequencesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('document_sequences')
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('doc_type', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('year', 'integer', ['null' => false])
            ->addColumn('last_number', 'integer', ['null' => false, 'default' => 0])
            ->addIndex(['organization_id', 'doc_type', 'year'], ['unique' => true, 'name' => 'uniq_document_sequences_scope'])
            ->create();
    }
}
