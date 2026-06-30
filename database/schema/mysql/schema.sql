-- NeNe Invoice — MySQL schema (generated from Phinx migrations)
-- Used by the Tier A web installer (public_html/install.php).
-- Must be kept in sync with database/migrations/ after any schema change.
-- Encoding: utf8mb4

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

CREATE TABLE IF NOT EXISTS `organizations` (
    `id`            INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(255) NOT NULL,
    `slug`          VARCHAR(100) NOT NULL,
    `external_id`   VARCHAR(255) DEFAULT NULL,
    `custom_domain` VARCHAR(255) DEFAULT NULL,
    `plan`          VARCHAR(32)  NOT NULL DEFAULT 'free',
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    DATETIME     NOT NULL,
    `updated_at`    DATETIME     NOT NULL,
    UNIQUE KEY `uniq_organizations_slug` (`slug`),
    UNIQUE KEY `uniq_organizations_external_id` (`external_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
    `id`              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `email`           VARCHAR(255) NOT NULL,
    `password_hash`   VARCHAR(255) NOT NULL,
    `role`            VARCHAR(32)  NOT NULL DEFAULT 'admin',
    `organization_id` INT          DEFAULT NULL,
    `status`          VARCHAR(16)  NOT NULL DEFAULT 'active',
    `created_at`      DATETIME     NOT NULL,
    `updated_at`      DATETIME     NOT NULL,
    UNIQUE KEY `uniq_users_email` (`email`),
    KEY `idx_users_organization_id` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `clients` (
    `id`                  INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `organization_id`     INT          NOT NULL,
    `name`                VARCHAR(255) NOT NULL,
    `name_kana`           VARCHAR(255) DEFAULT NULL,
    `contact_name`        VARCHAR(255) DEFAULT NULL,
    `email`               VARCHAR(255) DEFAULT NULL,
    `billing_address`     TEXT         DEFAULT NULL,
    `registration_number` VARCHAR(14)  DEFAULT NULL,
    `is_deleted`          TINYINT(1)   NOT NULL DEFAULT 0,
    `deleted_at`          DATETIME     DEFAULT NULL,
    `created_at`          DATETIME     NOT NULL,
    `updated_at`          DATETIME     NOT NULL,
    KEY `idx_clients_organization_id` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `company_settings` (
    `id`                  INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `organization_id`     INT           NOT NULL,
    `legal_name`          VARCHAR(255)  NOT NULL,
    `address`             TEXT          DEFAULT NULL,
    `phone`               VARCHAR(32)   DEFAULT NULL,
    `email`               VARCHAR(255)  DEFAULT NULL,
    `registration_number` VARCHAR(14)   DEFAULT NULL,
    `bank_name`           VARCHAR(255)  DEFAULT NULL,
    `bank_branch`         VARCHAR(255)  DEFAULT NULL,
    `account_type`        VARCHAR(32)   DEFAULT NULL,
    `account_number`      VARCHAR(64)   DEFAULT NULL,
    `logo_url`            VARCHAR(1024) DEFAULT NULL,
    `default_quote_validity_days`  INT  DEFAULT NULL,
    `default_payment_closing_day`  INT  DEFAULT NULL,
    `default_payment_month_offset` INT  DEFAULT NULL,
    `default_payment_pay_day`      INT  DEFAULT NULL,
    `pdf_template`        VARCHAR(16)   NOT NULL DEFAULT 'standard',
    `pdf_spacing`         VARCHAR(16)   NOT NULL DEFAULT 'medium',
    `pdf_heading_font`    VARCHAR(16)   NOT NULL DEFAULT 'gothic',
    `created_at`          DATETIME      NOT NULL,
    `updated_at`          DATETIME      NOT NULL,
    UNIQUE KEY `uniq_company_settings_organization_id` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `company_seal_images` (
    `id`              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `organization_id` INT          NOT NULL,
    `image_base64`    MEDIUMTEXT   NOT NULL,
    `created_at`      DATETIME     NOT NULL,
    `updated_at`      DATETIME     NOT NULL,
    UNIQUE KEY `uniq_company_seal_images_organization_id` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`           INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `actor_user_id` INT        DEFAULT NULL,
    `organization_id` INT      DEFAULT NULL,
    `action`       VARCHAR(64) NOT NULL,
    `entity_type`  VARCHAR(64) NOT NULL,
    `entity_id`    INT         DEFAULT NULL,
    `before_json`  TEXT        DEFAULT NULL,
    `after_json`   TEXT        DEFAULT NULL,
    `created_at`   DATETIME    NOT NULL,
    KEY `idx_audit_logs_organization_id` (`organization_id`),
    KEY `idx_audit_logs_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `document_sequences` (
    `id`              INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `organization_id` INT         NOT NULL,
    `doc_type`        VARCHAR(32) NOT NULL,
    `year`            INT         NOT NULL,
    `last_number`     INT         NOT NULL DEFAULT 0,
    UNIQUE KEY `uniq_document_sequences_scope` (`organization_id`, `doc_type`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `line_items` (
    `id`               INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `parent_type`      VARCHAR(16)   NOT NULL,
    `parent_id`        INT           NOT NULL,
    `description`      VARCHAR(1024) NOT NULL,
    `quantity`         INT           NOT NULL,
    `unit_price_cents` INT           NOT NULL,
    `tax_rate_bps`     INT           NOT NULL,
    `sort_order`       INT           NOT NULL DEFAULT 0,
    `created_at`       DATETIME      NOT NULL,
    `updated_at`       DATETIME      NOT NULL,
    KEY `idx_line_items_parent` (`parent_type`, `parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `quotes` (
    `id`              INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `organization_id` INT         NOT NULL,
    `client_id`       INT         NOT NULL,
    `quote_number`    VARCHAR(32) NOT NULL,
    `status`          VARCHAR(16) NOT NULL DEFAULT 'draft',
    `issued_at`       DATETIME    DEFAULT NULL,
    `valid_until`     DATETIME    DEFAULT NULL,
    `subtotal_cents`  INT         NOT NULL DEFAULT 0,
    `tax_cents`       INT         NOT NULL DEFAULT 0,
    `total_cents`     INT         NOT NULL DEFAULT 0,
    `notes`           TEXT        DEFAULT NULL,
    `is_deleted`      TINYINT(1)  NOT NULL DEFAULT 0,
    `deleted_at`      DATETIME    DEFAULT NULL,
    `created_at`      DATETIME    NOT NULL,
    `updated_at`      DATETIME    NOT NULL,
    KEY `idx_quotes_organization_id` (`organization_id`),
    UNIQUE KEY `uniq_quotes_org_number` (`organization_id`, `quote_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `invoices` (
    `id`                   INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `organization_id`      INT         NOT NULL,
    `client_id`            INT         NOT NULL,
    `quote_id`             INT         DEFAULT NULL,
    `invoice_number`       VARCHAR(32) DEFAULT NULL,
    `status`               VARCHAR(16) NOT NULL DEFAULT 'draft',
    `is_qualified_invoice` TINYINT(1)  NOT NULL DEFAULT 0,
    `issued_at`            DATETIME    DEFAULT NULL,
    `due_at`               DATETIME    DEFAULT NULL,
    `subtotal_cents`       INT         NOT NULL DEFAULT 0,
    `tax_cents`            INT         NOT NULL DEFAULT 0,
    `total_cents`          INT         NOT NULL DEFAULT 0,
    `notes`                TEXT        DEFAULT NULL,
    `is_deleted`           TINYINT(1)  NOT NULL DEFAULT 0,
    `deleted_at`           DATETIME    DEFAULT NULL,
    `created_at`           DATETIME    NOT NULL,
    `updated_at`           DATETIME    NOT NULL,
    KEY `idx_invoices_organization_id` (`organization_id`),
    UNIQUE KEY `uniq_invoices_org_number` (`organization_id`, `invoice_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payments` (
    `id`                  INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `organization_id`     INT          NOT NULL,
    `invoice_id`          INT          NOT NULL,
    `amount_cents`        INT          NOT NULL,
    `paid_at`             DATETIME     NOT NULL,
    `method`              VARCHAR(32)  DEFAULT NULL,
    `note`                TEXT         DEFAULT NULL,
    `external_reference`  VARCHAR(255) DEFAULT NULL,
    `idempotency_key`     VARCHAR(255) DEFAULT NULL,
    `is_deleted`          TINYINT(1)   NOT NULL DEFAULT 0,
    `deleted_at`          DATETIME     DEFAULT NULL,
    `created_at`          DATETIME     NOT NULL,
    `updated_at`          DATETIME     NOT NULL,
    KEY `idx_payments_organization_id` (`organization_id`),
    KEY `idx_payments_invoice_id` (`invoice_id`),
    UNIQUE KEY `uniq_payments_idempotency_key` (`idempotency_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `invoice_download_tokens` (
    `id`              INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `invoice_id`      INT         NOT NULL,
    `organization_id` INT         NOT NULL,
    `token_hash`      VARCHAR(64) NOT NULL,
    `expires_at`      DATETIME    NOT NULL,
    `created_at`      DATETIME    NOT NULL,
    UNIQUE KEY `uniq_download_tokens_hash` (`token_hash`),
    KEY `idx_download_tokens_invoice_id` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET foreign_key_checks = 1;

-- ---------------------------------------------------------------------------
-- login_attempts — failed login throttling per source IP (security: F-2).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`         INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `created_at` DATETIME    NOT NULL,
    KEY `idx_login_attempts_ip_time` (`ip_address`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- service_tokens — registry of issued NeNe Clear service tokens (ADR 0009).
-- Metadata only; the token value is never stored. `jti` keys revocation.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_tokens` (
    `id`              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `organization_id` INT          NOT NULL,
    `jti`             VARCHAR(64)  NOT NULL,
    `subject`         VARCHAR(255) NOT NULL,
    `label`           VARCHAR(255) NOT NULL,
    `scopes`          VARCHAR(255) NOT NULL,
    `created_by`      INT          DEFAULT NULL,
    `created_at`      DATETIME     NOT NULL,
    `expires_at`      DATETIME     NOT NULL,
    `revoked_at`      DATETIME     DEFAULT NULL,
    UNIQUE KEY `uniq_service_tokens_jti` (`jti`),
    KEY `idx_service_tokens_organization_id` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- payment_links — hashed, time-limited, revocable links that let a payer settle
-- one invoice on a hosted gateway (PAY.JP — ADR 0012/0013). Only the SHA-256
-- hash of the raw URL token is stored; card data is never stored (SAQ-A).
-- status: active | paid | revoked (expiry is derived from expires_at).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_links` (
    `id`                 INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `organization_id`    INT          NOT NULL,
    `invoice_id`         INT          NOT NULL,
    `token_hash`         VARCHAR(64)  NOT NULL,
    `gateway`            VARCHAR(32)  NOT NULL,
    `gateway_session_id` VARCHAR(255) DEFAULT NULL,
    `status`             VARCHAR(16)  NOT NULL,
    `expires_at`         DATETIME     NOT NULL,
    `paid_at`            DATETIME     DEFAULT NULL,
    `revoked_at`         DATETIME     DEFAULT NULL,
    `created_at`         DATETIME     NOT NULL,
    `updated_at`         DATETIME     NOT NULL,
    UNIQUE KEY `uniq_payment_links_token_hash` (`token_hash`),
    KEY `idx_payment_links_invoice_id` (`invoice_id`),
    KEY `idx_payment_links_gateway_session_id` (`gateway_session_id`),
    KEY `idx_payment_links_organization_id` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- refresh_tokens — rotating refresh tokens for silent re-authentication
-- (ADR 0014). Only the SHA-256 hash of the opaque token is stored. `family_id`
-- ties a rotation lineage so presenting a used/revoked token revokes the family.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `refresh_tokens` (
    `id`              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `organization_id` INT          DEFAULT NULL,
    `user_id`         INT          NOT NULL,
    `family_id`       VARCHAR(64)  NOT NULL,
    `token_hash`      VARCHAR(64)  NOT NULL,
    `issued_at`       DATETIME     NOT NULL,
    `expires_at`      DATETIME     NOT NULL,
    `used_at`         DATETIME     DEFAULT NULL,
    `revoked_at`      DATETIME     DEFAULT NULL,
    `created_at`      DATETIME     NOT NULL,
    UNIQUE KEY `uniq_refresh_tokens_token_hash` (`token_hash`),
    KEY `idx_refresh_tokens_family_id` (`family_id`),
    KEY `idx_refresh_tokens_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- items — reusable line-item master (品目マスタ), soft-deletable.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `items` (
    `id`                       INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `organization_id`          INT          NOT NULL,
    `description`              VARCHAR(255) NOT NULL,
    `default_unit_price_cents` INT          NOT NULL DEFAULT 0,
    `default_tax_rate_bps`     INT          NOT NULL DEFAULT 1000,
    `is_deleted`               TINYINT(1)   NOT NULL DEFAULT 0,
    `deleted_at`               DATETIME     DEFAULT NULL,
    `created_at`               DATETIME     NOT NULL,
    `updated_at`               DATETIME     NOT NULL,
    KEY `idx_items_organization_id` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- templates — reusable document templates (header + shared line_items),
-- soft-deletable.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `templates` (
    `id`              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `organization_id` INT          NOT NULL,
    `name`            VARCHAR(255) NOT NULL,
    `notes`           TEXT         DEFAULT NULL,
    `is_deleted`      TINYINT(1)   NOT NULL DEFAULT 0,
    `deleted_at`      DATETIME     DEFAULT NULL,
    `created_at`      DATETIME     NOT NULL,
    `updated_at`      DATETIME     NOT NULL,
    KEY `idx_templates_organization_id` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- recurring_invoices — recurring-billing schedules (#503). Generates an invoice
-- every period (顧問料・保守費・管理料・月謝). Line template lives in line_items;
-- next_run_on/last_run_on are calendar dates. frequency: monthly | quarterly.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recurring_invoices` (
    `id`              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `organization_id` INT          NOT NULL,
    `client_id`       INT          NOT NULL,
    `name`            VARCHAR(255) NOT NULL,
    `frequency`       VARCHAR(16)  NOT NULL,
    `subtotal_cents`  INT          NOT NULL DEFAULT 0,
    `tax_cents`       INT          NOT NULL DEFAULT 0,
    `total_cents`     INT          NOT NULL DEFAULT 0,
    `next_run_on`     DATE         NOT NULL,
    `last_run_on`     DATE         DEFAULT NULL,
    `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
    `notes`           TEXT         DEFAULT NULL,
    `is_deleted`      TINYINT(1)   NOT NULL DEFAULT 0,
    `deleted_at`      DATETIME     DEFAULT NULL,
    `created_at`      DATETIME     NOT NULL,
    `updated_at`      DATETIME     NOT NULL,
    KEY `idx_recurring_invoices_organization_id` (`organization_id`),
    KEY `idx_recurring_invoices_due` (`organization_id`, `is_active`, `next_run_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- bank_transactions — staging for bank-deposit CSV auto-reconciliation (#505).
-- One line imported from a bank CSV; integer cents (ADR 0004); value_date is a
-- calendar date. Importing only stages rows — matching/posting a payment is a
-- separate, compliance-reviewed step. direction: credit | debit.
-- status: unmatched | matched | posted | ignored.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bank_transactions` (
    `id`                 INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `organization_id`    INT          NOT NULL,
    `value_date`         DATE         NOT NULL,
    `direction`          VARCHAR(8)   NOT NULL,
    `amount_cents`       INT          NOT NULL,
    `payer_name`         VARCHAR(255) DEFAULT NULL,
    `description`        VARCHAR(512) DEFAULT NULL,
    `bank_reference`     VARCHAR(255) DEFAULT NULL,
    `status`             VARCHAR(16)  NOT NULL DEFAULT 'unmatched',
    `matched_invoice_id` INT          DEFAULT NULL,
    `matched_payment_id` INT          DEFAULT NULL,
    `imported_at`        DATETIME     NOT NULL,
    `created_at`         DATETIME     NOT NULL,
    `updated_at`         DATETIME     NOT NULL,
    KEY `idx_bank_transactions_organization_id` (`organization_id`),
    KEY `idx_bank_transactions_status` (`organization_id`, `status`),
    KEY `idx_bank_transactions_bank_reference` (`organization_id`, `bank_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- payer_aliases — learned remitter-name → client map for auto-reconciliation
-- (#505). normalized_name is produced by PayerNameNormalizer and is unique per
-- organization. Matching metadata, not a billing record.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payer_aliases` (
    `id`              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `organization_id` INT          NOT NULL,
    `normalized_name` VARCHAR(255) NOT NULL,
    `client_id`       INT          NOT NULL,
    `created_at`      DATETIME     NOT NULL,
    `updated_at`      DATETIME     NOT NULL,
    UNIQUE KEY `uq_payer_aliases_org_name` (`organization_id`, `normalized_name`),
    KEY `idx_payer_aliases_organization_id` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
