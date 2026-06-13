-- Schema snapshot for the company_settings table (SQLite dialect, used by repository tests).
-- Production DDL is applied via database/migrations/ (Phinx). Keep this in sync.
CREATE TABLE IF NOT EXISTS company_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    legal_name VARCHAR(255) NOT NULL,
    address TEXT NULL DEFAULT NULL,
    phone VARCHAR(32) NULL DEFAULT NULL,
    email VARCHAR(255) NULL DEFAULT NULL,
    registration_number VARCHAR(14) NULL DEFAULT NULL,
    bank_name VARCHAR(255) NULL DEFAULT NULL,
    bank_branch VARCHAR(255) NULL DEFAULT NULL,
    account_type VARCHAR(32) NULL DEFAULT NULL,
    account_number VARCHAR(64) NULL DEFAULT NULL,
    logo_url VARCHAR(1024) NULL DEFAULT NULL,
    default_quote_validity_days INTEGER NULL DEFAULT NULL,
    default_payment_closing_day INTEGER NULL DEFAULT NULL,
    default_payment_month_offset INTEGER NULL DEFAULT NULL,
    default_payment_pay_day INTEGER NULL DEFAULT NULL,
    pdf_template VARCHAR(16) NOT NULL DEFAULT 'standard',
    pdf_spacing VARCHAR(16) NOT NULL DEFAULT 'medium',
    pdf_heading_font VARCHAR(16) NOT NULL DEFAULT 'gothic',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
CREATE UNIQUE INDEX uniq_company_settings_organization_id ON company_settings (organization_id);
