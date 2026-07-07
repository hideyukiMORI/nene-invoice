<?php

declare(strict_types=1);

namespace NeneInvoice\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Detects the URL base path the application is installed under, so one artifact
 * runs at the document root, a subdomain root, or a subdirectory without a
 * rebuild (ADR 0015).
 *
 * The base is discovered at request time from `SCRIPT_NAME` (the URL to the
 * front controller — Apache/CGI always sets it), with an explicit
 * `APP_BASE_PATH` override for reverse-proxy / atypical-rewrite edge cases.
 *
 * Normalized form: the document root is the empty string `''` (so paths
 * concatenate cleanly), any subdirectory is `/segment` with a leading slash and
 * no trailing slash (e.g. `/invoice`, `/NeNeSuite/invoice`).
 */
final class BasePath
{
    /** Request attribute under which the front controller stores the detected base. */
    public const REQUEST_ATTRIBUTE = 'app.base_path';

    /** API path prefixes that must reach the router, never the SPA shell. */
    private const API_PREFIXES = ['/auth', '/admin', '/api', '/health', '/machine', '/examples', '/demo'];

    /**
     * @param array<string, mixed> $serverParams PSR-7 server params (or `$_SERVER`)
     */
    public static function detect(array $serverParams, ?string $override = null): string
    {
        if ($override !== null && $override !== '') {
            return self::normalize($override);
        }

        $script = $serverParams['SCRIPT_NAME'] ?? '';

        if (!is_string($script)) {
            return '';
        }

        $script = str_replace('\\', '/', $script);

        // Trust SCRIPT_NAME only when it points at the front controller, as
        // Apache/nginx set it after rewriting to index.php (e.g.
        // `/invoice/index.php`). The php built-in server in router-script mode
        // sets SCRIPT_NAME to the *request path* instead, which would mis-detect
        // (e.g. `/admin/me` → `/admin`); fall back to root there — dev runs at
        // root, and APP_BASE_PATH overrides if needed.
        if (!str_ends_with($script, '/index.php')) {
            return '';
        }

        return self::normalize(dirname($script));
    }

    /** Reads the base the front controller stored on the request (`''` if absent). */
    public static function fromRequest(ServerRequestInterface $request): string
    {
        $base = $request->getAttribute(self::REQUEST_ATTRIBUTE);

        return is_string($base) ? $base : '';
    }

    /** `''` for the document root; otherwise `/segment` (leading slash, no trailing). */
    public static function normalize(string $path): string
    {
        $trimmed = '/' . trim($path, '/');

        return $trimmed === '/' ? '' : $trimmed;
    }

    /**
     * Removes the base prefix from a request path, returning the base-relative
     * path (always leading-slash) that the router and middleware expect.
     */
    public static function strip(string $path, string $base): string
    {
        if ($base === '') {
            return $path === '' ? '/' : $path;
        }

        if ($path === $base) {
            return '/';
        }

        if (str_starts_with($path, $base . '/')) {
            return substr($path, strlen($base));
        }

        // Not under the detected base (unexpected) — leave untouched.
        return $path;
    }

    /**
     * True when the base-relative path targets the JSON API (router-handled),
     * as opposed to a human SPA route that should receive the app shell.
     */
    public static function isApiPath(string $strippedPath): bool
    {
        foreach (self::API_PREFIXES as $prefix) {
            if ($strippedPath === $prefix || str_starts_with($strippedPath, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
