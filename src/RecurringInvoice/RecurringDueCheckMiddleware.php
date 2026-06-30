<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Tier A (shared hosting, no cron) execution route for recurring billing (#526):
 * after serving an authenticated `/admin/` request, generate any due recurring
 * **drafts** for the request's organization, at most once per org per day.
 *
 * Best-effort: the generation runs after the real response is produced, only on
 * `/admin/` paths, and any failure is swallowed so it can never break a user
 * request. Tier B installs run `tools/run-recurring.php` from cron instead and
 * can drop this middleware with `RECURRING_INLINE=0`.
 *
 * Only drafts are generated — issuing/numbering is a separate, human,
 * tax-reviewed step (docs/explanation/accounting-compliance.md §5).
 */
final readonly class RecurringDueCheckMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RecurringDueRunnerInterface $runner,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (str_starts_with($request->getUri()->getPath(), '/admin/')) {
            try {
                $this->runner->runForCurrentOrg();
            } catch (Throwable $e) {
                error_log('NeNe Invoice: inline recurring run failed: ' . $e->getMessage());
            }
        }

        return $response;
    }
}
