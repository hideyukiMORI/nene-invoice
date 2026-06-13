import type { GatewayConnectivityDto, GatewaySettingsDto } from './api-types'
import type { GatewayConnectivity, GatewaySettings } from './model'

export function toGatewaySettings(dto: GatewaySettingsDto): GatewaySettings {
  return {
    gateway: dto.gateway,
    publicKeyMasked: dto.public_key_masked ?? null,
    secretSet: dto.secret_set,
    webhookTokenSet: dto.webhook_token_set,
    configured: dto.configured,
  }
}

export function toGatewayConnectivity(dto: GatewayConnectivityDto): GatewayConnectivity {
  return {
    ok: dto.ok,
    detail: dto.detail,
  }
}
