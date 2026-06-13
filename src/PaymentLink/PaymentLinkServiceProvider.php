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
use NeneInvoice\Payment\Gateway\PayjpGateway;
use NeneInvoice\Payment\Gateway\PayjpWebhookHandler;
use NeneInvoice\Payment\Gateway\PaymentGatewayInterface;
use NeneInvoice\Payment\PaymentRepositoryInterface;
use NeneInvoice\Payment\RecordPaymentUseCaseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
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
                PaymentGatewayInterface::class,
                static fn (ContainerInterface $c): PaymentGatewayInterface => new PayjpGateway(
                    (string) (getenv('PAYJP_SECRET_KEY') ?: ''),
                ),
            )
            ->set(
                ChargePaymentLinkUseCaseInterface::class,
                static fn (ContainerInterface $c): ChargePaymentLinkUseCase => new ChargePaymentLinkUseCase(
                    self::resolve($c, PaymentLinkRepositoryInterface::class),
                    self::resolve($c, InvoiceRepositoryInterface::class),
                    self::resolve($c, PaymentRepositoryInterface::class),
                    self::resolve($c, PaymentGatewayInterface::class),
                    self::resolve($c, RecordPaymentUseCaseInterface::class),
                    self::resolve($c, ClockInterface::class),
                    self::orgHolder($c),
                ),
            )
            ->set(
                PayPageHandler::class,
                static fn (ContainerInterface $c): PayPageHandler => new PayPageHandler(
                    self::resolve($c, PaymentLinkRepositoryInterface::class),
                    self::resolve($c, InvoiceRepositoryInterface::class),
                    self::resolve($c, PaymentRepositoryInterface::class),
                    self::resolve($c, ClockInterface::class),
                    self::resolve($c, Psr17Factory::class),
                    self::orgHolder($c),
                    (string) (getenv('PAYJP_PUBLIC_KEY') ?: ''),
                ),
            )
            ->set(
                ChargePaymentLinkHandler::class,
                static fn (ContainerInterface $c): ChargePaymentLinkHandler => new ChargePaymentLinkHandler(
                    self::resolve($c, ChargePaymentLinkUseCaseInterface::class),
                    self::resolve($c, Psr17Factory::class),
                ),
            )
            ->set(
                RecordSettlementUseCaseInterface::class,
                static fn (ContainerInterface $c): RecordSettlementUseCase => new RecordSettlementUseCase(
                    self::resolve($c, PaymentLinkRepositoryInterface::class),
                    self::resolve($c, RecordPaymentUseCaseInterface::class),
                    self::resolve($c, ClockInterface::class),
                    self::orgHolder($c),
                ),
            )
            ->set(
                PayjpWebhookHandler::class,
                static fn (ContainerInterface $c): PayjpWebhookHandler => new PayjpWebhookHandler(
                    self::resolve($c, RecordSettlementUseCaseInterface::class),
                    self::resolve($c, JsonResponseFactory::class),
                    self::resolve($c, ProblemDetailsResponseFactory::class),
                    (string) (getenv('PAYJP_WEBHOOK_TOKEN') ?: ''),
                ),
            )
            ->set(
                PaymentLinkRouteRegistrar::class,
                static fn (ContainerInterface $c): PaymentLinkRouteRegistrar => new PaymentLinkRouteRegistrar(
                    self::resolve($c, GeneratePaymentLinkHandler::class),
                    self::resolve($c, RevokePaymentLinkHandler::class),
                    self::resolve($c, PayPageHandler::class),
                    self::resolve($c, ChargePaymentLinkHandler::class),
                    self::resolve($c, PayjpWebhookHandler::class),
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
