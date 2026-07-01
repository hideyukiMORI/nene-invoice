<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Parses and validates bank-reconciliation request input (#505). Invalid input
 * surfaces as {@see BankTransactionValidationException} (422).
 */
final class BankTransactionRequest
{
    /** Built-in column-mapping presets selectable via `?preset=`. */
    public const PRESET_NET_BANK = 'net_bank_credit_debit';
    public const PRESET_SIGNED   = 'signed_amount';

    /** The `{id}` path parameter as an int, or 0 when absent. */
    public static function pathId(ServerRequestInterface $request): int
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);

        return is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @throws BankTransactionValidationException
     */
    public static function requireInvoiceId(array $body): int
    {
        $value = $body['invoice_id'] ?? null;

        if (!is_int($value) || $value <= 0) {
            throw new BankTransactionValidationException('invoice_id is required and must be a positive integer.');
        }

        return $value;
    }

    /**
     * The `?status=` list filter, or null when absent.
     *
     * @throws BankTransactionValidationException
     */
    public static function statusFilter(ServerRequestInterface $request): ?BankTransactionStatus
    {
        $params = $request->getQueryParams();
        $raw    = $params['status'] ?? null;

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $status = BankTransactionStatus::tryFrom($raw);

        if ($status === null) {
            throw new BankTransactionValidationException(sprintf('Unknown bank transaction status "%s".', $raw));
        }

        return $status;
    }

    /**
     * Resolves the column mapping for an import from `?preset=` (defaults to the
     * net-bank credit/debit shape).
     *
     * @throws BankTransactionValidationException
     */
    public static function mappingFromPreset(ServerRequestInterface $request): BankCsvColumnMapping
    {
        $params = $request->getQueryParams();
        $preset = $params['preset'] ?? self::PRESET_NET_BANK;

        return match ($preset) {
            self::PRESET_NET_BANK => BankImportPresets::netBankCreditDebit(),
            self::PRESET_SIGNED   => BankImportPresets::signedAmount(),
            default               => throw new BankTransactionValidationException(sprintf('Unknown import preset "%s".', is_string($preset) ? $preset : '')),
        };
    }
}
