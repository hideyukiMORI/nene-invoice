<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use LogicException;
use Nene2\Auth\BearerTokenMiddleware;
use Nene2\Auth\LocalBearerTokenVerifier;
use Nene2\Auth\TokenIssuerInterface;
use Nene2\Auth\TokenVerifierInterface;
use Nene2\Config\AppConfig;
use Nene2\Config\AppEnvironment;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ApplicationServiceProvider;
use NeneInvoice\User\PdoUserRepository;
use NeneInvoice\User\UserRepositoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;

/**
 * Wires authentication services: token issuer/verifier, user repository, HTTP
 * response factories, and the login use case / handler / route registrar.
 */
final readonly class AuthServiceProvider implements ServiceProviderInterface
{
    /**
     * Development-only fallback secret, used **only** in local/test when
     * NENE2_LOCAL_JWT_SECRET is unset. Production must set its own secret —
     * see {@see self::resolveJwtSecret()}. This constant is public in the OSS
     * repository, so signing real tokens with it would be a full auth bypass.
     */
    private const DEFAULT_DEV_SECRET = 'nene-invoice-dev-secret';

    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                LocalBearerTokenVerifier::class,
                static function (ContainerInterface $container): LocalBearerTokenVerifier {
                    $config = $container->get(AppConfig::class);

                    if (!$config instanceof AppConfig) {
                        throw new LogicException('Application config service is invalid.');
                    }

                    return new LocalBearerTokenVerifier(self::resolveJwtSecret($config));
                },
            )
            ->set(
                TokenIssuerInterface::class,
                static function (ContainerInterface $container): TokenIssuerInterface {
                    $verifier = $container->get(LocalBearerTokenVerifier::class);

                    if (!$verifier instanceof TokenIssuerInterface) {
                        throw new LogicException('Token issuer service is invalid.');
                    }

                    return $verifier;
                },
            )
            ->set(
                TokenVerifierInterface::class,
                static function (ContainerInterface $container): TokenVerifierInterface {
                    $verifier = $container->get(LocalBearerTokenVerifier::class);

                    if (!$verifier instanceof TokenVerifierInterface) {
                        throw new LogicException('Token verifier service is invalid.');
                    }

                    return $verifier;
                },
            )
            ->set(
                UserRepositoryInterface::class,
                static function (ContainerInterface $container): UserRepositoryInterface {
                    $query = $container->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoUserRepository($query, self::orgHolder($container));
                },
            )
            ->set(
                JsonResponseFactory::class,
                static function (ContainerInterface $container): JsonResponseFactory {
                    $psr17 = $container->get(Psr17Factory::class);

                    if (!$psr17 instanceof Psr17Factory) {
                        throw new LogicException('PSR-17 factory service is invalid.');
                    }

                    return new JsonResponseFactory($psr17, $psr17);
                },
            )
            ->set(
                ProblemDetailsResponseFactory::class,
                static function (ContainerInterface $container): ProblemDetailsResponseFactory {
                    $psr17 = $container->get(Psr17Factory::class);
                    $config = $container->get(AppConfig::class);

                    if (!$psr17 instanceof Psr17Factory) {
                        throw new LogicException('PSR-17 factory service is invalid.');
                    }

                    if (!$config instanceof AppConfig) {
                        throw new LogicException('Application config service is invalid.');
                    }

                    return new ProblemDetailsResponseFactory($psr17, $psr17, $config->problemDetailsBaseUrl);
                },
            )
            ->set(
                BearerTokenMiddleware::class,
                static function (ContainerInterface $container): BearerTokenMiddleware {
                    $problemDetails = $container->get(ProblemDetailsResponseFactory::class);
                    $verifier = $container->get(TokenVerifierInterface::class);

                    if (!$problemDetails instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('Problem details response factory service is invalid.');
                    }

                    if (!$verifier instanceof TokenVerifierInterface) {
                        throw new LogicException('Token verifier service is invalid.');
                    }

                    // Protect everything under /admin/ (operator) and /api/ (service
                    // tokens — ADR 0009). Public routes (/, /health, /auth/login) are
                    // not matched by the prefix and pass through.
                    return new BearerTokenMiddleware(
                        $problemDetails,
                        $verifier,
                        protectedPathPrefixes: ['/admin/', '/api/'],
                    );
                },
            )
            ->set(
                CapabilityMiddleware::class,
                static function (ContainerInterface $container): CapabilityMiddleware {
                    $problemDetails = $container->get(ProblemDetailsResponseFactory::class);

                    if (!$problemDetails instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('Problem details response factory service is invalid.');
                    }

                    return new CapabilityMiddleware($problemDetails);
                },
            )
            ->set(
                RefreshTokenRepositoryInterface::class,
                static function (ContainerInterface $container): RefreshTokenRepositoryInterface {
                    $query = $container->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoRefreshTokenRepository($query);
                },
            )
            ->set(
                RefreshTokenIssuer::class,
                static function (ContainerInterface $container): RefreshTokenIssuer {
                    $repository = $container->get(RefreshTokenRepositoryInterface::class);

                    if (!$repository instanceof RefreshTokenRepositoryInterface) {
                        throw new LogicException('Refresh token repository service is invalid.');
                    }

                    return new RefreshTokenIssuer($repository);
                },
            )
            ->set(
                LoginUseCaseInterface::class,
                static function (ContainerInterface $container): LoginUseCaseInterface {
                    $users = $container->get(UserRepositoryInterface::class);
                    $tokenIssuer = $container->get(TokenIssuerInterface::class);
                    $query = $container->get(DatabaseQueryExecutorInterface::class);
                    $refreshTokenIssuer = $container->get(RefreshTokenIssuer::class);

                    if (!$users instanceof UserRepositoryInterface) {
                        throw new LogicException('User repository service is invalid.');
                    }

                    if (!$tokenIssuer instanceof TokenIssuerInterface) {
                        throw new LogicException('Token issuer service is invalid.');
                    }

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    if (!$refreshTokenIssuer instanceof RefreshTokenIssuer) {
                        throw new LogicException('Refresh token issuer service is invalid.');
                    }

                    return new LoginUseCase($users, $tokenIssuer, new PdoLoginThrottle($query), $refreshTokenIssuer);
                },
            )
            ->set(
                RefreshSessionUseCaseInterface::class,
                static function (ContainerInterface $container): RefreshSessionUseCaseInterface {
                    $repository = $container->get(RefreshTokenRepositoryInterface::class);
                    $users = $container->get(UserRepositoryInterface::class);
                    $issuer = $container->get(RefreshTokenIssuer::class);
                    $tokenIssuer = $container->get(TokenIssuerInterface::class);

                    if (!$repository instanceof RefreshTokenRepositoryInterface) {
                        throw new LogicException('Refresh token repository service is invalid.');
                    }

                    if (!$users instanceof UserRepositoryInterface) {
                        throw new LogicException('User repository service is invalid.');
                    }

                    if (!$issuer instanceof RefreshTokenIssuer) {
                        throw new LogicException('Refresh token issuer service is invalid.');
                    }

                    if (!$tokenIssuer instanceof TokenIssuerInterface) {
                        throw new LogicException('Token issuer service is invalid.');
                    }

                    return new RefreshSessionUseCase($repository, $users, $issuer, $tokenIssuer);
                },
            )
            ->set(
                LogoutUseCaseInterface::class,
                static function (ContainerInterface $container): LogoutUseCaseInterface {
                    $repository = $container->get(RefreshTokenRepositoryInterface::class);

                    if (!$repository instanceof RefreshTokenRepositoryInterface) {
                        throw new LogicException('Refresh token repository service is invalid.');
                    }

                    return new LogoutUseCase($repository);
                },
            )
            ->set(
                LoginHandler::class,
                static function (ContainerInterface $container): LoginHandler {
                    $useCase = $container->get(LoginUseCaseInterface::class);
                    $json = $container->get(JsonResponseFactory::class);
                    $problemDetails = $container->get(ProblemDetailsResponseFactory::class);

                    if (!$useCase instanceof LoginUseCaseInterface) {
                        throw new LogicException('Login use case service is invalid.');
                    }

                    if (!$json instanceof JsonResponseFactory) {
                        throw new LogicException('JSON response factory service is invalid.');
                    }

                    if (!$problemDetails instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('Problem details response factory service is invalid.');
                    }

                    return new LoginHandler($useCase, $json, $problemDetails);
                },
            )
            ->set(
                GetCurrentUserUseCaseInterface::class,
                static function (ContainerInterface $container): GetCurrentUserUseCaseInterface {
                    $users = $container->get(UserRepositoryInterface::class);

                    if (!$users instanceof UserRepositoryInterface) {
                        throw new LogicException('User repository service is invalid.');
                    }

                    return new GetCurrentUserUseCase($users);
                },
            )
            ->set(
                GetCurrentUserHandler::class,
                static function (ContainerInterface $container): GetCurrentUserHandler {
                    $useCase = $container->get(GetCurrentUserUseCaseInterface::class);
                    $json = $container->get(JsonResponseFactory::class);
                    $problemDetails = $container->get(ProblemDetailsResponseFactory::class);

                    if (!$useCase instanceof GetCurrentUserUseCaseInterface) {
                        throw new LogicException('Get current user use case service is invalid.');
                    }

                    if (!$json instanceof JsonResponseFactory) {
                        throw new LogicException('JSON response factory service is invalid.');
                    }

                    if (!$problemDetails instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('Problem details response factory service is invalid.');
                    }

                    return new GetCurrentUserHandler($useCase, $json, $problemDetails);
                },
            )
            ->set(
                RefreshHandler::class,
                static function (ContainerInterface $container): RefreshHandler {
                    $useCase = $container->get(RefreshSessionUseCaseInterface::class);
                    $json = $container->get(JsonResponseFactory::class);
                    $problemDetails = $container->get(ProblemDetailsResponseFactory::class);

                    if (!$useCase instanceof RefreshSessionUseCaseInterface) {
                        throw new LogicException('Refresh session use case service is invalid.');
                    }

                    if (!$json instanceof JsonResponseFactory) {
                        throw new LogicException('JSON response factory service is invalid.');
                    }

                    if (!$problemDetails instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('Problem details response factory service is invalid.');
                    }

                    return new RefreshHandler($useCase, $json, $problemDetails);
                },
            )
            ->set(
                LogoutHandler::class,
                static function (ContainerInterface $container): LogoutHandler {
                    $useCase = $container->get(LogoutUseCaseInterface::class);
                    $json = $container->get(JsonResponseFactory::class);
                    $problemDetails = $container->get(ProblemDetailsResponseFactory::class);

                    if (!$useCase instanceof LogoutUseCaseInterface) {
                        throw new LogicException('Logout use case service is invalid.');
                    }

                    if (!$json instanceof JsonResponseFactory) {
                        throw new LogicException('JSON response factory service is invalid.');
                    }

                    if (!$problemDetails instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('Problem details response factory service is invalid.');
                    }

                    return new LogoutHandler($useCase, $json, $problemDetails);
                },
            )
            ->set(
                AuthRouteRegistrar::class,
                static function (ContainerInterface $container): AuthRouteRegistrar {
                    $loginHandler = $container->get(LoginHandler::class);
                    $getCurrentUserHandler = $container->get(GetCurrentUserHandler::class);
                    $refreshHandler = $container->get(RefreshHandler::class);
                    $logoutHandler = $container->get(LogoutHandler::class);

                    if (!$loginHandler instanceof LoginHandler) {
                        throw new LogicException('Login handler service is invalid.');
                    }

                    if (!$getCurrentUserHandler instanceof GetCurrentUserHandler) {
                        throw new LogicException('Get current user handler service is invalid.');
                    }

                    if (!$refreshHandler instanceof RefreshHandler) {
                        throw new LogicException('Refresh handler service is invalid.');
                    }

                    if (!$logoutHandler instanceof LogoutHandler) {
                        throw new LogicException('Logout handler service is invalid.');
                    }

                    return new AuthRouteRegistrar($loginHandler, $getCurrentUserHandler, $refreshHandler, $logoutHandler);
                },
            );
    }

    /**
     * Resolves the HMAC secret for local bearer tokens, failing closed.
     *
     * The same secret signs operator and service tokens, so a predictable value
     * is a full authentication bypass (a forged superadmin token). In production
     * the secret is therefore mandatory: if NENE2_LOCAL_JWT_SECRET is unset we
     * refuse to boot rather than silently fall back to the public dev constant
     * (Round 3 finding M-1). Local/test may use the dev fallback for convenience.
     */
    private static function resolveJwtSecret(AppConfig $config): string
    {
        $secret = $config->localJwtSecret;

        if ($secret !== null) {
            return $secret;
        }

        if ($config->environment === AppEnvironment::Production) {
            throw new LogicException(
                'NENE2_LOCAL_JWT_SECRET must be set in production. '
                . 'Generate one with: php -r "echo bin2hex(random_bytes(32));"',
            );
        }

        return self::DEFAULT_DEV_SECRET;
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
