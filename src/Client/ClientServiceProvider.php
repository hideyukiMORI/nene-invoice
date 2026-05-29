<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Psr\Container\ContainerInterface;

/**
 * Wires the Client (取引先) domain: repository, read use cases, handlers, the
 * not-found exception handler, and the route registrar.
 */
final readonly class ClientServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                ClientRepositoryInterface::class,
                static function (ContainerInterface $c): ClientRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoClientRepository($query);
                },
            )
            ->set(ListClientsUseCase::class, static fn (ContainerInterface $c): ListClientsUseCase => new ListClientsUseCase(self::repository($c)))
            ->set(GetClientByIdUseCase::class, static fn (ContainerInterface $c): GetClientByIdUseCase => new GetClientByIdUseCase(self::repository($c)))
            ->set(
                ListClientsHandler::class,
                static fn (ContainerInterface $c): ListClientsHandler => new ListClientsHandler(
                    self::listUseCase($c),
                    self::json($c),
                    self::problemDetails($c),
                ),
            )
            ->set(
                GetClientByIdHandler::class,
                static fn (ContainerInterface $c): GetClientByIdHandler => new GetClientByIdHandler(
                    self::getUseCase($c),
                    self::json($c),
                    self::problemDetails($c),
                ),
            )
            ->set(
                ClientNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): ClientNotFoundExceptionHandler => new ClientNotFoundExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                ClientRouteRegistrar::class,
                static function (ContainerInterface $c): ClientRouteRegistrar {
                    $list = $c->get(ListClientsHandler::class);
                    $get = $c->get(GetClientByIdHandler::class);

                    if (!$list instanceof ListClientsHandler || !$get instanceof GetClientByIdHandler) {
                        throw new LogicException('Client handler services are invalid.');
                    }

                    return new ClientRouteRegistrar($list, $get);
                },
            );
    }

    private static function repository(ContainerInterface $c): ClientRepositoryInterface
    {
        $repo = $c->get(ClientRepositoryInterface::class);

        if (!$repo instanceof ClientRepositoryInterface) {
            throw new LogicException('Client repository service is invalid.');
        }

        return $repo;
    }

    private static function listUseCase(ContainerInterface $c): ListClientsUseCase
    {
        $u = $c->get(ListClientsUseCase::class);

        if (!$u instanceof ListClientsUseCase) {
            throw new LogicException('List clients use case service is invalid.');
        }

        return $u;
    }

    private static function getUseCase(ContainerInterface $c): GetClientByIdUseCase
    {
        $u = $c->get(GetClientByIdUseCase::class);

        if (!$u instanceof GetClientByIdUseCase) {
            throw new LogicException('Get client use case service is invalid.');
        }

        return $u;
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
}
