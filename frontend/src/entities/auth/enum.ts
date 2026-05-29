export const ROLES = ['superadmin', 'admin', 'member', 'viewer'] as const

export type Role = (typeof ROLES)[number]
