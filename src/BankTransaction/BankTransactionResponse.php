<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * Serializes bank-reconciliation entities to snake_case JSON (#505).
 */
final class BankTransactionResponse
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(BankTransaction $transaction): array
    {
        return [
            'id'                 => $transaction->id,
            'value_date'         => $transaction->valueDate,
            'direction'          => $transaction->direction->value,
            'amount_cents'       => $transaction->amountCents,
            'payer_name'         => $transaction->payerName,
            'description'        => $transaction->description,
            'bank_reference'     => $transaction->bankReference,
            'status'             => $transaction->status->value,
            'matched_invoice_id' => $transaction->matchedInvoiceId,
            'matched_payment_id' => $transaction->matchedPaymentId,
            'imported_at'        => $transaction->importedAt,
            'created_at'         => $transaction->createdAt,
            'updated_at'         => $transaction->updatedAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function suggestionToArray(SuggestedMatch $suggestion): array
    {
        return [
            'invoice_id'       => $suggestion->invoiceId,
            'invoice_number'   => $suggestion->invoiceNumber,
            'client_id'        => $suggestion->clientId,
            'client_name'      => $suggestion->clientName,
            'outstanding_cents' => $suggestion->outstandingCents,
            'score'            => $suggestion->score,
            'reasons'          => $suggestion->reasons,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function importResultToArray(ImportBankTransactionsResult $result): array
    {
        return [
            'imported_count'          => $result->importedCount,
            'skipped_duplicate_count' => $result->skippedDuplicateCount,
            'row_errors'              => $result->rowErrors,
            'format_error'            => $result->formatError,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function confirmResultToArray(ConfirmMatchResult $result): array
    {
        return [
            'transaction' => self::toArray($result->transaction),
            'payment'     => [
                'id'               => $result->payment->payment->id,
                'invoice_id'       => $result->payment->payment->invoiceId,
                'amount_cents'     => $result->payment->payment->amountCents,
                'invoice_status'   => $result->payment->invoice->status->value,
                'total_paid_cents' => $result->payment->totalPaidCents,
            ],
        ];
    }
}
