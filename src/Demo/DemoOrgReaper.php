<?php

declare(strict_types=1);

namespace NeneInvoice\Demo;

use Nene2\Database\DatabaseConnectionException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Demo\DisposableOrgReaperInterface;
use NeneInvoice\Organization\DeleteOrganizationUseCaseInterface;
use NeneInvoice\Organization\OrganizationNotFoundException;

/**
 * Destroys one disposable demo organization and everything it owns
 * (Nene2\Demo consumer, #610 — the teardown half of `tools/sweep-demo.php`).
 *
 * The org row itself goes through the audited {@see DeleteOrganizationUseCaseInterface},
 * which does NOT cascade: child rows are disposable demo data, so they are
 * bulk-DELETEd best-effort here first (`"消えていい" のが強み＝掃除は雑でよい`),
 * then the residue outside the database (the org's `var/recurring-runs` stamp).
 *
 * Idempotent by contract: an org already swept by a concurrent run is success
 * ({@see \Nene2\Demo\DisposableDemoSweeper} does not catch our exceptions).
 * Real organizations are protected upstream — the sweeper only ever sees orgs
 * the caller selected by the demo slug prefix.
 */
final readonly class DemoOrgReaper implements DisposableOrgReaperInterface
{
    /**
     * Child tables carrying `organization_id` directly (`line_items` has no
     * `organization_id` and is deleted through its parents below).
     */
    private const array CHILD_TABLES = [
        'clients', 'items', 'quotes', 'invoices', 'payments', 'bank_transactions',
        'payer_aliases', 'recurring_invoices', 'company_settings', 'document_sequences',
        'users', 'refresh_tokens', 'login_attempts', 'service_tokens',
        'payment_links', 'invoice_download_tokens', 'templates', 'company_seal_images',
    ];

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private DeleteOrganizationUseCaseInterface $deleteOrganization,
        private string $projectRoot,
    ) {
    }

    public function reap(int $orgId): void
    {
        foreach (['invoice', 'quote', 'recurring_invoice'] as $parentType) {
            $this->query->execute(
                "DELETE FROM line_items WHERE parent_type = ? AND parent_id IN
                    (SELECT id FROM {$parentType}s WHERE organization_id = ?)",
                [$parentType, $orgId],
            );
        }

        foreach (self::CHILD_TABLES as $table) {
            try {
                $this->query->execute("DELETE FROM {$table} WHERE organization_id = ?", [$orgId]);
            } catch (DatabaseConnectionException) {
                // テーブルが無い環境（軽量構成）でも掃除は続行する。
            }
        }

        try {
            $this->deleteOrganization->execute(null, $orgId);
        } catch (OrganizationNotFoundException) {
            // すでに消えている（並行実行など）— 冪等成功。
        }

        // RECURRING_INLINE の実行スタンプはファイルなので DB 掃除では消えない。
        @unlink($this->projectRoot . '/var/recurring-runs/org-' . $orgId . '.txt');
    }
}
