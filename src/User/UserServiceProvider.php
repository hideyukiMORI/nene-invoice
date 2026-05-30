<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use LogicException;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ApplicationServiceProvider;
use NeneInvoice\Audit\AuditRecorderInterface;
use Psr\Container\ContainerInterface;

/**
 * Wires the User domain: use cases, handlers, domain exception handlers, and the
 * route registrar. `UserRepositoryInterface` is provided by
 * {@see \NeneInvoice\Auth\AuthServiceProvider}.
 */
final readonly class UserServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(ListUsersUseCase::class, static fn (ContainerInterface $c): ListUsersUseCase => new ListUsersUseCase(self::repository($c)))
            ->set(GetUserByIdUseCase::class, static fn (ContainerInterface $c): GetUserByIdUseCase => new GetUserByIdUseCase(self::repository($c)))
            ->set(CreateUserUseCase::class, static fn (ContainerInterface $c): CreateUserUseCase => new CreateUserUseCase(self::repository($c), self::audit($c), self::orgHolder($c)))
            ->set(UpdateUserUseCase::class, static fn (ContainerInterface $c): UpdateUserUseCase => new UpdateUserUseCase(self::repository($c), self::audit($c), self::orgHolder($c)))
            ->set(DeleteUserUseCase::class, static fn (ContainerInterface $c): DeleteUserUseCase => new DeleteUserUseCase(self::repository($c), self::audit($c), self::orgHolder($c)))
            ->set(
                ListUsersHandler::class,
                static fn (ContainerInterface $c): ListUsersHandler => new ListUsersHandler(
                    self::listUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                GetUserByIdHandler::class,
                static fn (ContainerInterface $c): GetUserByIdHandler => new GetUserByIdHandler(
                    self::getUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                CreateUserHandler::class,
                static fn (ContainerInterface $c): CreateUserHandler => new CreateUserHandler(
                    self::createUseCase($c),
                    self::json($c),
                    self::problemDetails($c),
                ),
            )
            ->set(
                UpdateUserHandler::class,
                static fn (ContainerInterface $c): UpdateUserHandler => new UpdateUserHandler(
                    self::updateUseCase($c),
                    self::json($c),
                    self::problemDetails($c),
                ),
            )
            ->set(
                DeleteUserHandler::class,
                static fn (ContainerInterface $c): DeleteUserHandler => new DeleteUserHandler(
                    self::deleteUseCase($c),
                    self::json($c),
                    self::problemDetails($c),
                ),
            )
            ->set(
                UserNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): UserNotFoundExceptionHandler => new UserNotFoundExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                UserEmailConflictExceptionHandler::class,
                static fn (ContainerInterface $c): UserEmailConflictExceptionHandler => new UserEmailConflictExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                RoleNotAssignableExceptionHandler::class,
                static fn (ContainerInterface $c): RoleNotAssignableExceptionHandler => new RoleNotAssignableExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                CannotDeleteSelfExceptionHandler::class,
                static fn (ContainerInterface $c): CannotDeleteSelfExceptionHandler => new CannotDeleteSelfExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                UserRouteRegistrar::class,
                static function (ContainerInterface $c): UserRouteRegistrar {
                    $list = $c->get(ListUsersHandler::class);
                    $get = $c->get(GetUserByIdHandler::class);
                    $create = $c->get(CreateUserHandler::class);
                    $update = $c->get(UpdateUserHandler::class);
                    $delete = $c->get(DeleteUserHandler::class);

                    if (!$list instanceof ListUsersHandler
                        || !$get instanceof GetUserByIdHandler
                        || !$create instanceof CreateUserHandler
                        || !$update instanceof UpdateUserHandler
                        || !$delete instanceof DeleteUserHandler
                    ) {
                        throw new LogicException('User handler services are invalid.');
                    }

                    return new UserRouteRegistrar($list, $get, $create, $update, $delete);
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

    private static function audit(ContainerInterface $c): AuditRecorderInterface
    {
        $recorder = $c->get(AuditRecorderInterface::class);

        if (!$recorder instanceof AuditRecorderInterface) {
            throw new LogicException('Audit recorder service is invalid.');
        }

        return $recorder;
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

    private static function createUseCase(ContainerInterface $c): CreateUserUseCase
    {
        $u = $c->get(CreateUserUseCase::class);

        if (!$u instanceof CreateUserUseCase) {
            throw new LogicException('Create user use case service is invalid.');
        }

        return $u;
    }

    private static function updateUseCase(ContainerInterface $c): UpdateUserUseCase
    {
        $u = $c->get(UpdateUserUseCase::class);

        if (!$u instanceof UpdateUserUseCase) {
            throw new LogicException('Update user use case service is invalid.');
        }

        return $u;
    }

    private static function deleteUseCase(ContainerInterface $c): DeleteUserUseCase
    {
        $u = $c->get(DeleteUserUseCase::class);

        if (!$u instanceof DeleteUserUseCase) {
            throw new LogicException('Delete user use case service is invalid.');
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
