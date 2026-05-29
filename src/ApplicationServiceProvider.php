<?php

declare(strict_types=1);

namespace NeneInvoice;

use LogicException;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use NeneInvoice\Auth\AuthRouteRegistrar;
use Psr\Container\ContainerInterface;

/**
 * Aggregates the application's route registrars and domain exception handlers.
 *
 * `GET /health` is provided by the framework. Per-domain providers
 * (Organization, Auth, User, Client, …) register their route registrars in the
 * container; this provider collects them into the list the runtime consumes.
 */
final readonly class ApplicationServiceProvider implements ServiceProviderInterface
{
    public const ROUTE_REGISTRARS = 'nene-invoice.route_registrars';
    public const EXCEPTION_HANDLERS = 'nene-invoice.exception_handlers';

    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                self::ROUTE_REGISTRARS,
                static function (ContainerInterface $container): array {
                    $authRoutes = $container->get(AuthRouteRegistrar::class);

                    if (!$authRoutes instanceof AuthRouteRegistrar) {
                        throw new LogicException('Auth route registrar service is invalid.');
                    }

                    return [$authRoutes];
                },
            )
            ->set(self::EXCEPTION_HANDLERS, static fn (ContainerInterface $container): array => []);
    }
}
