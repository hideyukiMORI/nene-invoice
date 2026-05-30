<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateLoginAttemptsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('login_attempts')
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addIndex(['ip_address', 'created_at'], ['name' => 'idx_login_attempts_ip_time'])
            ->create();
    }
}
