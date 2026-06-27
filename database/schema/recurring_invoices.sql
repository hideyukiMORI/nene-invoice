-- Schema snapshot for the recurring_invoices table (SQLite dialect, used by repository tests).
-- Production DDL is applied via database/migrations/ (Phinx). Keep this in sync.
-- A recurring_invoice is a billing schedule (#503) that generates an invoice every
-- period (顧問料・保守費・管理料・月謝). The line template lives in line_items; totals are
-- integer cents (ADR 0004). next_run_on / last_run_on are calendar dates (like valid_until).
-- frequency: monthly | quarterly. Generation/issue are separate, compliance-reviewed concerns.
CREATE TABLE IF NOT EXISTS recurring_invoices (
    id              INTEGER      NOT NULL PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER      NOT NULL,
    client_id       INTEGER      NOT NULL,
    name            VARCHAR(255) NOT NULL,
    frequency       VARCHAR(16)  NOT NULL,
    subtotal_cents  INTEGER      NOT NULL DEFAULT 0,
    tax_cents       INTEGER      NOT NULL DEFAULT 0,
    total_cents     INTEGER      NOT NULL DEFAULT 0,
    next_run_on     DATE         NOT NULL,
    last_run_on     DATE,
    is_active       BOOLEAN      NOT NULL DEFAULT 1,
    notes           TEXT,
    is_deleted      BOOLEAN      NOT NULL DEFAULT 0,
    deleted_at      DATETIME,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_recurring_invoices_organization_id ON recurring_invoices (organization_id);
CREATE INDEX IF NOT EXISTS idx_recurring_invoices_due ON recurring_invoices (organization_id, is_active, next_run_on);
