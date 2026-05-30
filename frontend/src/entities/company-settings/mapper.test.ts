import { describe, expect, it } from 'vitest'
import type { CompanySettingsDto } from './api-types'
import { toCompanySettings } from './mapper'

describe('toCompanySettings', () => {
  it('maps a fully-populated profile', () => {
    const dto: CompanySettingsDto = {
      organization_id: 1,
      legal_name: '株式会社あやね',
      address: '東京都渋谷区1-1-1',
      phone: '03-0000-0000',
      email: 'info@example.com',
      registration_number: 'T1234567890123',
      bank_name: 'みずほ',
      bank_branch: '渋谷支店',
      account_type: '普通',
      account_number: '1234567',
    }

    const settings = toCompanySettings(dto)
    expect(settings.legal_name).toBe('株式会社あやね')
    expect(settings.registration_number).toBe('T1234567890123')
    expect(settings.account_number).toBe('1234567')
  })

  it('normalises omitted optional fields to null', () => {
    const settings = toCompanySettings({ organization_id: 2, legal_name: 'Minimal' })
    expect(settings.organization_id).toBe(2)
    expect(settings.legal_name).toBe('Minimal')
    expect(settings.address).toBeNull()
    expect(settings.registration_number).toBeNull()
    expect(settings.bank_name).toBeNull()
    expect(settings.account_number).toBeNull()
  })
})
