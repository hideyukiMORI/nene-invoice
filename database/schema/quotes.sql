-- Schema snapshot for the quotes table (SQLite dialect, used by repository tests).
-- Production DDL is applied via database/migrations/ (Phinx). Keep this in sync.
CREATE TABLE IF NOT EXISTS quotes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    client_id INTEGER NOT NULL,
    quote_number VARCHAR(32) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'draft',
    issued_at DATETIME NULL DEFAULT NULL,
    valid_until DATETIME NULL DEFAULT NULL,
    subtotal_cents INTEGER NOT NULL DEFAULT 0,
    tax_cents INTEGER NOT NULL DEFAULT 0,
    total_cents INTEGER NOT NULL DEFAULT 0,
    notes TEXT NULL DEFAULT NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
CREATE INDEX idx_quotes_organization_id ON quotes (organization_id);
CREATE UNIQUE INDEX uniq_quotes_org_number ON quotes (organization_id, quote_number);
