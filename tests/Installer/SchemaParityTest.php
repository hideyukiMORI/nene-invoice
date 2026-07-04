<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Installer;

use PHPUnit\Framework\TestCase;

/**
 * Keeps the reference DDL (`database/schema/mysql/schema.sql`) in sync with the
 * migrations (issue #482; scope revised by #562 決定A).
 *
 * Since #562 the Tier A web installer provisions via phinx
 * (`Nene2\Install\DatabaseSchemaApplier`), so migrations are the single source
 * of truth and installs can no longer drift. The hand-maintained DDL remains as
 * reference documentation only; this test keeps that reference honest — a stale
 * reference once hid the missing `refresh_tokens` table (ADR 0014). Whether to
 * drop the file (and this test) entirely is a follow-up decision.
 */
final class SchemaParityTest extends TestCase
{
    public function test_every_migration_created_table_exists_in_the_installer_schema(): void
    {
        $root = dirname(__DIR__, 2);
        $schema = (string) file_get_contents($root . '/database/schema/mysql/schema.sql');

        $missing = [];

        foreach (glob($root . '/database/migrations/*.php') ?: [] as $file) {
            $src = (string) file_get_contents($file);

            // Only migrations that CREATE a table need a matching DDL block;
            // column-adding / altering migrations call update()/save() instead.
            if (!str_contains($src, '->create()')) {
                continue;
            }

            if (preg_match("/->table\('([^']+)'/", $src, $m) !== 1) {
                continue;
            }

            $table = $m[1];

            if (preg_match('/CREATE TABLE[^;]*`' . preg_quote($table, '/') . '`/i', $schema) !== 1) {
                $missing[] = $table;
            }
        }

        self::assertSame(
            [],
            $missing,
            'Installer schema.sql is missing tables created by migrations: ' . implode(', ', $missing),
        );
    }

    public function test_every_migration_column_exists_in_the_installer_schema(): void
    {
        $root = dirname(__DIR__, 2);
        $schema = (string) file_get_contents($root . '/database/schema/mysql/schema.sql');

        $missing = [];

        foreach (glob($root . '/database/migrations/*.php') ?: [] as $file) {
            $src = (string) file_get_contents($file);

            if (preg_match_all("/->addColumn\('([^']+)'/", $src, $matches) === false) {
                continue;
            }

            foreach ($matches[1] as $column) {
                // Name-level guard (not type): every column a migration adds —
                // whether creating a table or altering one — must appear in the
                // installer DDL, or a fresh install would be missing it.
                if (preg_match('/`' . preg_quote($column, '/') . '`/', $schema) !== 1) {
                    $missing[$column] = basename($file);
                }
            }
        }

        self::assertSame(
            [],
            array_keys($missing),
            'Installer schema.sql is missing columns added by migrations: '
            . implode(', ', array_map(static fn (string $c, string $f): string => "$c ($f)", array_keys($missing), array_values($missing))),
        );
    }
}
