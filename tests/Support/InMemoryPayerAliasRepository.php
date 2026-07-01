<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\BankTransaction\PayerAlias;
use NeneInvoice\BankTransaction\PayerAliasNotFoundException;
use NeneInvoice\BankTransaction\PayerAliasRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database). `upsert` forces the org from
 * the request-scoped holder and keys on (org, normalized_name), mirroring
 * {@see \NeneInvoice\BankTransaction\PdoPayerAliasRepository}.
 */
final class InMemoryPayerAliasRepository implements PayerAliasRepositoryInterface
{
    /** @var array<int, PayerAlias> */
    private array $byId = [];
    private int $nextId = 1;

    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;

    /** @param RequestScopedHolder<int>|null $orgId */
    public function __construct(?RequestScopedHolder $orgId = null)
    {
        if ($orgId === null) {
            $orgId = new RequestScopedHolder();
            $orgId->set(1);
        }

        $this->orgId = $orgId;
    }

    public function upsert(PayerAlias $alias): int
    {
        $existing = $this->findByNormalizedName($alias->normalizedName);

        if ($existing !== null && $existing->id !== null) {
            $this->byId[$existing->id] = $this->copy($alias, $existing->id, $this->orgId->get());

            return $existing->id;
        }

        $id              = $this->nextId++;
        $this->byId[$id] = $this->copy($alias, $id, $this->orgId->get());

        return $id;
    }

    public function findById(int $id): ?PayerAlias
    {
        $alias = $this->byId[$id] ?? null;

        return $alias !== null && $alias->organizationId === $this->orgId->get() ? $alias : null;
    }

    public function findByNormalizedName(string $normalizedName): ?PayerAlias
    {
        foreach ($this->byId as $alias) {
            if ($alias->organizationId === $this->orgId->get() && $alias->normalizedName === $normalizedName) {
                return $alias;
            }
        }

        return null;
    }

    /** @return list<PayerAlias> */
    public function findByOrganization(int $limit, int $offset): array
    {
        $mine = array_values(array_filter(
            $this->byId,
            fn (PayerAlias $a): bool => $a->organizationId === $this->orgId->get(),
        ));

        usort($mine, static fn (PayerAlias $a, PayerAlias $b): int => strcmp($a->normalizedName, $b->normalizedName));

        return array_slice($mine, $offset, $limit);
    }

    public function countByOrganization(): int
    {
        return count(array_filter(
            $this->byId,
            fn (PayerAlias $a): bool => $a->organizationId === $this->orgId->get(),
        ));
    }

    public function delete(int $id): void
    {
        if ($this->findById($id) === null) {
            throw new PayerAliasNotFoundException($id);
        }

        unset($this->byId[$id]);
    }

    private function copy(PayerAlias $alias, int $id, int $organizationId): PayerAlias
    {
        return new PayerAlias(
            organizationId: $organizationId,
            normalizedName: $alias->normalizedName,
            clientId: $alias->clientId,
            id: $id,
            createdAt: $alias->createdAt ?? '2026-06-06 00:00:00',
            updatedAt: '2026-06-06 00:00:00',
        );
    }
}
