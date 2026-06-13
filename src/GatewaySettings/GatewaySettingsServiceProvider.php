<?php

declare(strict_types=1);

namespace NeneInvoice\GatewaySettings;

use LogicException;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Payment\Gateway\PaymentGatewayInterface;
use Psr\Container\ContainerInterface;

/**
 * Wires the read-only gateway settings endpoints. Credentials stay in the
 * environment (ADR 0013 / Issue #432 chose env over DB secret storage); this
 * provider reads only their presence and a masked public key.
 */
final readonly class GatewaySettingsServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                GetGatewaySettingsHandler::class,
                static fn (ContainerInterface $c): GetGatewaySettingsHandler => new GetGatewaySettingsHandler(
                    self::resolve($c, JsonResponseFactory::class),
                    (string) (getenv('PAYJP_PUBLIC_KEY') ?: ''),
                    (getenv('PAYJP_SECRET_KEY') ?: '') !== '',
                    (getenv('PAYJP_WEBHOOK_TOKEN') ?: '') !== '',
                ),
            )
            ->set(
                TestGatewayConnectivityHandler::class,
                static fn (ContainerInterface $c): TestGatewayConnectivityHandler => new TestGatewayConnectivityHandler(
                    self::resolve($c, PaymentGatewayInterface::class),
                    self::resolve($c, JsonResponseFactory::class),
                    (getenv('PAYJP_SECRET_KEY') ?: '') !== '',
                ),
            )
            ->set(
                GatewaySettingsRouteRegistrar::class,
                static fn (ContainerInterface $c): GatewaySettingsRouteRegistrar => new GatewaySettingsRouteRegistrar(
                    self::resolve($c, GetGatewaySettingsHandler::class),
                    self::resolve($c, TestGatewayConnectivityHandler::class),
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
}
