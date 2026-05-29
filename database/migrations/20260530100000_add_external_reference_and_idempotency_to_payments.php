<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds external_reference (the originating system's reconciliation id, e.g. NeNe
 * Clear) and idempotency_key (for safe retried writes) to payments — ADR 0009.
 * idempotency_key is unique; it is null for manual operator payments (multiple
 * NULLs are allowed by the unique index).
 */
final class AddExternalReferenceAndIdempotencyToPayments extends AbstractMigration
{
    public function change(): void
    {
        $this->table('payments')
            ->addColumn('external_reference', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('idempotency_key', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addIndex(['idempotency_key'], ['unique' => true, 'name' => 'uniq_payments_idempotency_key'])
            ->update();
    }
}
