<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds an optional reading/index field to clients (#314). Holds furigana (kana)
 * or latin/romaji so the name can be sorted and suggested for both Japanese and
 * non-Japanese clients.
 */
final class AddNameKanaToClients extends AbstractMigration
{
    public function change(): void
    {
        $this->table('clients')
            ->addColumn('name_kana', 'string', [
                'limit'   => 255,
                'null'    => true,
                'default' => null,
                'after'   => 'name',
            ])
            ->update();
    }
}
