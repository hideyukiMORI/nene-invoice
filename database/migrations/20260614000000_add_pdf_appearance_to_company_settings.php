<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * PDF appearance settings (Issue #449): per-issuer layout template, spacing
 * scale and heading font for 見積書 / 請求書 PDFs. All non-null with backward-
 * compatible defaults so existing organizations keep the current look
 * (`standard` / `medium` / `gothic`). Allowed values live in terminology.md §2.
 *
 * These are presentation-only: every template/spacing/font combination must
 * still render all 適格請求書 required fields (accounting-compliance.md).
 */
final class AddPdfAppearanceToCompanySettings extends AbstractMigration
{
    public function change(): void
    {
        $this->table('company_settings')
            ->addColumn('pdf_template', 'string', ['limit' => 16, 'null' => false, 'default' => 'standard'])
            ->addColumn('pdf_spacing', 'string', ['limit' => 16, 'null' => false, 'default' => 'medium'])
            ->addColumn('pdf_heading_font', 'string', ['limit' => 16, 'null' => false, 'default' => 'gothic'])
            ->update();
    }
}
