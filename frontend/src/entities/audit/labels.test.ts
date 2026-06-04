import { describe, expect, it } from 'vitest'
import { jaMessages } from '@/shared/i18n/messages/ja'
import {
  AUDIT_ACTIONS,
  AUDIT_ENTITY_TYPES,
  auditActionLabelKey,
  auditEntityLabelKey,
} from './labels'

describe('audit labels', () => {
  it('maps every known entity type to a defined ja message', () => {
    for (const type of AUDIT_ENTITY_TYPES) {
      const key = auditEntityLabelKey(type)
      expect(key).toBeDefined()
      expect(jaMessages[key as keyof typeof jaMessages]).toBeTruthy()
    }
  })

  it('maps every known action to a defined ja message', () => {
    for (const action of AUDIT_ACTIONS) {
      const key = auditActionLabelKey(action)
      expect(key).toBeDefined()
      expect(jaMessages[key as keyof typeof jaMessages]).toBeTruthy()
    }
  })

  it('returns undefined for unknown identifiers (caller falls back to raw)', () => {
    expect(auditActionLabelKey('something.new')).toBeUndefined()
    expect(auditEntityLabelKey('widget')).toBeUndefined()
  })

  it('localizes the known accounting actions', () => {
    expect(auditActionLabelKey('invoice.issued')).toBe('admin.audit.action.invoice.issued')
    expect(jaMessages['admin.audit.action.invoice.issued']).toBe('請求書を発行')
    expect(jaMessages['admin.audit.entity.payment']).toBe('入金')
  })
})
