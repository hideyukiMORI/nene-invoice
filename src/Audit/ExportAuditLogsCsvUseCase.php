<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

use NeneInvoice\Support\Jst;

/**
 * Assembles CSV bytes for the audit trail (filtered, newest first). UTF-8 BOM is
 * prepended so Excel opens the file without encoding issues. before/after are
 * emitted as JSON so the full snapshot is preserved for compliance.
 */
final readonly class ExportAuditLogsCsvUseCase implements ExportAuditLogsCsvUseCaseInterface
{
    /** Safety cap on exported rows (a single CSV should stay bounded). */
    private const MAX_ROWS = 10000;

    public function __construct(
        private AuditLogRepositoryInterface $logs,
    ) {
    }

    public function execute(AuditLogFilter $filter): string
    {
        $rows = $this->logs->findAll($filter, self::MAX_ROWS, 0);

        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);

        // UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            '日時',
            'アクション',
            '対象種別',
            '対象ID',
            '操作者ID',
            '操作者メール',
            '変更前',
            '変更後',
        ]);

        foreach ($rows as $log) {
            fputcsv($handle, [
                $log->createdAt !== null ? Jst::dateTime($log->createdAt) : '',
                $log->action,
                $log->entityType,
                $log->entityId,
                $log->actorUserId,
                $log->actorEmail,
                self::encode($log->before),
                self::encode($log->after),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /** @param array<string, mixed>|null $value */
    private static function encode(?array $value): string
    {
        if ($value === null) {
            return '';
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '' : $json;
    }
}
