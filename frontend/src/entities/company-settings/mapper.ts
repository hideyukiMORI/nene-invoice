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
  }
}
