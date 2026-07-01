<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers bank-reconciliation (自動消込) routes (#505). Reads require
 * `view_billing`, mutations require `manage_billing` (CapabilityMiddleware); all
 * scoped to the caller's organization.
 */
final readonly class BankTransactionRouteRegistrar
{
    public function __construct(
        private ImportBankTransactionsHandler $importHandler,
        private ListBankTransactionsHandler $listHandler,
        private BankTransactionSuggestionsHandler $suggestionsHandler,
        private ConfirmBankTransactionMatchHandler $confirmHandler,
        private IgnoreBankTransactionHandler $ignoreHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $import      = $this->importHandler;
        $list        = $this->listHandler;
        $suggestions = $this->suggestionsHandler;
        $confirm     = $this->confirmHandler;
        $ignore      = $this->ignoreHandler;

        $router->post('/admin/bank-transactions/import', static fn (ServerRequestInterface $r) => $import->handle($r));
        $router->get('/admin/bank-transactions', static fn (ServerRequestInterface $r) => $list->handle($r));
        $router->get('/admin/bank-transactions/{id}/suggestions', static fn (ServerRequestInterface $r) => $suggestions->handle($r));
        $router->post('/admin/bank-transactions/{id}/confirm', static fn (ServerRequestInterface $r) => $confirm->handle($r));
        $router->post('/admin/bank-transactions/{id}/ignore', static fn (ServerRequestInterface $r) => $ignore->handle($r));
    }
}
