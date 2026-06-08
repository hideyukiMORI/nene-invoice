<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

final readonly class ListServiceTokensUseCase implements ListServiceTokensUseCaseInterface
{
    public function __construct(
        private ServiceTokenRepositoryInterface $repository,
    ) {
    }

    public function execute(int $limit, int $offset): ListServiceTokensResult
    {
        return new ListServiceTokensResult(
            items: $this->repository->findAll($limit, $offset),
            total: $this->repository->count(),
        );
    }
}
