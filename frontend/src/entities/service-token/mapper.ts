import type { CreatedServiceTokenDto, ServiceTokenDto, ServiceTokenListDto } from './api-types'
import { toServiceTokenId } from './ids'
import type { IssuedServiceToken, ServiceToken, ServiceTokenPage } from './model'

export function toServiceToken(dto: ServiceTokenDto): ServiceToken {
  return {
    id: toServiceTokenId(dto.id),
    subject: dto.subject,
    label: dto.label,
    scopes: dto.scopes,
    created_by: dto.created_by ?? null,
    created_at: dto.created_at,
    expires_at: dto.expires_at,
    revoked_at: dto.revoked_at ?? null,
    status: dto.status,
  }
}

export function toIssuedServiceToken(dto: CreatedServiceTokenDto): IssuedServiceToken {
  return { ...toServiceToken(dto), token: dto.token }
}

export function toServiceTokenPage(dto: ServiceTokenListDto): ServiceTokenPage {
  return {
    items: dto.items.map(toServiceToken),
    total: dto.total,
    limit: dto.limit,
    offset: dto.offset,
  }
}
