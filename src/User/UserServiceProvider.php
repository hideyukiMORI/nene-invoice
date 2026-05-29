<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use LogicException;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Psr\Container\ContainerInterface;

/**
 * Wires the User domain read side: use cases, handlers, the not-found exception
 * handler, and the route registrar. `UserRepositoryInterface` is provided by
 * {@see \NeneInvoice\Auth\AuthServiceProvider}.
 */
final readonly class UserServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(ListUsersUseCase::class, static fn (ContainerInterface $c): ListUsersUseCase => new ListUsersUseCase(self::repository($c)))
            ->set(GetUserByIdUseCase::class, static fn (ContainerInterface $c): GetUserByIdUseCase => new GetUserByIdUseCase(self::repository($c)))
            ->set(
                ListUsersHandler::class,
                static fn (ContainerInterface $c): ListUsersHandler => new ListUsersHandler(
                    self::listUseCase($c),
                    self::json($c),
                    self::problemDetails($c),
                ),
            )
            ->set(
                GetUserByIdHandler::class,
                static fn (ContainerInterface $c): GetUserByIdHandler => new GetUserByIdHandler(
                    self::getUseCase($c),
                    self::json($c),
                    self::problemDetails($c),
                ),
            )
            ->set(
                UserNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): UserNotFoundExceptionHandler => new UserNotFoundExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                UserRouteRegistrar::class,
                static function (ContainerInterface $c): UserRouteRegistrar {
                    $list = $c->get(ListUsersHandler::class);
                    $get = $c->get(GetUserByIdHandler::class);

                    if (!$list instanceof ListUsersHandler || !$get instanceof GetUserByIdHandler) {
                        throw new LogicException('User handler services are invalid.');
                    }

                    return new UserRouteRegistrar($list, $get);
                },
            );
    }

    private static function repository(ContainerInterface $c): UserRepositoryInterface
    {
        $repo = $c->get(UserRepositoryInterface::class);

        if (!$repo instanceof UserRepositoryInterface) {
            throw new LogicException('User repository service is invalid.');
        }

        return $repo;
    }

    private static function listUseCase(ContainerInterface $c): ListUsersUseCase
    {
        $u = $c->get(ListUsersUseCase::class);

        if (!$u instanceof ListUsersUseCase) {
            throw new LogicException('List users use case service is invalid.');
        }

        return $u;
    }

    private static function getUseCase(ContainerInterface $c): GetUserByIdUseCase
    {
        $u = $c->get(GetUserByIdUseCase::class);

        if (!$u instanceof GetUserByIdUseCase) {
            throw new LogicException('Get user use case service is invalid.');
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
