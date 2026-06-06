<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ApplicationServiceProvider;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Audit\AuditServiceProvider;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\LineItem\PdoLineItemRepository;
use Psr\Container\ContainerInterface;

/**
 * Wires the Template (雛形) domain: repository, use cases, handlers, the
 * not-found exception handler, and the route registrar. Line presets reuse the
 * shared line-item repository (parent_type = 'template').
 */
final readonly class TemplateServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                TemplateRepositoryInterface::class,
                static function (ContainerInterface $c): TemplateRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoTemplateRepository($query, self::orgHolder($c));
                },
            )
            ->set(ListTemplatesUseCaseInterface::class, static fn (ContainerInterface $c): ListTemplatesUseCase => new ListTemplatesUseCase(self::repository($c)))
            ->set(GetTemplateByIdUseCaseInterface::class, static fn (ContainerInterface $c): GetTemplateByIdUseCase => new GetTemplateByIdUseCase(self::repository($c), self::lineItems($c)))
            ->set(CreateTemplateUseCaseInterface::class, static function (ContainerInterface $c): CreateTemplateUseCase {
                $orgHolder = self::orgHolder($c);

                return new CreateTemplateUseCase(
                    self::resolve($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $exec): TemplateRepositoryInterface => new PdoTemplateRepository($exec, $orgHolder),
                    static fn (DatabaseQueryExecutorInterface $exec): LineItemRepositoryInterface => new PdoLineItemRepository($exec, $orgHolder),
                    AuditServiceProvider::recorderFactory($c),
                    $orgHolder,
                );
            })
            ->set(UpdateTemplateUseCaseInterface::class, static function (ContainerInterface $c): UpdateTemplateUseCase {
                $orgHolder = self::orgHolder($c);

                return new UpdateTemplateUseCase(
                    self::resolve($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $exec): TemplateRepositoryInterface => new PdoTemplateRepository($exec, $orgHolder),
                    static fn (DatabaseQueryExecutorInterface $exec): LineItemRepositoryInterface => new PdoLineItemRepository($exec, $orgHolder),
                    AuditServiceProvider::recorderFactory($c),
                    $orgHolder,
                );
            })
            ->set(DeleteTemplateUseCaseInterface::class, static fn (ContainerInterface $c): DeleteTemplateUseCase => new DeleteTemplateUseCase(self::repository($c), self::lineItems($c), self::audit($c), self::orgHolder($c)))
            ->set(
                ListTemplatesHandler::class,
                static fn (ContainerInterface $c): ListTemplatesHandler => new ListTemplatesHandler(self::resolve($c, ListTemplatesUseCaseInterface::class), self::json($c)),
            )
            ->set(
                GetTemplateByIdHandler::class,
                static fn (ContainerInterface $c): GetTemplateByIdHandler => new GetTemplateByIdHandler(self::resolve($c, GetTemplateByIdUseCaseInterface::class), self::json($c)),
            )
            ->set(
                CreateTemplateHandler::class,
                static fn (ContainerInterface $c): CreateTemplateHandler => new CreateTemplateHandler(self::resolve($c, CreateTemplateUseCaseInterface::class), self::json($c)),
            )
            ->set(
                UpdateTemplateHandler::class,
                static fn (ContainerInterface $c): UpdateTemplateHandler => new UpdateTemplateHandler(self::resolve($c, UpdateTemplateUseCaseInterface::class), self::json($c)),
            )
            ->set(
                DeleteTemplateHandler::class,
                static fn (ContainerInterface $c): DeleteTemplateHandler => new DeleteTemplateHandler(self::resolve($c, DeleteTemplateUseCaseInterface::class), self::json($c)),
            )
            ->set(
                TemplateNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): TemplateNotFoundExceptionHandler => new TemplateNotFoundExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                TemplateRouteRegistrar::class,
                static function (ContainerInterface $c): TemplateRouteRegistrar {
                    $list = $c->get(ListTemplatesHandler::class);
                    $get = $c->get(GetTemplateByIdHandler::class);
                    $create = $c->get(CreateTemplateHandler::class);
                    $update = $c->get(UpdateTemplateHandler::class);
                    $delete = $c->get(DeleteTemplateHandler::class);

                    if (!$list instanceof ListTemplatesHandler
                        || !$get instanceof GetTemplateByIdHandler
                        || !$create instanceof CreateTemplateHandler
                        || !$update instanceof UpdateTemplateHandler
                        || !$delete instanceof DeleteTemplateHandler
                    ) {
                        throw new LogicException('Template handler services are invalid.');
                    }

                    return new TemplateRouteRegistrar($list, $get, $create, $update, $delete);
                },
            );
    }

    private static function repository(ContainerInterface $c): TemplateRepositoryInterface
    {
        $repo = $c->get(TemplateRepositoryInterface::class);
        if (!$repo instanceof TemplateRepositoryInterface) {
            throw new LogicException('Template repository service is invalid.');
        }

        return $repo;
    }

    private static function lineItems(ContainerInterface $c): LineItemRepositoryInterface
    {
        $repo = $c->get(LineItemRepositoryInterface::class);
        if (!$repo instanceof LineItemRepositoryInterface) {
            throw new LogicException('Line item repository service is invalid.');
        }

        return $repo;
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

    private static function audit(ContainerInterface $c): AuditRecorderInterface
    {
        $recorder = $c->get(AuditRecorderInterface::class);
        if (!$recorder instanceof AuditRecorderInterface) {
            throw new LogicException('Audit recorder service is invalid.');
        }

        return $recorder;
    }

    private static function json(ContainerInterface $c): JsonResponseFactory
    {
        $j = $c->get(JsonResponseFactory::class);
        if (!$j instanceof JsonResponseFactory) {
            throw new LogicException('JSON response factory service is invalid.');
        }

        return $j;
    }

    private static function problemDetails(ContainerInterface $c): ProblemDetailsResponseFactory
    {
        $p = $c->get(ProblemDetailsResponseFactory::class);
        if (!$p instanceof ProblemDetailsResponseFactory) {
            throw new LogicException('Problem details response factory service is invalid.');
        }

        return $p;
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
}
