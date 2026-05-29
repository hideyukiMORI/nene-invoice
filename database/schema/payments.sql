-- Schema snapshot for the payments table (SQLite dialect, used by repository tests).
-- Production DDL is applied via database/migrations/ (Phinx). Keep this in sync.
-- A payment records an amount received against an issued invoice (integer cents).
CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    invoice_id INTEGER NOT NULL,
    amount_cents INTEGER NOT NULL,
    paid_at DATETIME NOT NULL,
    method VARCHAR(32) NULL DEFAULT NULL,
    note TEXT NULL DEFAULT NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
CREATE INDEX idx_payments_organization_id ON payments (organization_id);
CREATE INDEX idx_payments_invoice_id ON payments (invoice_id);
