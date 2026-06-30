<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;

/**
 * Imports a bank statement CSV into staged {@see BankTransaction} rows (#505).
 *
 * Parses with {@see BankCsvParser}, then persists the lines in one transaction,
 * skipping any whose `bank_reference` was already imported (idempotent
 * re-import) and de-duplicating references within the same file. Staging only —
 * it records **no payment**; matching a line to an invoice and recording a
 * payment are later, compliance-reviewed steps (accounting-compliance.md).
 */
final readonly class ImportBankTransactionsUseCase
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): BankTransactionRepositoryInterface $repositoryFactory
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $tx,
        private Closure $repositoryFactory,
    ) {
    }

    public function execute(string $raw, BankCsvColumnMapping $mapping): ImportBankTransactionsResult
    {
        $parse = BankCsvParser::parse($raw, $mapping);

        if ($parse->formatError !== null) {
            return new ImportBankTransactionsResult(0, 0, [], $parse->formatError);
        }

        /** @var array{imported: int, skipped: int} $counts */
        $counts = $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($parse): array {
            $repository = ($this->repositoryFactory)($exec);
            $imported   = 0;
            $skipped    = 0;
            $seen       = [];

            foreach ($parse->transactions as $transaction) {
                $reference = $transaction->bankReference;

                if ($reference !== null) {
                    if (isset($seen[$reference]) || $repository->findByBankReference($reference) !== null) {
                        ++$skipped;

                        continue;
                    }

                    $seen[$reference] = true;
                }

                $repository->save($transaction);
                ++$imported;
            }

            return ['imported' => $imported, 'skipped' => $skipped];
        });

        return new ImportBankTransactionsResult($counts['imported'], $counts['skipped'], $parse->errors, null);
    }
}
