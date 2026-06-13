-- Schema snapshot for the payment_links table (SQLite dialect, used by repository tests).
-- Production DDL is applied via database/migrations/ (Phinx). Keep this in sync.
-- A payment link is a hashed, time-limited, revocable URL that lets a payer settle
-- one invoice on a hosted gateway (PAY.JP — ADR 0012/0013). The DB stores only the
-- SHA-256 hash of the raw URL token; card data is never stored (SAQ-A).
-- status: active | paid | revoked (expiry is derived from expires_at, not a stored status).
CREATE TABLE IF NOT EXISTS payment_links (
    id                  INTEGER      NOT NULL PRIMARY KEY AUTOINCREMENT,
    organization_id     INTEGER      NOT NULL,
    invoice_id          INTEGER      NOT NULL,
    token_hash          VARCHAR(64)  NOT NULL,
    gateway             VARCHAR(32)  NOT NULL,
    gateway_session_id  VARCHAR(255),
    status              VARCHAR(16)  NOT NULL,
    expires_at          DATETIME     NOT NULL,
    paid_at             DATETIME,
    revoked_at          DATETIME,
    created_at          DATETIME     NOT NULL,
    updated_at          DATETIME     NOT NULL,
    CONSTRAINT uniq_payment_links_token_hash UNIQUE (token_hash)
);
CREATE INDEX IF NOT EXISTS idx_payment_links_invoice_id ON payment_links (invoice_id);
CREATE INDEX IF NOT EXISTS idx_payment_links_gateway_session_id ON payment_links (gateway_session_id);
CREATE INDEX IF NOT EXISTS idx_payment_links_organization_id ON payment_links (organization_id);
