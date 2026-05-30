<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ApplicationServiceProvider;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Wires the Payment domain: repository, use cases (record / list), handlers, the
 * validation exception handler, and the route registrar.
 */
final readonly class PaymentServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                PaymentRepositoryInterface::class,
                static function (ContainerInterface $c): PaymentRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoPaymentRepository($query, self::orgHolder($c));
                },
            )
            ->set(
                RecordPaymentUseCase::class,
                static fn (ContainerInterface $c): RecordPaymentUseCase => new RecordPaymentUseCase(
                    self::resolve($c, PaymentRepositoryInterface::class),
                    self::resolve($c, InvoiceRepositoryInterface::class),
                    self::resolve($c, AuditRecorderInterface::class),
                    self::orgHolder($c),
                ),
            )
            ->set(
                ListPaymentsUseCase::class,
                static fn (ContainerInterface $c): ListPaymentsUseCase => new ListPaymentsUseCase(
                    self::resolve($c, PaymentRepositoryInterface::class),
                    self::resolve($c, InvoiceRepositoryInterface::class),
                ),
            )
            ->set(
                VoidPaymentUseCase::class,
                static fn (ContainerInterface $c): VoidPaymentUseCase => new VoidPaymentUseCase(
                    self::resolve($c, PaymentRepositoryInterface::class),
                    self::resolve($c, InvoiceRepositoryInterface::class),
                    self::resolve($c, AuditRecorderInterface::class),
                    self::orgHolder($c),
                ),
            )
            ->set(
                RecordPaymentHandler::class,
                static fn (ContainerInterface $c): RecordPaymentHandler => new RecordPaymentHandler(
                    self::resolve($c, RecordPaymentUseCase::class),
                    self::json($c),
                ),
            )
            ->set(
                ListPaymentsHandler::class,
                static fn (ContainerInterface $c): ListPaymentsHandler => new ListPaymentsHandler(
                    self::resolve($c, ListPaymentsUseCase::class),
                    self::json($c),
                ),
            )
            ->set(
                PaymentValidationExceptionHandler::class,
                static fn (ContainerInterface $c): PaymentValidationExceptionHandler => new PaymentValidationExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                PaymentExceedsOutstandingExceptionHandler::class,
                static fn (ContainerInterface $c): PaymentExceedsOutstandingExceptionHandler => new PaymentExceedsOutstandingExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                PaymentNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): PaymentNotFoundExceptionHandler => new PaymentNotFoundExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                PaymentRouteRegistrar::class,
                static function (ContainerInterface $c): PaymentRouteRegistrar {
                    $record = $c->get(RecordPaymentHandler::class);
                    $list = $c->get(ListPaymentsHandler::class);

                    if (!$record instanceof RecordPaymentHandler || !$list instanceof ListPaymentsHandler) {
                        throw new LogicException('Payment handler services are invalid.');
                    }

                    return new PaymentRouteRegistrar($record, $list);
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
