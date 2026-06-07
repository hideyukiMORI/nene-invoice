<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Compliance\RegistrationNumber;
use NeneInvoice\Support\CsvImport;
use NeneInvoice\Support\CsvImportResult;

/**
 * Template-only clients import (ADR 0011). Two passes: validate every row first
 * (no writes) and, only if all rows pass, apply them in a single transaction so
 * the import is all-or-nothing. Row validation reuses the same rules as
 * interactive create/update (registration number, required name) — there is no
 * lenient import path. `id` blank = create, present = update an existing
 * own-organization client.
 *
 * @phpstan-import-type RowError from CsvImportResult
 */
final readonly class ImportClientsCsvUseCase implements ImportClientsCsvUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): ClientRepositoryInterface $clientsFactory
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditFactory
     * @param RequestScopedHolder<int> $orgId
     */
    public function __construct(
        private ClientRepositoryInterface $clients,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $clientsFactory,
        private Closure $auditFactory,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function execute(?int $actorUserId, string $raw, bool $dryRun): CsvImportResult
    {
        $parse = CsvImport::parse($raw, ClientImportTemplate::HEADER);

        if ($parse->formatError !== null) {
            return CsvImportResult::formatRejected($parse->formatError);
        }

        $rowCount = count($parse->rows);
        /** @var list<RowError> $errors */
        $errors = [];
        /** @var list<array{mode: 'create'|'update', existing: ?Client, client: Client}> $ops */
        $ops = [];

        foreach ($parse->rows as $row) {
            $line  = $row['line'];
            $cells = $row['cells'];

            if (!$row['well_formed']) {
                $errors[] = self::error($line, null, 'malformed_row', '列数がヘッダーと一致しません。');
                continue;
            }

            $version = $cells['__template'];
            if ($version !== '' && $version !== ClientImportTemplate::VERSION) {
                $errors[] = self::error($line, '__template', 'invalid_template_version', sprintf('テンプレートのバージョンが一致しません（期待: %s）。', ClientImportTemplate::VERSION));
                continue;
            }

            /** @var list<RowError> $rowErrors */
            $rowErrors = [];

            $name = $cells['取引先名'];
            if ($name === '') {
                $rowErrors[] = self::error($line, '取引先名', 'required', '取引先名は必須です。');
            }

            $registrationNumber = $cells['登録番号'] === '' ? null : $cells['登録番号'];
            if ($registrationNumber !== null && !RegistrationNumber::isValid($registrationNumber)) {
                $rowErrors[] = self::error($line, '登録番号', 'invalid_registration_number', '登録番号は T+13桁である必要があります。');
            }

            $mode     = 'create';
            $existing = null;
            $idCell   = $cells['id'];
            if ($idCell !== '') {
                if (!ctype_digit($idCell)) {
                    $rowErrors[] = self::error($line, 'id', 'invalid_id', 'id は数値で指定してください。');
                } else {
                    $existing = $this->clients->findById((int) $idCell);
                    if ($existing === null) {
                        $rowErrors[] = self::error($line, 'id', 'client_not_found', sprintf('id %d の取引先が見つかりません。', (int) $idCell));
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
                'client'   => new Client(
                    organizationId: $this->orgId->get(),
                    name: $name,
                    nameKana: self::optional($cells['カナ']),
                    contactName: self::optional($cells['担当者']),
                    email: self::optional($cells['メール']),
                    billingAddress: self::optional($cells['請求先住所']),
                    registrationNumber: $registrationNumber,
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
     * @param list<array{mode: 'create'|'update', existing: ?Client, client: Client}> $ops
     */
    private function apply(?int $actorUserId, array $ops): void
    {
        $organizationId = $this->orgId->get();

        $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $ops): void {
            $clients = ($this->clientsFactory)($exec);
            $audit   = ($this->auditFactory)($exec);

            foreach ($ops as $op) {
                if ($op['mode'] === 'update' && $op['existing'] !== null) {
                    $clients->update($op['client']);
                    $after = $clients->findById((int) $op['existing']->id);
                    $audit->record($actorUserId, $organizationId, 'client.updated', 'client', $op['existing']->id, ClientResponse::toArray($op['existing']), $after !== null ? ClientResponse::toArray($after) : null);
                    continue;
                }

                $id    = $clients->save($op['client']);
                $after = $clients->findById($id);
                $audit->record($actorUserId, $organizationId, 'client.created', 'client', $id, null, $after !== null ? ClientResponse::toArray($after) : null);
            }
        });
    }

    private static function optional(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * @return RowError
     */
    private static function error(int $row, ?string $column, string $code, string $message): array
    {
        return ['row' => $row, 'column' => $column, 'code' => $code, 'message' => $message];
    }
}
