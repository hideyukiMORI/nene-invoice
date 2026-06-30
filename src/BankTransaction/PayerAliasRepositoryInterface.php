<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

interface PayerAliasRepositoryInterface
{
    /**
     * Insert a new alias, or update the existing alias for this org + normalized
     * name to point at `$alias->clientId` (learning on confirm). Returns the row id.
     */
    public function upsert(PayerAlias $alias): int;

    public function findById(int $id): ?PayerAlias;

    /** The client mapped to this normalized payer name in the caller's org, or null. */
    public function findByNormalizedName(string $normalizedName): ?PayerAlias;

    /** @return list<PayerAlias> */
    public function findByOrganization(int $limit, int $offset): array;

    public function countByOrganization(): int;

    /** @throws PayerAliasNotFoundException */
    public function delete(int $id): void;
}
