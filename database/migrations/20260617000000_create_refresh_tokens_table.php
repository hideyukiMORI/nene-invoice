<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Persists rotating refresh tokens for silent re-authentication (ADR 0014).
 *
 * Only the SHA-256 hash of the opaque token is stored — never the plaintext —
 * mirroring the no-plaintext posture of `service_tokens`. `family_id` ties a
 * rotation lineage together so that presenting an already-used or revoked token
 * (`used_at` / `revoked_at` set) can invalidate the whole family (reuse defense).
 */
final class CreateRefreshTokensTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('refresh_tokens')
            ->addColumn('organization_id', 'integer', ['null' => true])
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('family_id', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('token_hash', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('issued_at', 'datetime', ['null' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addColumn('used_at', 'datetime', ['null' => true])
            ->addColumn('revoked_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addIndex(['token_hash'], ['unique' => true, 'name' => 'uniq_refresh_tokens_token_hash'])
            ->addIndex(['family_id'], ['name' => 'idx_refresh_tokens_family_id'])
            ->addIndex(['user_id'], ['name' => 'idx_refresh_tokens_user_id'])
            ->create();
    }
}
