-- Schema snapshot for the company_seal_images table (SQLite dialect, used by repository tests).
-- Production DDL is applied via database/migrations/ (Phinx). Keep this in sync.
-- One PNG seal (社印) per organization, stored base64-encoded and tenant-scoped (Issue #448).
CREATE TABLE IF NOT EXISTS company_seal_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    image_base64 TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
CREATE UNIQUE INDEX uniq_company_seal_images_organization_id ON company_seal_images (organization_id);
