-- Schema snapshot for the line_items table (SQLite dialect, used by repository tests).
-- Production DDL is applied via database/migrations/ (Phinx). Keep this in sync.
CREATE TABLE IF NOT EXISTS line_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_type VARCHAR(16) NOT NULL,
    parent_id INTEGER NOT NULL,
    description VARCHAR(1024) NOT NULL,
    quantity INTEGER NOT NULL,
    unit_price_cents INTEGER NOT NULL,
    tax_rate_bps INTEGER NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
CREATE INDEX idx_line_items_parent ON line_items (parent_type, parent_id);
