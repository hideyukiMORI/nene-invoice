-- Schema snapshot for the audit_logs table (SQLite dialect, used by repository tests).
-- Production DDL is applied via database/migrations/ (Phinx). Keep this in sync.
CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_user_id INTEGER NULL DEFAULT NULL,
    organization_id INTEGER NULL DEFAULT NULL,
    action VARCHAR(64) NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    entity_id INTEGER NULL DEFAULT NULL,
    before_json TEXT NULL DEFAULT NULL,
    after_json TEXT NULL DEFAULT NULL,
    created_at DATETIME NOT NULL
);
CREATE INDEX idx_audit_logs_organization_id ON audit_logs (organization_id);
CREATE INDEX idx_audit_logs_entity ON audit_logs (entity_type, entity_id);
