<?php

declare(strict_types=1);

/**
 * Conformance linter shim — runs the NENE2 fleet conformance rules
 * (`Nene2\Conformance\*`, ADR 0016, error-tier D1–D4) against this repo.
 *
 * WHY THIS LOCAL SHIM EXISTS (Packagist pilot finding, 2026-07-07):
 *   The upstream tool `vendor/hideyukimori/nene2/tools/conformance.php` (v1.8.1)
 *   bootstraps with `require dirname(__DIR__) . '/vendor/autoload.php'`, i.e. it
 *   expects NENE2's *own* vendor/autoload.php. That path only exists when NENE2
 *   is checked out standalone (or installed as a path/symlink dependency). For a
 *   Packagist consumer like nene-invoice, NENE2's dependencies are flattened into
 *   this project's vendor/, so `vendor/hideyukimori/nene2/vendor/autoload.php`
 *   does not exist and the upstream tool fatals before it can run.
 *
 *   The sibling validator `validate-mcp-tools.php` avoids this by requiring
 *   `$projectRoot/vendor/autoload.php` (which respects --root / getcwd()). Once
 *   NENE2 fixes conformance.php the same way (see the pilot report / issue),
 *   delete this shim and point `composer conformance` back at the vendor tool:
 *     "conformance": "php vendor/hideyukimori/nene2/tools/conformance.php --root=."
 *
 *   This shim lives in the consumer's tools/, so `dirname(__DIR__)` correctly
 *   resolves to this project's root and the consumer autoload is used. The rule
 *   logic itself is 100% upstream (Nene2\Conformance\* from the NENE2 package);
 *   only the bootstrap line differs.
 *
 * Usage:
 *   php tools/conformance.php [--root=PATH] [--format=text|json] [--write-baseline]
 *
 * Exit codes: 0 = no active errors, 1 = active error findings, 2 = usage/config error.
 */

use Nene2\Conformance\Baseline;
use Nene2\Conformance\ConformanceRunner;
use Nene2\Conformance\Finding;
use Nene2\Conformance\RunResult;

$cwd = getcwd();
$projectRoot = $cwd !== false ? $cwd : dirname(__DIR__);
$format = 'text';
$writeBaseline = false;

foreach ($argv as $index => $arg) {
    if ($index === 0) {
        continue;
    }

    if (str_starts_with($arg, '--root=')) {
        $explicit = substr($arg, 7);
        $projectRoot = realpath($explicit) ?: $explicit;
    } elseif (str_starts_with($arg, '--format=')) {
        $format = substr($arg, 9);
    } elseif ($arg === '--write-baseline') {
        $writeBaseline = true;
    } else {
        fwrite(STDERR, "Unknown argument: {$arg}\n");
        exit(2);
    }
}

if (!in_array($format, ['text', 'json'], true)) {
    fwrite(STDERR, "Unknown --format '{$format}' (expected 'text' or 'json').\n");
    exit(2);
}

require dirname(__DIR__) . '/vendor/autoload.php';

if (!class_exists(ConformanceRunner::class)) {
    fwrite(STDERR, "Error: Nene2\\Conformance is not available (require hideyukimori/nene2 ^1.8.1).\n");
    exit(2);
}

$root = rtrim(str_replace('\\', '/', $projectRoot), '/');
$baselinePath = $root . '/' . ConformanceRunner::BASELINE_FILENAME;
$runner = ConformanceRunner::withDefaultRules();
$baseline = Baseline::load($baselinePath);

if ($baseline->validationErrors() !== []) {
    foreach ($baseline->validationErrors() as $error) {
        fwrite(STDERR, "Baseline error: {$error}\n");
    }

    exit(2);
}

if ($writeBaseline) {
    $data = $runner->buildBaseline($root, $baseline);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false || file_put_contents($baselinePath, $json . "\n") === false) {
        fwrite(STDERR, "Could not write baseline to {$baselinePath}\n");
        exit(2);
    }

    fwrite(STDERR, sprintf("Wrote baseline with %d ignore entrie(s) to %s\n", count($data['ignore']), $baselinePath));
    exit(0);
}

$result = $runner->run($root, $baseline);

if ($format === 'json') {
    echo renderJson($result), "\n";
} else {
    fwrite(STDERR, renderText($result));
}

exit($result->exitCode());

function renderText(RunResult $result): string
{
    $errors = $result->errors();

    if ($errors === []) {
        return sprintf(
            "Conformance OK — no error findings (%d suppressed by baseline/ignore).\n",
            $result->suppressed,
        );
    }

    $lines = [sprintf(
        "Conformance: %d error finding(s), %d suppressed by baseline/ignore.\n",
        count($errors),
        $result->suppressed,
    )];

    foreach ($errors as $finding) {
        $location = $finding->line > 0 ? "{$finding->file}:{$finding->line}" : $finding->file;
        $lines[] = sprintf("  [%s] %s\n        %s\n", $finding->ruleId, $location, $finding->message);
    }

    $lines[] = "\nBaseline existing drift with: php tools/conformance.php --write-baseline\n";
    $lines[] = "Allowlist a false positive in conformance.baseline.json (\"allow\": with a required \"reason\").\n";

    return implode('', $lines);
}

function renderJson(RunResult $result): string
{
    $payload = [
        'ok' => $result->exitCode() === 0,
        'suppressed' => $result->suppressed,
        'findings' => array_map(static fn (Finding $f): array => $f->toArray(), $result->findings),
    ];

    return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
