<?php

declare(strict_types=1);

namespace NeneInvoice\DocumentSequence;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Wires document numbering: the sequence repository and number generator.
 */
final readonly class DocumentSequenceServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                DocumentSequenceRepositoryInterface::class,
                static function (ContainerInterface $c): DocumentSequenceRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoDocumentSequenceRepository($query);
                },
            )
            ->set(
                DocumentNumberGenerator::class,
                static function (ContainerInterface $c): DocumentNumberGenerator {
                    $repo = $c->get(DocumentSequenceRepositoryInterface::class);

                    if (!$repo instanceof DocumentSequenceRepositoryInterface) {
                        throw new LogicException('Document sequence repository service is invalid.');
                    }

                    return new DocumentNumberGenerator($repo);
                },
            );
    }
}
