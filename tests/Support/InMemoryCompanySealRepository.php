<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Company\Seal\CompanySealRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database). Org-scoped, mirroring
 * {@see \NeneInvoice\Company\Seal\PdoCompanySealRepository}. Defaults to org 1.
 */
final class InMemoryCompanySealRepository implements CompanySealRepositoryInterface
{
    /** @var array<int, string> */
    private array $byOrganization = [];

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

    public function find(): ?string
    {
        return $this->byOrganization[$this->orgId->get()] ?? null;
    }

    public function exists(): bool
    {
        return $this->find() !== null;
    }

    public function save(string $imageBase64): void
    {
        $this->byOrganization[$this->orgId->get()] = $imageBase64;
    }

    public function delete(): void
    {
        unset($this->byOrganization[$this->orgId->get()]);
    }
}
