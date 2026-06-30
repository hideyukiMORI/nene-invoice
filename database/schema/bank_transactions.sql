-- Schema snapshot for the bank_transactions table (SQLite dialect, used by repository tests).
-- Production DDL is applied via database/migrations/ (Phinx). Keep this in sync.
-- A bank_transaction is one line imported from a bank deposit CSV (#505) — the staging
-- area for auto-reconciliation (自動消込). Amounts are integer cents (ADR 0004); value_date
-- is a JST calendar date. Importing only stages rows; matching and posting a payment are
-- separate, compliance-reviewed concerns (accounting-compliance.md) — staging records nothing.
-- direction: credit | debit. status: unmatched | matched | posted | ignored.
CREATE TABLE IF NOT EXISTS bank_transactions (
    id                 INTEGER      NOT NULL PRIMARY KEY AUTOINCREMENT,
    organization_id    INTEGER      NOT NULL,
    value_date         DATE         NOT NULL,
    direction          VARCHAR(8)   NOT NULL,
    amount_cents       INTEGER      NOT NULL,
    payer_name         VARCHAR(255),
    description        VARCHAR(512),
    bank_reference     VARCHAR(255),
    status             VARCHAR(16)  NOT NULL DEFAULT 'unmatched',
    matched_invoice_id INTEGER,
    matched_payment_id INTEGER,
    imported_at        DATETIME     NOT NULL,
    created_at         DATETIME     NOT NULL,
    updated_at         DATETIME     NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_bank_transactions_organization_id ON bank_transactions (organization_id);
CREATE INDEX IF NOT EXISTS idx_bank_transactions_status ON bank_transactions (organization_id, status);
CREATE INDEX IF NOT EXISTS idx_bank_transactions_bank_reference ON bank_transactions (organization_id, bank_reference);
