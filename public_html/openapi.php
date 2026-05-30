<?php

declare(strict_types=1);

/**
 * Serves OpenAPI spec documents over HTTP without authentication.
 *
 * Usage:
 *   GET /openapi.php             — operator API spec (docs/openapi/openapi.yaml)
 *   GET /openapi.php?spec=service — service API spec (docs/openapi/service-api.yaml)
 */

$specMap = [
    'operator' => dirname(__DIR__) . '/docs/openapi/openapi.yaml',
    'service'  => dirname(__DIR__) . '/docs/openapi/service-api.yaml',
];

$specKey = isset($_GET['spec']) && is_string($_GET['spec']) ? $_GET['spec'] : 'operator';

if (!array_key_exists($specKey, $specMap)) {
    http_response_code(404);
    header('Content-Type: application/problem+json; charset=utf-8');
    echo json_encode([
        'type'   => 'https://nene-invoice.dev/problems/not-found',
        'title'  => 'Not Found',
        'status' => 404,
        'detail' => sprintf('Unknown spec "%s". Valid values: operator, service.', $specKey),
    ], JSON_THROW_ON_ERROR);
    exit;
}

$filePath = $specMap[$specKey];

if (!file_exists($filePath)) {
    http_response_code(404);
    header('Content-Type: application/problem+json; charset=utf-8');
    echo json_encode([
        'type'   => 'https://nene-invoice.dev/problems/not-found',
        'title'  => 'Not Found',
        'status' => 404,
        'detail' => 'Spec file not found on this server.',
    ], JSON_THROW_ON_ERROR);
    exit;
}

header('Content-Type: application/yaml; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=3600');
readfile($filePath);
