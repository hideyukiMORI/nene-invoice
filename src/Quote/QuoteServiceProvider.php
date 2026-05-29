<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Wires the Quote domain. Use cases, handlers, and the route registrar are added
 * with the quote CRUD PR.
 */
final readonly class QuoteServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->set(
            QuoteRepositoryInterface::class,
            static function (ContainerInterface $c): QuoteRepositoryInterface {
                $query = $c->get(DatabaseQueryExecutorInterface::class);

                if (!$query instanceof DatabaseQueryExecutorInterface) {
                    throw new LogicException('Database query executor service is invalid.');
                }

                return new PdoQuoteRepository($query);
            },
        );
    }
}
