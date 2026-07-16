<?php

declare(strict_types=1);

namespace NeneInvoice;

/**
 * The application's own release version, intended for the auth-gated
 * `GET /machine/health` `version` field so NeNe Suite can compare the installed
 * version against Origin's latest and flag updates (#496). This is the
 * *application* version and is intentionally distinct from the NENE2 *framework*
 * version (reported separately).
 *
 * Single source of truth: the repo-root `VERSION` file (fleet convention —
 * records #586). `frontend/package.json` is pinned to `0.0.0`, and
 * `tools/build-release.sh` reads the same `VERSION` for the release ZIP name, so
 * the version is bumped in exactly one place per release. A missing or blank
 * file yields null, and the caller omits `version` rather than reporting a
 * fabricated value.
 */
final class Version
{
    /**
     * The version string from the VERSION file, or null when it is absent or
     * blank. Pass an explicit $path only in tests; production uses the root file.
     */
    public static function current(?string $path = null): ?string
    {
        $path ??= __DIR__ . '/../VERSION';

        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        $version = trim($raw);

        return $version === '' ? null : $version;
    }
}
