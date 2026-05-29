-- Schema snapshot for the organizations table (SQLite dialect, used by repository tests).
-- Production DDL is applied via database/migrations/ (Phinx). Keep this in sync.
CREATE TABLE IF NOT EXISTS organizations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    external_id VARCHAR(255) NULL DEFAULT NULL,
    custom_domain VARCHAR(255) NULL DEFAULT NULL,
    plan VARCHAR(32) NOT NULL DEFAULT 'free',
    is_active BOOLEAN NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
CREATE UNIQUE INDEX uniq_organizations_slug ON organizations (slug);
CREATE UNIQUE INDEX uniq_organizations_external_id ON organizations (external_id);
