/** UI read model for the issuer (自社) profile. Mirrors API snake_case fields. */
export interface CompanySettings {
  organization_id: number
  legal_name: string
  address: string | null
  phone: string | null
  email: string | null
  registration_number: string | null
  bank_name: string | null
  bank_branch: string | null
  account_type: string | null
  account_number: string | null
  /** Billing defaults (Issue #268). null = no default. */
  default_quote_validity_days: number | null
  /** 締め日 1–31; null = 末日 when terms are configured. */
  default_payment_closing_day: number | null
  /** 支払月 0=当月, 1=翌月 …; null = no payment-terms default. */
  default_payment_month_offset: number | null
  /** 支払日 1–31; null = 末日 when terms are configured. */
  default_payment_pay_day: number | null
}

export interface UpdateCompanySettingsInput {
  legal_name: string
  address: string | null
  phone: string | null
  email: string | null
  registration_number: string | null
  bank_name: string | null
  bank_branch: string | null
  account_type: string | null
  account_number: string | null
  default_quote_validity_days: number | null
  default_payment_closing_day: number | null
  default_payment_month_offset: number | null
  default_payment_pay_day: number | null
}
