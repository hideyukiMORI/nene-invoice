-- Schema snapshot for the payer_aliases table (SQLite dialect, used by repository tests).
-- Production DDL is applied via database/migrations/ (Phinx). Keep this in sync.
-- A payer_alias maps a normalized bank remitter name (振込依頼人名) to a client (#505),
-- so auto-reconciliation can match future deposits from the same payer. normalized_name
-- is produced by PayerNameNormalizer; unique per organization. Learned when an operator
-- confirms a match (a later increment) — this is matching metadata, not a billing record.
CREATE TABLE IF NOT EXISTS payer_aliases (
    id              INTEGER      NOT NULL PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER      NOT NULL,
    normalized_name VARCHAR(255) NOT NULL,
    client_id       INTEGER      NOT NULL,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_payer_aliases_org_name ON payer_aliases (organization_id, normalized_name);
CREATE INDEX IF NOT EXISTS idx_payer_aliases_organization_id ON payer_aliases (organization_id);
