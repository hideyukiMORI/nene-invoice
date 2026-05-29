<?php

declare(strict_types=1);

namespace NeneInvoice;

use LogicException;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use NeneInvoice\Auth\AuthRouteRegistrar;
use NeneInvoice\Organization\OrganizationNotFoundExceptionHandler;
use NeneInvoice\Organization\OrganizationRouteRegistrar;
use NeneInvoice\Organization\OrganizationSlugConflictExceptionHandler;
use NeneInvoice\User\UserNotFoundExceptionHandler;
use NeneInvoice\User\UserRouteRegistrar;
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
                    $organizationRoutes = $container->get(OrganizationRouteRegistrar::class);
                    $userRoutes = $container->get(UserRouteRegistrar::class);

                    if (!$authRoutes instanceof AuthRouteRegistrar) {
                        throw new LogicException('Auth route registrar service is invalid.');
                    }

                    if (!$organizationRoutes instanceof OrganizationRouteRegistrar) {
                        throw new LogicException('Organization route registrar service is invalid.');
                    }

                    if (!$userRoutes instanceof UserRouteRegistrar) {
                        throw new LogicException('User route registrar service is invalid.');
                    }

                    return [$authRoutes, $organizationRoutes, $userRoutes];
                },
            )
            ->set(
                self::EXCEPTION_HANDLERS,
                static function (ContainerInterface $container): array {
                    $orgNotFound = $container->get(OrganizationNotFoundExceptionHandler::class);
                    $orgSlugConflict = $container->get(OrganizationSlugConflictExceptionHandler::class);
                    $userNotFound = $container->get(UserNotFoundExceptionHandler::class);

                    if (!$orgNotFound instanceof OrganizationNotFoundExceptionHandler) {
                        throw new LogicException('Organization not-found exception handler service is invalid.');
                    }

                    if (!$orgSlugConflict instanceof OrganizationSlugConflictExceptionHandler) {
                        throw new LogicException('Organization slug-conflict exception handler service is invalid.');
                    }

                    if (!$userNotFound instanceof UserNotFoundExceptionHandler) {
                        throw new LogicException('User not-found exception handler service is invalid.');
                    }

                    return [$orgNotFound, $orgSlugConflict, $userNotFound];
                },
            );
    }
}
