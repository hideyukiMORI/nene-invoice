<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Support\CsvImport;
use NeneInvoice\Support\CsvImportResult;

/**
 * Template-only items import (ADR 0011). Two passes: validate every row first (no
 * writes) and, only if all rows pass, apply them in a single transaction
 * (all-or-nothing). Row validation reuses the same rules as interactive
 * create/update — description required, unit price a non-negative whole-yen
 * integer (cents 1:1 for JPY), tax rate a percent restricted to the supported
 * Japanese rates. `id` blank = create, present = update an existing own-org item.
 *
 * @phpstan-import-type RowError from CsvImportResult
 */
final readonly class ImportItemsCsvUseCase implements ImportItemsCsvUseCaseInterface
{
    /** Percent (sheet) → basis points (stored). Keys mirror {@see ItemField::ALLOWED_TAX_RATES}. */
    private const PERCENT_TO_BPS = ['10' => 1000, '8' => 800];

    /**
     * @param Closure(DatabaseQueryExecutorInterface): ItemRepositoryInterface $itemsFactory
     * @param RequestScopedHolder<int> $orgId
     */
    public function __construct(
        private ItemRepositoryInterface $items,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $itemsFactory,
        private AuditRecorderFactoryInterface $auditFactory,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function execute(?int $actorUserId, string $raw, bool $dryRun): CsvImportResult
    {
        $parse = CsvImport::parse($raw, ItemImportTemplate::HEADER);

        if ($parse->formatError !== null) {
            return CsvImportResult::formatRejected($parse->formatError);
        }

        $rowCount = count($parse->rows);
        /** @var list<RowError> $errors */
        $errors = [];
        /** @var list<array{mode: 'create'|'update', existing: ?Item, item: Item}> $ops */
        $ops = [];

        foreach ($parse->rows as $row) {
            $line  = $row['line'];
            $cells = $row['cells'];

            if (!$row['well_formed']) {
                $errors[] = self::error($line, null, 'malformed_row', '列数がヘッダーと一致しません。');
                continue;
            }

            $version = $cells['__template'];
            if ($version !== '' && $version !== ItemImportTemplate::VERSION) {
                $errors[] = self::error($line, '__template', 'invalid_template_version', sprintf('テンプレートのバージョンが一致しません（期待: %s）。', ItemImportTemplate::VERSION));
                continue;
            }

            /** @var list<RowError> $rowErrors */
            $rowErrors = [];

            $description = $cells['品目名'];
            if ($description === '') {
                $rowErrors[] = self::error($line, '品目名', 'required', '品目名は必須です。');
            }

            $priceCell = $cells['標準単価'];
            $price     = 0;
            if ($priceCell === '') {
                $rowErrors[] = self::error($line, '標準単価', 'required', '標準単価は必須です。');
            } elseif (!ctype_digit($priceCell)) {
                $rowErrors[] = self::error($line, '標準単価', 'invalid_price', '標準単価は 0 以上の整数（円）で指定してください。');
            } else {
                $price = (int) $priceCell;
            }

            $taxCell = $cells['標準税率'];
            $taxKey  = str_ends_with($taxCell, '%') ? trim(substr($taxCell, 0, -1)) : $taxCell;
            $taxBps  = 1000;
            if ($taxCell === '') {
                $rowErrors[] = self::error($line, '標準税率', 'required', '標準税率は必須です。');
            } elseif (!isset(self::PERCENT_TO_BPS[$taxKey])) {
                $rowErrors[] = self::error($line, '標準税率', 'invalid_tax_rate', '標準税率は 10 または 8 で指定してください。');
            } else {
                $taxBps = self::PERCENT_TO_BPS[$taxKey];
            }

            $mode     = 'create';
            $existing = null;
            $idCell   = $cells['id'];
            if ($idCell !== '') {
                if (!ctype_digit($idCell)) {
                    $rowErrors[] = self::error($line, 'id', 'invalid_id', 'id は数値で指定してください。');
                } else {
                    $existing = $this->items->findById((int) $idCell);
                    if ($existing === null) {
                        $rowErrors[] = self::error($line, 'id', 'item_not_found', sprintf('id %d の品目が見つかりません。', (int) $idCell));
                    } else {
                        $mode = 'update';
                    }
                }
            }

            if ($rowErrors !== []) {
                array_push($errors, ...$rowErrors);
                continue;
            }

            $ops[] = [
                'mode'     => $mode,
                'existing' => $existing,
                'item'     => new Item(
                    organizationId: $this->orgId->get(),
                    description: $description,
                    defaultUnitPriceCents: $price,
                    defaultTaxRateBps: $taxBps,
                    id: $existing?->id,
                    createdAt: $existing?->createdAt,
                    updatedAt: $existing?->updatedAt,
                ),
            ];
        }

        if ($errors !== []) {
            return CsvImportResult::rowsRejected($rowCount, $errors);
        }

        $created = count(array_filter($ops, static fn (array $op): bool => $op['mode'] === 'create'));
        $updated = count($ops) - $created;

        if ($dryRun) {
            return CsvImportResult::applied($rowCount, $created, $updated, true);
        }

        $this->apply($actorUserId, $ops);

        return CsvImportResult::applied($rowCount, $created, $updated, false);
    }

    /**
     * @param list<array{mode: 'create'|'update', existing: ?Item, item: Item}> $ops
     */
    private function apply(?int $actorUserId, array $ops): void
    {
        $organizationId = $this->orgId->get();

        $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $ops): void {
            $items = ($this->itemsFactory)($exec);
            $audit = $this->auditFactory->forExecutor($exec);

            foreach ($ops as $op) {
                if ($op['mode'] === 'update' && $op['existing'] !== null) {
                    $items->update($op['item']);
                    $after = $items->findById((int) $op['existing']->id);
                    $audit->record(new AuditEvent(
                        action: 'item.updated',
                        entityType: 'item',
                        entityId: $op['existing']->id,
                        actorId: $actorUserId,
                        organizationId: $organizationId,
                        before: ItemResponse::toArray($op['existing']),
                        after: $after !== null ? ItemResponse::toArray($after) : null,
                    ));
                    continue;
                }

                $id    = $items->save($op['item']);
                $after = $items->findById($id);
                $audit->record(new AuditEvent(
                    action: 'item.created',
                    entityType: 'item',
                    entityId: $id,
                    actorId: $actorUserId,
                    organizationId: $organizationId,
                    before: null,
                    after: $after !== null ? ItemResponse::toArray($after) : null,
                ));
            }
        });
    }

    /**
     * @return RowError
     */
    private static function error(int $row, ?string $column, string $code, string $message): array
    {
        return ['row' => $row, 'column' => $column, 'code' => $code, 'message' => $message];
    }
}
