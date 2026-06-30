<?php

declare(strict_types=1);

/**
 * Tier B (Docker / VPS) cron entry point for recurring billing (#526).
 *
 * Generates due recurring **drafts** across every organization. Issuing and
 * numbering remain a separate, human, tax-reviewed step
 * (docs/explanation/accounting-compliance.md §5) — this script never issues.
 *
 * Usage (cron, e.g. daily 02:00 JST):
 *   php tools/run-recurring.php
 *   # docker: docker compose exec -T app php tools/run-recurring.php
 *
 * Idempotent: the GenerateDue use case advances next_run_on and stamps
 * last_run_on, so re-running on the same day generates nothing new. Safe to run
 * more often than daily.
 *
 * Tier A (shared hosting, no cron) does not need this — the inline
 * RecurringDueCheckMiddleware runs the same generation on /admin/ requests.
 */

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ApplicationServiceProvider;
use NeneInvoice\Http\RuntimeContainerFactory;
use NeneInvoice\Organization\OrganizationRepositoryInterface;
use NeneInvoice\RecurringInvoice\GenerateDueRecurringInvoicesUseCase;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = (new RuntimeContainerFactory(dirname(__DIR__)))->create();

$orgHolder = $container->get(ApplicationServiceProvider::ORG_ID_HOLDER);
if (!$orgHolder instanceof RequestScopedHolder) {
    fwrite(STDERR, "Error: org holder service is unavailable.\n");
    exit(1);
}

$organizations = $container->get(OrganizationRepositoryInterface::class);
if (!$organizations instanceof OrganizationRepositoryInterface) {
    fwrite(STDERR, "Error: organization repository is unavailable.\n");
    exit(1);
}

$useCase = $container->get(GenerateDueRecurringInvoicesUseCase::class);
if (!$useCase instanceof GenerateDueRecurringInvoicesUseCase) {
    fwrite(STDERR, "Error: recurring generator is unavailable.\n");
    exit(1);
}

$batchSize      = 100;
$offset         = 0;
$totalOrgs      = 0;
$totalGenerated = 0;

while (true) {
    $orgs = $organizations->findAll($batchSize, $offset);
    if ($orgs === []) {
        break;
    }

    foreach ($orgs as $org) {
        if ($org->id === null) {
            continue;
        }

        // Scope every query to this org (ADR 0006): the holder is shared with the
        // generator's repositories, which read it at query time.
        $orgHolder->set($org->id);
        $result = $useCase->execute(null);
        ++$totalOrgs;

        foreach ($result->generated as $row) {
            ++$totalGenerated;
            printf("org %d: recurring %d → draft invoice %d\n", $org->id, $row['recurring_invoice_id'], $row['invoice_id']);
        }
    }

    if (count($orgs) < $batchSize) {
        break;
    }

    $offset += $batchSize;
}

printf("Done. %d draft(s) generated across %d organization(s).\n", $totalGenerated, $totalOrgs);
