import { describe, expect, it } from 'vitest'
import type { GatewayConnectivityDto, GatewaySettingsDto } from './api-types'
import { toGatewayConnectivity, toGatewaySettings } from './mapper'

describe('toGatewaySettings', () => {
  it('maps a configured gateway', () => {
    const dto: GatewaySettingsDto = {
      gateway: 'payjp',
      public_key_masked: 'pk_test_…59a6',
      secret_set: true,
      webhook_token_set: false,
      configured: true,
    }

    const settings = toGatewaySettings(dto)
    expect(settings.gateway).toBe('payjp')
    expect(settings.publicKeyMasked).toBe('pk_test_…59a6')
    expect(settings.secretSet).toBe(true)
    expect(settings.webhookTokenSet).toBe(false)
    expect(settings.configured).toBe(true)
  })

  it('normalises a null masked public key', () => {
    const settings = toGatewaySettings({
      gateway: 'payjp',
      public_key_masked: null,
      secret_set: false,
      webhook_token_set: false,
      configured: false,
    })
    expect(settings.publicKeyMasked).toBeNull()
    expect(settings.configured).toBe(false)
  })
})

describe('toGatewayConnectivity', () => {
  it('maps an ok result', () => {
    const dto: GatewayConnectivityDto = { ok: true, detail: 'connected' }
    expect(toGatewayConnectivity(dto)).toEqual({ ok: true, detail: 'connected' })
  })

  it('maps a failure result', () => {
    const dto: GatewayConnectivityDto = { ok: false, detail: 'invalid_credentials' }
    expect(toGatewayConnectivity(dto)).toEqual({ ok: false, detail: 'invalid_credentials' })
  })
})
