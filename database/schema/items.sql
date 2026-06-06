-- Schema snapshot for the items table (SQLite dialect, used by repository tests).
-- Production DDL is applied via database/migrations/ (Phinx). Keep this in sync.
-- Item master (品目): reusable description + default unit price / tax rate, org-scoped.
CREATE TABLE IF NOT EXISTS items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    description VARCHAR(255) NOT NULL,
    default_unit_price_cents INTEGER NOT NULL DEFAULT 0,
    default_tax_rate_bps INTEGER NOT NULL DEFAULT 1000,
    is_deleted BOOLEAN NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
CREATE INDEX idx_items_organization_id ON items (organization_id);
