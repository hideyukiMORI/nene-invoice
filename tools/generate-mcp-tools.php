<?php

declare(strict_types=1);

/**
 * Regenerates docs/mcp/tools.json from declarative read-tool definitions aligned
 * with docs/openapi/openapi.yaml. The MCP catalog exposes read-only operations
 * (aggregated views) for ops / MCP clients; mutating operations are not exposed.
 *
 * Usage: php tools/generate-mcp-tools.php
 *
 * Validated by `composer mcp` (NENE2 validate-mcp-tools.php): each tool's source
 * must match an OpenAPI operation and responseSchemaRef must equal that
 * operation's 200 response schema $ref.
 */

$root = dirname(__DIR__);
$outputPath = $root . '/docs/mcp/tools.json';

/** @return array<string, mixed> */
function limitOffsetProperties(): array
{
    return [
        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
        'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
    ];
}

/** @return array<string, mixed> */
function idProperty(): array
{
    return ['id' => ['type' => 'integer', 'minimum' => 1]];
}

/**
 * @param array<string, mixed> $properties
 * @param list<string> $required
 * @return array<string, mixed>
 */
function readTool(
    string $name,
    string $title,
    string $description,
    string $method,
    string $path,
    string $responseSchemaRef,
    string $safety,
    array $properties = [],
    array $required = [],
): array {
    $inputSchema = [
        'type' => 'object',
        'properties' => $properties === [] ? (object) [] : $properties,
        'additionalProperties' => false,
    ];

    if ($required !== []) {
        $inputSchema['required'] = $required;
    }

    return [
        'name' => $name,
        'title' => $title,
        'description' => $description,
        'safety' => $safety,
        'source' => [
            'type' => 'openapi',
            'operationId' => $name,
            'method' => $method,
            'path' => $path,
        ],
        'inputSchema' => $inputSchema,
        'responseSchemaRef' => $responseSchemaRef,
    ];
}

$tools = [
    readTool('getHealth', 'Health', 'Operational health check for the NeNe Invoice API runtime.', 'GET', '/health', '#/components/schemas/HealthStatus', 'read'),
    readTool('getCurrentUser', 'Current user', 'Returns the authenticated user resolved from the bearer token.', 'GET', '/admin/me', '#/components/schemas/CurrentUser', 'read'),
    readTool('listAuditLogs', 'List audit logs', 'Lists the organization audit trail, newest first (admin oversight).', 'GET', '/admin/audit-logs', '#/components/schemas/AuditLogList', 'admin', limitOffsetProperties()),
    readTool('listOrganizations', 'List organizations', 'Lists tenant organizations (superadmin).', 'GET', '/admin/organizations', '#/components/schemas/OrganizationList', 'admin', limitOffsetProperties()),
    readTool('getOrganizationById', 'Get organization', 'Returns one organization by id (superadmin).', 'GET', '/admin/organizations/{id}', '#/components/schemas/Organization', 'admin', idProperty(), ['id']),
    readTool('listUsers', 'List users', 'Lists users in the caller organization (admin).', 'GET', '/admin/users', '#/components/schemas/UserList', 'admin', limitOffsetProperties()),
    readTool('getUserById', 'Get user', 'Returns one user by id (admin).', 'GET', '/admin/users/{id}', '#/components/schemas/User', 'admin', idProperty(), ['id']),
    readTool('getCompanySettings', 'Get company settings', 'Returns the issuer profile for the caller organization.', 'GET', '/admin/company-settings', '#/components/schemas/CompanySettings', 'read'),
    readTool('listClients', 'List clients', 'Lists clients (trading partners) in the caller organization.', 'GET', '/admin/clients', '#/components/schemas/ClientList', 'read', limitOffsetProperties()),
    readTool('getClientById', 'Get client', 'Returns one client by id.', 'GET', '/admin/clients/{id}', '#/components/schemas/Client', 'read', idProperty(), ['id']),
    readTool('listQuotes', 'List quotes', 'Lists quotes (estimates) in the caller organization.', 'GET', '/admin/quotes', '#/components/schemas/QuoteList', 'read', limitOffsetProperties()),
    readTool('getQuoteById', 'Get quote', 'Returns one quote with its line items.', 'GET', '/admin/quotes/{id}', '#/components/schemas/QuoteWithLines', 'read', idProperty(), ['id']),
    readTool('listInvoices', 'List invoices', 'Lists invoices in the caller organization.', 'GET', '/admin/invoices', '#/components/schemas/InvoiceList', 'read', limitOffsetProperties()),
    readTool('getInvoiceById', 'Get invoice', 'Returns one invoice with its line items.', 'GET', '/admin/invoices/{id}', '#/components/schemas/InvoiceWithLines', 'read', idProperty(), ['id']),
    readTool('listPayments', 'List payments', 'Lists the payments recorded against an invoice.', 'GET', '/admin/invoices/{id}/payments', '#/components/schemas/PaymentList', 'read', idProperty(), ['id']),
];

$catalog = [
    'version' => 1,
    'source' => 'docs/openapi/openapi.yaml',
    'tools' => $tools,
];

file_put_contents(
    $outputPath,
    json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
);

echo sprintf("Wrote %d MCP tools to %s\n", count($tools), $outputPath);
