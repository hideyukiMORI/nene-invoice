<?php

declare(strict_types=1);

namespace NeneInvoice\Demo;

use LogicException;
use Nene2\Config\AppConfig;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Demo\CountingDemoCapacityGuard;
use Nene2\Demo\DemoCapacityGuardInterface;
use Nene2\Demo\DisposableOrgReaperInterface;
use Nene2\Demo\StartDisposableDemoHandler;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\ClockInterface;
use Nene2\Middleware\RateLimitStorageInterface;
use NeneInvoice\Auth\RefreshTokenIssuer;
use NeneInvoice\Http\RuntimeServiceProvider;
use NeneInvoice\Organization\CreateOrganizationUseCaseInterface;
use NeneInvoice\Organization\DeleteOrganizationUseCaseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;

/**
 * Wires the disposable-demo domain as a `Nene2\Demo` consumer (#610): the
 * product concretes (provisioner / seeder / seater / reaper), the creation-time
 * capacity guard that closed #608, and the framework handler + route registrar.
 * All dependencies (org creation/deletion, refresh-token issuance, the shared
 * query executor) are reused from existing providers — no auth code is added.
 */
final readonly class DemoServiceProvider implements ServiceProviderInterface
{
    /**
     * Demo starts allowed per client network per window (#612). Deliberately
     * generous: the one-shot cookie design makes legitimate re-clicks normal,
     * and "one IP" is really one office NAT / carrier NAT — while runaway
     * abuse stays bounded by the instance-wide org ceiling plus hourly sweep.
     */
    public const int THROTTLE_LIMIT = 30;
    public const int THROTTLE_WINDOW_SECONDS = 3600;

    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                DemoDataSeeder::class,
                static fn (ContainerInterface $c): DemoDataSeeder => new DemoDataSeeder(
                    self::query($c),
                    self::clock($c),
                ),
            )
            ->set(
                DemoOrgProvisioner::class,
                static function (ContainerInterface $c): DemoOrgProvisioner {
                    $createOrg = $c->get(CreateOrganizationUseCaseInterface::class);

                    if (!$createOrg instanceof CreateOrganizationUseCaseInterface) {
                        throw new LogicException('Create organization use case service is invalid.');
                    }

                    return new DemoOrgProvisioner($createOrg, self::query($c));
                },
            )
            ->set(
                DemoSessionSeater::class,
                static function (ContainerInterface $c): DemoSessionSeater {
                    $refreshTokenIssuer = $c->get(RefreshTokenIssuer::class);
                    $psr17 = $c->get(Psr17Factory::class);

                    if (!$refreshTokenIssuer instanceof RefreshTokenIssuer) {
                        throw new LogicException('Refresh token issuer service is invalid.');
                    }

                    if (!$psr17 instanceof Psr17Factory) {
                        throw new LogicException('PSR-17 factory service is invalid.');
                    }

                    return new DemoSessionSeater($refreshTokenIssuer, $psr17);
                },
            )
            ->set(
                DisposableOrgReaperInterface::class,
                static function (ContainerInterface $c): DisposableOrgReaperInterface {
                    $deleteOrg = $c->get(DeleteOrganizationUseCaseInterface::class);

                    if (!$deleteOrg instanceof DeleteOrganizationUseCaseInterface) {
                        throw new LogicException('Delete organization use case service is invalid.');
                    }

                    return new DemoOrgReaper(self::query($c), $deleteOrg, self::projectRoot($c));
                },
            )
            ->set(
                RateLimitStorageInterface::class,
                static fn (ContainerInterface $c): RateLimitStorageInterface => new FileRateLimitStorage(
                    self::projectRoot($c) . '/var',
                    self::clock($c),
                ),
            )
            ->set(
                DemoCapacityGuardInterface::class,
                static function (ContainerInterface $c): DemoCapacityGuardInterface {
                    $config = self::appConfig($c);
                    $query = self::query($c);
                    $storage = $c->get(RateLimitStorageInterface::class);

                    if (!$storage instanceof RateLimitStorageInterface) {
                        throw new LogicException('Rate limit storage service is invalid.');
                    }

                    return new CountingDemoCapacityGuard(
                        demoOrgCount: static function () use ($query, $config): int {
                            $row = $query->fetchOne(
                                'SELECT COUNT(*) AS cnt FROM organizations WHERE slug LIKE ?',
                                [$config->demo->slugPrefix . '%'],
                            );

                            return $row !== null ? (int) $row['cnt'] : 0;
                        },
                        config: $config->demo,
                        throttleStorage: $storage,
                        throttleLimit: self::THROTTLE_LIMIT,
                        throttleWindowSeconds: self::THROTTLE_WINDOW_SECONDS,
                        clock: self::clock($c),
                    );
                },
            )
            ->set(
                StartDisposableDemoHandler::class,
                static function (ContainerInterface $c): StartDisposableDemoHandler {
                    $guard = $c->get(DemoCapacityGuardInterface::class);
                    $provisioner = $c->get(DemoOrgProvisioner::class);
                    $seeder = $c->get(DemoDataSeeder::class);
                    $seater = $c->get(DemoSessionSeater::class);
                    $problemDetails = $c->get(ProblemDetailsResponseFactory::class);

                    if (!$guard instanceof DemoCapacityGuardInterface) {
                        throw new LogicException('Demo capacity guard service is invalid.');
                    }

                    if (!$provisioner instanceof DemoOrgProvisioner) {
                        throw new LogicException('Demo org provisioner service is invalid.');
                    }

                    if (!$seeder instanceof DemoDataSeeder) {
                        throw new LogicException('Demo data seeder service is invalid.');
                    }

                    if (!$seater instanceof DemoSessionSeater) {
                        throw new LogicException('Demo session seater service is invalid.');
                    }

                    if (!$problemDetails instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('Problem details response factory service is invalid.');
                    }

                    return new StartDisposableDemoHandler(
                        self::appConfig($c)->demo,
                        $guard,
                        $provisioner,
                        $seeder,
                        $seater,
                        $problemDetails,
                        DemoTemplate::class,
                    );
                },
            )
            ->set(
                DemoBrowserErrorPage::class,
                static function (ContainerInterface $c): DemoBrowserErrorPage {
                    $psr17 = $c->get(Psr17Factory::class);

                    if (!$psr17 instanceof Psr17Factory) {
                        throw new LogicException('PSR-17 factory service is invalid.');
                    }

                    return new DemoBrowserErrorPage($psr17, self::THROTTLE_LIMIT, self::THROTTLE_WINDOW_SECONDS);
                },
            )
            ->set(
                DemoRouteRegistrar::class,
                static function (ContainerInterface $c): DemoRouteRegistrar {
                    $handler = $c->get(StartDisposableDemoHandler::class);
                    $errorPage = $c->get(DemoBrowserErrorPage::class);

                    if (!$handler instanceof StartDisposableDemoHandler) {
                        throw new LogicException('Start disposable demo handler service is invalid.');
                    }

                    if (!$errorPage instanceof DemoBrowserErrorPage) {
                        throw new LogicException('Demo browser error page service is invalid.');
                    }

                    return new DemoRouteRegistrar($handler, $errorPage);
                },
            );
    }

    private static function query(ContainerInterface $c): DatabaseQueryExecutorInterface
    {
        $query = $c->get(DatabaseQueryExecutorInterface::class);

        if (!$query instanceof DatabaseQueryExecutorInterface) {
            throw new LogicException('Database query executor service is invalid.');
        }

        return $query;
    }

    private static function clock(ContainerInterface $c): ClockInterface
    {
        $clock = $c->get(ClockInterface::class);

        if (!$clock instanceof ClockInterface) {
            throw new LogicException('Clock service is invalid.');
        }

        return $clock;
    }

    private static function appConfig(ContainerInterface $c): AppConfig
    {
        $config = $c->get(AppConfig::class);

        if (!$config instanceof AppConfig) {
            throw new LogicException('App config service is invalid.');
        }

        return $config;
    }

    private static function projectRoot(ContainerInterface $c): string
    {
        $root = $c->get(RuntimeServiceProvider::PROJECT_ROOT);

        if (!is_string($root) || $root === '') {
            throw new LogicException('Project root service is invalid.');
        }

        return $root;
    }
}
