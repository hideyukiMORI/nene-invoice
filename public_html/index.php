<?php

declare(strict_types=1);

use Nene2\Config\AppConfig;
use Nene2\Http\ResponseEmitter;
use NeneInvoice\Http\BasePath;
use NeneInvoice\Http\RuntimeContainerFactory;
use NeneInvoice\Http\SpaBasePlan;
use NeneInvoice\Http\SpaShell;
use NeneInvoice\Organization\OrganizationRepositoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Server\RequestHandlerInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

// php built-in server only: serve real static files (admin/assets/*, favicons)
// as-is by returning false from the router script. Production Apache handles this
// via the `.htaccess` `!-f` condition and never invokes index.php for existing
// files, so this branch is dead there (and guarded to the cli-server SAPI, which
// only the php built-in server uses, to be safe).
if (PHP_SAPI === 'cli-server') {
    $requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $candidate = is_string($requestedPath) ? realpath(__DIR__ . $requestedPath) : false;

    if ($candidate !== false && is_file($candidate) && str_starts_with($candidate, __DIR__ . DIRECTORY_SEPARATOR)) {
        return false;
    }
}

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

// Merge the install base (ADR 0015) with the path-tenancy org prefix (型B Phase
// 2): under `path` mode a leading `/<slug>/` that names a real org makes the
// shell serve the tenant SPA with `app-base=<base>/<slug>/`, so its router and
// API calls stay under the slug. The slug lookup only runs in path mode.
$modeRaw = $_ENV['TENANT_RESOLUTION'] ?? getenv('TENANT_RESOLUTION');
$mode    = is_string($modeRaw) && $modeRaw !== '' ? $modeRaw : 'single';

$spaPlan = SpaBasePlan::resolve(
    $base,
    $strippedPath,
    $mode,
    static function (string $slug) use ($container): bool {
        $repository = $container->get(OrganizationRepositoryInterface::class);
        assert($repository instanceof OrganizationRepositoryInterface);

        return $repository->findBySlug($slug) !== null;
    },
);

// Non-API GET/HEAD requests get the SPA shell (with the base injected), which
// also serves deep-links / F5. Everything else goes to the JSON API router.
$method = $request->getMethod();
$response = null;

if (($method === 'GET' || $method === 'HEAD') && !BasePath::isApiPath($spaPlan->spaPath)) {
    // Demo-only, env-gated cookieless analytics (#658): the disposable-demo host
    // sets DEMO_ANALYTICS_ENDPOINT in its .env; every other install leaves it
    // unset so no beacon and no analytics CSP are ever emitted. The origin
    // literal lives only in that .env — never in .env.example or the built SPA.
    $analyticsEndpointRaw = $_ENV['DEMO_ANALYTICS_ENDPOINT'] ?? getenv('DEMO_ANALYTICS_ENDPOINT');
    $analyticsEndpoint = is_string($analyticsEndpointRaw) && $analyticsEndpointRaw !== '' ? $analyticsEndpointRaw : null;

    $shell = new SpaShell($projectRoot . '/public_html/admin/index.html', $psr17Factory, $psr17Factory, $analyticsEndpoint);
    $response = $shell->serve($spaPlan->assetBase, $spaPlan->appBase);
}

if ($response === null) {
    $application = $container->get(RequestHandlerInterface::class);
    assert($application instanceof RequestHandlerInterface);
    $response = $application->handle($request);
}

$emitter = $container->get(ResponseEmitter::class);
assert($emitter instanceof ResponseEmitter);
$emitter->emit($response);
