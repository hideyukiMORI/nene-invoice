import type { CompanySettingsDto } from './api-types'
import type { CompanySettings } from './model'

export function toCompanySettings(dto: CompanySettingsDto): CompanySettings {
  return {
    organization_id: dto.organization_id,
    legal_name: dto.legal_name,
    address: dto.address ?? null,
    phone: dto.phone ?? null,
    email: dto.email ?? null,
    registration_number: dto.registration_number ?? null,
    bank_name: dto.bank_name ?? null,
    bank_branch: dto.bank_branch ?? null,
    account_type: dto.account_type ?? null,
    account_number: dto.account_number ?? null,
    default_quote_validity_days: dto.default_quote_validity_days ?? null,
    default_payment_closing_day: dto.default_payment_closing_day ?? null,
    default_payment_month_offset: dto.default_payment_month_offset ?? null,
    default_payment_pay_day: dto.default_payment_pay_day ?? null,
    pdf_template: dto.pdf_template ?? 'standard',
    pdf_spacing: dto.pdf_spacing ?? 'medium',
    pdf_heading_font: dto.pdf_heading_font ?? 'gothic',
  }
}
