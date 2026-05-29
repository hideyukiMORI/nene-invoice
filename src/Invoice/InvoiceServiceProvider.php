<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Wires the Invoice domain. Use cases, handlers, and the route registrar are
 * added with the invoice conversion/issue/CRUD PR.
 */
final readonly class InvoiceServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->set(
            InvoiceRepositoryInterface::class,
            static function (ContainerInterface $c): InvoiceRepositoryInterface {
                $query = $c->get(DatabaseQueryExecutorInterface::class);

                if (!$query instanceof DatabaseQueryExecutorInterface) {
                    throw new LogicException('Database query executor service is invalid.');
                }

                return new PdoInvoiceRepository($query);
            },
        );
    }
}
