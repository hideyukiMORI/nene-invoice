import type { components } from '@/shared/api/schema.gen'

export type CompanySealDto = components['schemas']['CompanySeal']
export type CompanySealStateDto = components['schemas']['CompanySealState']

/** UI read model for the issuer seal (社印) — Issue #448. */
export interface CompanySeal {
  has_seal: boolean
  /** Base64 PNG (no data-URI prefix), or null when has_seal is false. */
  image_base64: string | null
}
