<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceApi;

use LogicException;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Invoice\GetInvoiceByIdUseCase;
use NeneInvoice\Invoice\ListInvoicesUseCase;
use NeneInvoice\Payment\ListPaymentsUseCase;
use Psr\Container\ContainerInterface;

/**
 * Wires the NeNe Clear service surface (`/api/*`): scope middleware, read
 * handlers (reusing operator use cases), and the route registrar (ADR 0009).
 */
final readonly class ServiceApiServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                ServiceScopeMiddleware::class,
                static fn (ContainerInterface $c): ServiceScopeMiddleware => new ServiceScopeMiddleware(self::problemDetails($c)),
            )
            ->set(
                ListServiceInvoicesHandler::class,
                static fn (ContainerInterface $c): ListServiceInvoicesHandler => new ListServiceInvoicesHandler(
                    self::resolve($c, ListInvoicesUseCase::class),
                    self::json($c),
                    self::problemDetails($c),
                ),
            )
            ->set(
                GetServiceInvoiceHandler::class,
                static fn (ContainerInterface $c): GetServiceInvoiceHandler => new GetServiceInvoiceHandler(
                    self::resolve($c, GetInvoiceByIdUseCase::class),
                    self::resolve($c, ListPaymentsUseCase::class),
                    self::json($c),
                    self::problemDetails($c),
                ),
            )
            ->set(
                ServiceApiRouteRegistrar::class,
                static function (ContainerInterface $c): ServiceApiRouteRegistrar {
                    $list = $c->get(ListServiceInvoicesHandler::class);
                    $get = $c->get(GetServiceInvoiceHandler::class);

                    if (!$list instanceof ListServiceInvoicesHandler || !$get instanceof GetServiceInvoiceHandler) {
                        throw new LogicException('Service API handler services are invalid.');
                    }

                    return new ServiceApiRouteRegistrar($list, $get);
                },
            );
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    private static function resolve(ContainerInterface $c, string $id): object
    {
        $service = $c->get($id);

        if (!$service instanceof $id) {
            throw new LogicException(sprintf('Service %s is invalid.', $id));
        }

        return $service;
    }

    private static function json(ContainerInterface $c): JsonResponseFactory
    {
        return self::resolve($c, JsonResponseFactory::class);
    }

    private static function problemDetails(ContainerInterface $c): ProblemDetailsResponseFactory
    {
        return self::resolve($c, ProblemDetailsResponseFactory::class);
    }
}
