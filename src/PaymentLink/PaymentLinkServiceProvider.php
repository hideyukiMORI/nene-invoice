<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\ClockInterface;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ApplicationServiceProvider;
use NeneInvoice\Audit\AuditServiceProvider;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;
use Psr\Container\ContainerInterface;

final readonly class PaymentLinkServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                PaymentLinkRepositoryInterface::class,
                static function (ContainerInterface $c): PaymentLinkRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoPaymentLinkRepository($query, self::orgHolder($c));
                },
            )
            ->set(
                GeneratePaymentLinkUseCaseInterface::class,
                static fn (ContainerInterface $c): GeneratePaymentLinkUseCase => new GeneratePaymentLinkUseCase(
                    self::resolve($c, InvoiceRepositoryInterface::class),
                    self::resolve($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $exec): PaymentLinkRepositoryInterface => new PdoPaymentLinkRepository($exec, self::orgHolder($c)),
                    AuditServiceProvider::recorderFactory($c),
                    self::resolve($c, ClockInterface::class),
                    self::orgHolder($c),
                ),
            )
            ->set(
                RevokePaymentLinkUseCaseInterface::class,
                static fn (ContainerInterface $c): RevokePaymentLinkUseCase => new RevokePaymentLinkUseCase(
                    self::resolve($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $exec): PaymentLinkRepositoryInterface => new PdoPaymentLinkRepository($exec, self::orgHolder($c)),
                    AuditServiceProvider::recorderFactory($c),
                    self::resolve($c, ClockInterface::class),
                    self::orgHolder($c),
                ),
            )
            ->set(
                GeneratePaymentLinkHandler::class,
                static fn (ContainerInterface $c): GeneratePaymentLinkHandler => new GeneratePaymentLinkHandler(
                    self::resolve($c, GeneratePaymentLinkUseCaseInterface::class),
                    self::resolve($c, JsonResponseFactory::class),
                ),
            )
            ->set(
                RevokePaymentLinkHandler::class,
                static fn (ContainerInterface $c): RevokePaymentLinkHandler => new RevokePaymentLinkHandler(
                    self::resolve($c, RevokePaymentLinkUseCaseInterface::class),
                    self::resolve($c, JsonResponseFactory::class),
                    self::resolve($c, ProblemDetailsResponseFactory::class),
                ),
            )
            ->set(
                PaymentLinkRouteRegistrar::class,
                static fn (ContainerInterface $c): PaymentLinkRouteRegistrar => new PaymentLinkRouteRegistrar(
                    self::resolve($c, GeneratePaymentLinkHandler::class),
                    self::resolve($c, RevokePaymentLinkHandler::class),
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
