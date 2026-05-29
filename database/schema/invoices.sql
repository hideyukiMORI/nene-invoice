-- Schema snapshot for the invoices table (SQLite dialect, used by repository tests).
-- Production DDL is applied via database/migrations/ (Phinx). Keep this in sync.
-- invoice_number is NULL for drafts; the UNIQUE index permits multiple NULLs.
CREATE TABLE IF NOT EXISTS invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    client_id INTEGER NOT NULL,
    quote_id INTEGER NULL DEFAULT NULL,
    invoice_number VARCHAR(32) NULL DEFAULT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'draft',
    is_qualified_invoice BOOLEAN NOT NULL DEFAULT 0,
    issued_at DATETIME NULL DEFAULT NULL,
    due_at DATETIME NULL DEFAULT NULL,
    subtotal_cents INTEGER NOT NULL DEFAULT 0,
    tax_cents INTEGER NOT NULL DEFAULT 0,
    total_cents INTEGER NOT NULL DEFAULT 0,
    notes TEXT NULL DEFAULT NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
CREATE INDEX idx_invoices_organization_id ON invoices (organization_id);
CREATE UNIQUE INDEX uniq_invoices_org_number ON invoices (organization_id, invoice_number);
