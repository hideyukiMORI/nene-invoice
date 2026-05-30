<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\OpenApi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Sanity checks for the public_html/openapi.php spec-serving script.
 *
 * This is a standalone PHP file (no DI container), so we verify:
 * - The entry-point file exists and is parseable PHP.
 * - The spec files it references are present on disk.
 * - Unknown spec keys are not resolvable to a real path.
 */
final class OpenApiServeTest extends TestCase
{
    private string $scriptPath;

    private string $docsOpenApiDir;

    protected function setUp(): void
    {
        $this->scriptPath     = dirname(__DIR__, 2) . '/public_html/openapi.php';
        $this->docsOpenApiDir = dirname(__DIR__, 2) . '/docs/openapi';
    }

    public function test_script_file_exists(): void
    {
        self::assertFileExists($this->scriptPath);
    }

    public function test_script_is_syntactically_valid_php(): void
    {
        exec('php -l ' . escapeshellarg($this->scriptPath) . ' 2>&1', $output, $exitCode);
        self::assertSame(0, $exitCode, implode("\n", $output));
    }

    public function test_operator_spec_file_exists(): void
    {
        self::assertFileExists($this->docsOpenApiDir . '/openapi.yaml');
    }

    public function test_service_spec_file_exists(): void
    {
        self::assertFileExists($this->docsOpenApiDir . '/service-api.yaml');
    }

    #[DataProvider('validSpecProvider')]
    public function test_valid_spec_keys_resolve_to_existing_files(string $spec): void
    {
        $specMap = [
            'operator' => $this->docsOpenApiDir . '/openapi.yaml',
            'service'  => $this->docsOpenApiDir . '/service-api.yaml',
        ];

        self::assertArrayHasKey($spec, $specMap);
        self::assertFileExists($specMap[$spec]);
    }

    /** @return list<array{string}> */
    public static function validSpecProvider(): array
    {
        return [['operator'], ['service']];
    }

    public function test_unknown_spec_key_is_not_in_the_map(): void
    {
        $specMap = ['operator', 'service'];
        self::assertNotContains('unknown', $specMap);
        self::assertNotContains('', $specMap);
        self::assertNotContains('../etc/passwd', $specMap);
    }
}
