<?php

declare(strict_types=1);

/*
 * Process-wide timezone. The application stores all instant timestamps
 * (created_at, updated_at, issued_at, paid_at, audit/token/login times) in
 * **UTC** and converts to JST only at display edges (PDF, CSV, frontend).
 * Forcing the process timezone to UTC makes every ambient date()/DateTimeImmutable
 * produce UTC consistently across web, CLI, and test contexts.
 *
 * Calendar-date fields (due_at, valid_until) are computed in JST — see
 * docs/adr/0010-utc-storage-jst-display.md and NeneInvoice\Support\Jst.
 */
date_default_timezone_set('UTC');
