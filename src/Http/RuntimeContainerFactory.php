<?php

declare(strict_types=1);

namespace NeneInvoice\Http;

use Nene2\DependencyInjection\ContainerBuilder;
use Psr\Container\ContainerInterface;

/**
 * Builds the runtime DI container for the NeNe Invoice front controller.
 *
 * The project root is passed through so that configuration and database
 * services (added in later PRs) can resolve paths relative to it.
 */
final readonly class RuntimeContainerFactory
{
    public function __construct(
        private ?string $projectRoot = null,
    ) {
    }

    public function create(): ContainerInterface
    {
        $projectRoot = $this->projectRoot ?? dirname(__DIR__, 2);

        return (new ContainerBuilder())
            ->value(RuntimeServiceProvider::PROJECT_ROOT, $projectRoot)
            ->addProvider(new RuntimeServiceProvider())
            ->build();
    }
}
