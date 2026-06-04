import type { MessageKey } from '@/shared/i18n'

/**
 * Maps audit `entity_type` / `action` system identifiers to i18n message keys
 * so the trail reads in accounting terms (請求書を発行 …) instead of raw codes
 * (invoice.issued …). Unknown values fall back to the raw string, so a newly
 * added action still renders something until it is mapped here.
 */

const ENTITY_LABEL_KEY: Record<string, MessageKey> = {
  organization: 'admin.audit.entity.organization',
  user: 'admin.audit.entity.user',
  client: 'admin.audit.entity.client',
  company_settings: 'admin.audit.entity.company_settings',
  quote: 'admin.audit.entity.quote',
  invoice: 'admin.audit.entity.invoice',
  payment: 'admin.audit.entity.payment',
}

const ACTION_LABEL_KEY: Record<string, MessageKey> = {
  'organization.created': 'admin.audit.action.organization.created',
  'organization.deleted': 'admin.audit.action.organization.deleted',
  'user.created': 'admin.audit.action.user.created',
  'user.updated': 'admin.audit.action.user.updated',
  'user.deleted': 'admin.audit.action.user.deleted',
  'client.created': 'admin.audit.action.client.created',
  'client.updated': 'admin.audit.action.client.updated',
  'client.deleted': 'admin.audit.action.client.deleted',
  'company_settings.created': 'admin.audit.action.company_settings.created',
  'company_settings.updated': 'admin.audit.action.company_settings.updated',
  'quote.created': 'admin.audit.action.quote.created',
  'quote.status_changed': 'admin.audit.action.quote.status_changed',
  'invoice.created': 'admin.audit.action.invoice.created',
  'invoice.issued': 'admin.audit.action.invoice.issued',
  'invoice.sent': 'admin.audit.action.invoice.sent',
  'invoice.download_token_issued': 'admin.audit.action.invoice.download_token_issued',
  'payment.recorded': 'admin.audit.action.payment.recorded',
  'payment.voided': 'admin.audit.action.payment.voided',
}

/** Entity types that can appear in the trail (terminology registry §1). */
export const AUDIT_ENTITY_TYPES = Object.keys(ENTITY_LABEL_KEY)

/** Known actions, for the audit filter dropdown. */
export const AUDIT_ACTIONS = Object.keys(ACTION_LABEL_KEY)

export function auditEntityLabelKey(entityType: string): MessageKey | undefined {
  return ENTITY_LABEL_KEY[entityType]
}

export function auditActionLabelKey(action: string): MessageKey | undefined {
  return ACTION_LABEL_KEY[action]
}
