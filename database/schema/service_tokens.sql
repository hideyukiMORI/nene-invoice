CREATE TABLE IF NOT EXISTS service_tokens (
    id                INTEGER      NOT NULL PRIMARY KEY AUTOINCREMENT,
    organization_id   INTEGER      NOT NULL,
    jti               VARCHAR(64)  NOT NULL,
    subject           VARCHAR(255) NOT NULL,
    label             VARCHAR(255) NOT NULL,
    scopes            VARCHAR(255) NOT NULL,
    created_by        INTEGER,
    created_at        DATETIME     NOT NULL,
    expires_at        DATETIME     NOT NULL,
    revoked_at        DATETIME,
    CONSTRAINT uniq_service_tokens_jti UNIQUE (jti)
);
CREATE INDEX IF NOT EXISTS idx_service_tokens_organization_id ON service_tokens (organization_id);
