<?php

declare(strict_types=1);

namespace NeneInvoice\Mcp;

/**
 * Raised when the local MCP server's environment is misconfigured in a way that
 * must fail loudly rather than fall back to a wider posture (ADR 0021) — e.g.
 * requesting a superadmin mint without the admin-exposure opt-in.
 */
final class LocalMcpConfigException extends \RuntimeException
{
}
