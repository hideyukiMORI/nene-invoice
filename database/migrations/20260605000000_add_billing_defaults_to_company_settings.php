<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Company-level billing defaults (Issue #268): default quote validity period and
 * the invoice payment-terms model (締め日 + 支払サイト). All nullable — existing
 * organizations keep "no default" behaviour. `month_offset` doubles as the
 * presence flag for the payment-terms default (null = not configured); a null
 * `closing_day` / `pay_day` means 末日 (month-end) when terms are configured.
 */
final class AddBillingDefaultsToCompanySettings extends AbstractMigration
{
    public function change(): void
    {
        $this->table('company_settings')
            ->addColumn('default_quote_validity_days', 'integer', ['null' => true, 'default' => null])
            ->addColumn('default_payment_closing_day', 'integer', ['null' => true, 'default' => null])
            ->addColumn('default_payment_month_offset', 'integer', ['null' => true, 'default' => null])
            ->addColumn('default_payment_pay_day', 'integer', ['null' => true, 'default' => null])
            ->update();
    }
}
