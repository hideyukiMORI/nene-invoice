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
}
