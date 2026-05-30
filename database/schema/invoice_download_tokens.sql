CREATE TABLE IF NOT EXISTS invoice_download_tokens (
    id                INTEGER      NOT NULL PRIMARY KEY AUTOINCREMENT,
    invoice_id        INTEGER      NOT NULL,
    organization_id   INTEGER      NOT NULL,
    token_hash        VARCHAR(64)  NOT NULL,
    expires_at        DATETIME     NOT NULL,
    created_at        DATETIME     NOT NULL,
    CONSTRAINT uniq_download_tokens_hash UNIQUE (token_hash)
);
CREATE INDEX IF NOT EXISTS idx_download_tokens_invoice_id ON invoice_download_tokens (invoice_id);
