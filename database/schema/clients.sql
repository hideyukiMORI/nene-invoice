-- Schema snapshot for the clients table (SQLite dialect, used by repository tests).
-- Production DDL is applied via database/migrations/ (Phinx). Keep this in sync.
CREATE TABLE IF NOT EXISTS clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255) NULL DEFAULT NULL,
    email VARCHAR(255) NULL DEFAULT NULL,
    billing_address TEXT NULL DEFAULT NULL,
    registration_number VARCHAR(14) NULL DEFAULT NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
CREATE INDEX idx_clients_organization_id ON clients (organization_id);
