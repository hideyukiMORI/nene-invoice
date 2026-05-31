<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\OpenApi;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Contract guards for docs/openapi/openapi.yaml. Complements
 * tools/validate-openapi.php (which checks $ref resolution): asserts the
 * documented operation set matches the implemented endpoints, that every
 * operation is well-formed, and that the MCP catalog stays aligned.
 */
final class OpenApiContractTest extends TestCase
{
    /**
     * The canonical set of operationIds the API implements. Adding or removing an
     * endpoint must update this set, the OpenAPI spec, and terminology.md together.
     *
     * @var list<string>
     */
    private const EXPECTED_OPERATION_IDS = [
        'getHealth',
        'getDashboard',
        'login',
        'getCurrentUser',
        'listAuditLogs',
        'listOrganizations',
        'getOrganizationById',
        'createOrganization',
        'deleteOrganization',
        'listUsers',
        'getUserById',
        'createUser',
        'updateUser',
        'deleteUser',
        'getCompanySettings',
        'updateCompanySettings',
        'listClients',
        'getClientById',
        'createClient',
        'updateClient',
        'deleteClient',
        'listQuotes',
        'getQuoteById',
        'createQuote',
        'changeQuoteStatus',
        'getQuotePdf',
        'convertQuoteToInvoice',
        'listInvoices',
        'getInvoiceById',
        'createInvoice',
        'getInvoicePdf',
        'generateDownloadToken',
        'downloadInvoicePdf',
        'issueInvoice',
        'listPayments',
        'recordPayment',
        'sendInvoiceEmail',
    ];

    /** @var array<string, mixed> */
    private static array $document = [];

    public static function setUpBeforeClass(): void
    {
        $parsed = Yaml::parseFile(dirname(__DIR__, 2) . '/docs/openapi/openapi.yaml');
        self::assertIsArray($parsed);
        self::$document = $parsed;
    }

    public function test_documented_operations_match_the_implemented_set(): void
    {
        $documented = $this->operationIds();

        sort($documented);
        $expected = self::EXPECTED_OPERATION_IDS;
        sort($expected);

        self::assertSame($expected, $documented, 'OpenAPI operationIds drifted from the implemented endpoints.');
    }

    public function test_operation_ids_are_unique(): void
    {
        $ids = $this->operationIds();

        self::assertSame(count($ids), count(array_unique($ids)), 'Duplicate operationId in the OpenAPI spec.');
    }

    public function test_every_operation_is_well_formed(): void
    {
        foreach ($this->operations() as $key => $operation) {
            self::assertArrayHasKey('operationId', $operation, "Missing operationId at {$key}");
            self::assertArrayHasKey('summary', $operation, "Missing summary at {$key}");
            self::assertArrayHasKey('tags', $operation, "Missing tags at {$key}");
            self::assertArrayHasKey('responses', $operation, "Missing responses at {$key}");
            self::assertMatchesRegularExpression('/^[a-z][a-zA-Z]+$/', (string) $operation['operationId'], "operationId not camelCase at {$key}");
        }
    }

    public function test_id_paths_declare_an_id_parameter(): void
    {
        $paths = self::$document['paths'];
        self::assertIsArray($paths);

        foreach ($paths as $path => $pathItem) {
            if (!str_contains((string) $path, '{id}') || !is_array($pathItem)) {
                continue;
            }

            $hasPathLevel = $this->declaresIdParam($pathItem['parameters'] ?? null);

            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (!isset($pathItem[$method]) || !is_array($pathItem[$method])) {
                    continue;
                }

                $hasOpLevel = $this->declaresIdParam($pathItem[$method]['parameters'] ?? null);
                self::assertTrue($hasPathLevel || $hasOpLevel, "Path {$path} {$method} does not declare an id parameter.");
            }
        }
    }

    public function test_mcp_catalog_tools_reference_documented_operations(): void
    {
        $raw = file_get_contents(dirname(__DIR__, 2) . '/docs/mcp/tools.json');
        self::assertIsString($raw);
        $catalog = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($catalog);
        self::assertArrayHasKey('tools', $catalog);
        self::assertIsArray($catalog['tools']);

        $documented = $this->operationIds();

        foreach ($catalog['tools'] as $tool) {
            self::assertIsArray($tool);
            $operationId = $tool['source']['operationId'] ?? null;
            self::assertIsString($operationId);
            self::assertContains($operationId, $documented, "MCP tool references unknown operationId {$operationId}");
        }
    }

    /**
     * @param mixed $parameters
     */
    private function declaresIdParam($parameters): bool
    {
        if (!is_array($parameters)) {
            return false;
        }

        foreach ($parameters as $parameter) {
            if (is_array($parameter) && (($parameter['name'] ?? null) === 'id' || str_contains((string) ($parameter['$ref'] ?? ''), 'IdPathParam'))) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function operationIds(): array
    {
        $ids = [];

        foreach ($this->operations() as $operation) {
            if (isset($operation['operationId']) && is_string($operation['operationId'])) {
                $ids[] = $operation['operationId'];
            }
        }

        return $ids;
    }

    /** @return array<string, array<string, mixed>> */
    private function operations(): array
    {
        $paths = self::$document['paths'] ?? [];
        self::assertIsArray($paths);

        $operations = [];

        foreach ($paths as $path => $pathItem) {
            if (!is_array($pathItem)) {
                continue;
            }

            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (isset($pathItem[$method]) && is_array($pathItem[$method])) {
                    $operations[$method . ' ' . (string) $path] = $pathItem[$method];
                }
            }
        }

        return $operations;
    }
}
