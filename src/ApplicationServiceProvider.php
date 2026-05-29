<?php

declare(strict_types=1);

namespace NeneInvoice;

use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Aggregates the application's route registrars and domain exception handlers.
 *
 * Both lists are empty in this scaffold — `GET /health` is provided by the
 * framework. Per-domain providers (Organization, Auth, User, Client, …) append
 * their registrars and handlers here as features land.
 */
final readonly class ApplicationServiceProvider implements ServiceProviderInterface
{
    public const ROUTE_REGISTRARS = 'nene-invoice.route_registrars';
    public const EXCEPTION_HANDLERS = 'nene-invoice.exception_handlers';

    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(self::ROUTE_REGISTRARS, static fn (ContainerInterface $container): array => [])
            ->set(self::EXCEPTION_HANDLERS, static fn (ContainerInterface $container): array => []);
    }
}
