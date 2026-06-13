<?php

declare(strict_types=1);

namespace NeneInvoice\Company\Seal;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class PdoCompanySealRepository implements CompanySealRepositoryInterface
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function find(): ?string
    {
        $row = $this->query->fetchOne(
            'SELECT image_base64 FROM company_seal_images WHERE organization_id = ?',
            [$this->orgId->get()],
        );

        $value = $row['image_base64'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function exists(): bool
    {
        return $this->find() !== null;
    }

    public function save(string $imageBase64): void
    {
        $now            = date('Y-m-d H:i:s');
        $organizationId = $this->orgId->get();

        if ($this->exists()) {
            $this->query->execute(
                'UPDATE company_seal_images SET image_base64 = ?, updated_at = ? WHERE organization_id = ?',
                [$imageBase64, $now, $organizationId],
            );

            return;
        }

        $this->query->execute(
            'INSERT INTO company_seal_images (organization_id, image_base64, created_at, updated_at) VALUES (?, ?, ?, ?)',
            [$organizationId, $imageBase64, $now, $now],
        );
    }

    public function delete(): void
    {
        $this->query->execute(
            'DELETE FROM company_seal_images WHERE organization_id = ?',
            [$this->orgId->get()],
        );
    }
}
