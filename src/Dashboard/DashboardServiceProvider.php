<?php

declare(strict_types=1);

namespace NeneInvoice\Dashboard;

use LogicException;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ApplicationServiceProvider;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;
use NeneInvoice\Payment\PaymentRepositoryInterface;
use Psr\Container\ContainerInterface;

final readonly class DashboardServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                GetDashboardSummaryUseCase::class,
                static fn (ContainerInterface $c): GetDashboardSummaryUseCase => new GetDashboardSummaryUseCase(
                    self::resolve($c, InvoiceRepositoryInterface::class),
                    self::resolve($c, PaymentRepositoryInterface::class),
                    self::orgHolder($c),
                ),
            )
            ->set(
                GetDashboardHandler::class,
                static fn (ContainerInterface $c): GetDashboardHandler => new GetDashboardHandler(
                    self::resolve($c, GetDashboardSummaryUseCase::class),
                    self::resolve($c, PaymentRepositoryInterface::class),
                    self::resolve($c, JsonResponseFactory::class),
                ),
            )
            ->set(
                DashboardRouteRegistrar::class,
                static fn (ContainerInterface $c): DashboardRouteRegistrar => new DashboardRouteRegistrar(
                    self::resolve($c, GetDashboardHandler::class),
                ),
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

    /** @return RequestScopedHolder<int> */
    private static function orgHolder(ContainerInterface $c): RequestScopedHolder
    {
        $holder = $c->get(ApplicationServiceProvider::ORG_ID_HOLDER);

        if (!$holder instanceof RequestScopedHolder) {
            throw new LogicException('Org id holder service is invalid.');
        }

        return $holder;
    }
}
