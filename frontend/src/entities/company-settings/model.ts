/** PDF appearance enums (Issue #449). Values match terminology.md §2. */
export type PdfTemplate = 'standard' | 'modern' | 'classic'
export type PdfSpacing = 'small' | 'medium' | 'large'
export type PdfHeadingFont = 'gothic' | 'mincho'

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
  /** PDF layout template for 見積書 / 請求書 (Issue #449). */
  pdf_template: PdfTemplate
  /** PDF spacing scale (gap/margin/padding 大中小). */
  pdf_spacing: PdfSpacing
  /** PDF heading font (見出しフォント ゴシック/明朝). */
  pdf_heading_font: PdfHeadingFont
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
  pdf_template: PdfTemplate
  pdf_spacing: PdfSpacing
  pdf_heading_font: PdfHeadingFont
}
