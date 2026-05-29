<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Wires the shared line-item repository.
 */
final readonly class LineItemServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->set(
            LineItemRepositoryInterface::class,
            static function (ContainerInterface $c): LineItemRepositoryInterface {
                $query = $c->get(DatabaseQueryExecutorInterface::class);

                if (!$query instanceof DatabaseQueryExecutorInterface) {
                    throw new LogicException('Database query executor service is invalid.');
                }

                return new PdoLineItemRepository($query);
            },
        );
    }
}
