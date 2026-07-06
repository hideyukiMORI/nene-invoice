<?php

declare(strict_types=1);

namespace NeneInvoice\InvoiceDownloadToken;

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
use NeneInvoice\Invoice\GenerateInvoicePdfUseCaseInterface;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;
use NeneInvoice\Invoice\Pdf\InvoicePdfGenerator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;

final readonly class InvoiceDownloadTokenServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                InvoiceDownloadTokenRepositoryInterface::class,
                static function (ContainerInterface $c): InvoiceDownloadTokenRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoInvoiceDownloadTokenRepository($query);
                },
            )
            ->set(
                GenerateDownloadTokenUseCaseInterface::class,
                static fn (ContainerInterface $c): GenerateDownloadTokenUseCase => new GenerateDownloadTokenUseCase(
                    self::resolve($c, InvoiceRepositoryInterface::class),
                    self::resolve($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $exec): InvoiceDownloadTokenRepositoryInterface => new PdoInvoiceDownloadTokenRepository($exec),
                    AuditServiceProvider::recorderFactory($c),
                    self::resolve($c, ClockInterface::class),
                    self::orgHolder($c),
                ),
            )
            ->set(
                GenerateDownloadTokenHandler::class,
                static fn (ContainerInterface $c): GenerateDownloadTokenHandler => new GenerateDownloadTokenHandler(
                    self::resolve($c, GenerateDownloadTokenUseCaseInterface::class),
                    self::resolve($c, JsonResponseFactory::class),
                ),
            )
            ->set(
                DownloadInvoicePdfHandler::class,
                static fn (ContainerInterface $c): DownloadInvoicePdfHandler => new DownloadInvoicePdfHandler(
                    self::resolve($c, InvoiceDownloadTokenRepositoryInterface::class),
                    self::resolve($c, GenerateInvoicePdfUseCaseInterface::class),
                    self::resolve($c, InvoicePdfGenerator::class),
                    self::resolve($c, Psr17Factory::class),
                    self::resolve($c, ProblemDetailsResponseFactory::class),
                    self::orgHolder($c),
                    self::resolve($c, ClockInterface::class),
                ),
            )
            ->set(
                InvoiceDownloadTokenRouteRegistrar::class,
                static fn (ContainerInterface $c): InvoiceDownloadTokenRouteRegistrar => new InvoiceDownloadTokenRouteRegistrar(
                    self::resolve($c, GenerateDownloadTokenHandler::class),
                    self::resolve($c, DownloadInvoicePdfHandler::class),
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
