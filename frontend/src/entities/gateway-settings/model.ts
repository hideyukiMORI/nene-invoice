/** UI read model for the card payment gateway status. Secrets are never exposed. */
export interface GatewaySettings {
  gateway: string
  /** Masked public key for display, or null when unset. */
  publicKeyMasked: string | null
  secretSet: boolean
  webhookTokenSet: boolean
  configured: boolean
}

export type GatewayConnectivityDetail =
  | 'connected'
  | 'not_configured'
  | 'invalid_credentials'
  | 'unreachable'

export interface GatewayConnectivity {
  ok: boolean
  detail: GatewayConnectivityDetail
}
