<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Installer;

use PHPUnit\Framework\TestCase;

/**
 * Guards the Tier A web installer against schema drift (issue #482).
 *
 * The CLI-less installer (`public_html/install.php`) provisions the database
 * from a hand-maintained DDL file rather than Phinx, so the two can diverge — as
 * happened when `refresh_tokens` (ADR 0014) was added by a migration but not to
 * the installer schema, leaving fresh installs unable to silent-refresh. This
 * test fails if any table created by a migration is absent from the installer
 * schema, so a real install would have provisioned it.
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
