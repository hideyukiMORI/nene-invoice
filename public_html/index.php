<?php

declare(strict_types=1);

use Nene2\Config\AppConfig;
use Nene2\Http\ResponseEmitter;
use NeneInvoice\Http\BasePath;
use NeneInvoice\Http\RuntimeContainerFactory;
use NeneInvoice\Http\SpaShell;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Server\RequestHandlerInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

// Do not advertise the PHP version (defense in depth; `expose_php` may be On).
header_remove('X-Powered-By');

$projectRoot = dirname(__DIR__);
$container = (new RuntimeContainerFactory($projectRoot))->create();

$psr17Factory = $container->get(Psr17Factory::class);
assert($psr17Factory instanceof Psr17Factory);

$serverRequestCreator = new ServerRequestCreator(
    $psr17Factory,
    $psr17Factory,
    $psr17Factory,
    $psr17Factory,
);

$request = $serverRequestCreator->fromGlobals();

// --- Location-independent install (ADR 0015) ---------------------------------
// Detect the URL base the app is installed under (document root / subdomain /
// subdirectory) so one artifact runs anywhere. Resolving AppConfig first loads
// `.env`, making the optional APP_BASE_PATH override available.
$container->get(AppConfig::class);
$override = $_SERVER['APP_BASE_PATH'] ?? $_ENV['APP_BASE_PATH'] ?? null;
$base = BasePath::detect($request->getServerParams(), is_string($override) ? $override : null);

$strippedPath = BasePath::strip($request->getUri()->getPath(), $base);
$request = $request
    ->withUri($request->getUri()->withPath($strippedPath))
    ->withAttribute(BasePath::REQUEST_ATTRIBUTE, $base);

// Non-API GET/HEAD requests get the SPA shell (with the base injected), which
// also serves deep-links / F5. Everything else goes to the JSON API router.
$method = $request->getMethod();
$response = null;

if (($method === 'GET' || $method === 'HEAD') && !BasePath::isApiPath($strippedPath)) {
    $shell = new SpaShell($projectRoot . '/public_html/admin/index.html', $psr17Factory, $psr17Factory);
    $response = $shell->serve($base);
}

if ($response === null) {
    $application = $container->get(RequestHandlerInterface::class);
    assert($application instanceof RequestHandlerInterface);
    $response = $application->handle($request);
}

$emitter = $container->get(ResponseEmitter::class);
assert($emitter instanceof ResponseEmitter);
$emitter->emit($response);
