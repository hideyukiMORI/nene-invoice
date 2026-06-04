import type { ReactNode } from 'react'

export interface FormLayoutProps {
  children: ReactNode
}

/**
 * Constrained, left-aligned column for forms. Keeps inputs at a comfortable
 * reading width instead of stretching to the full content area (案C reference:
 * `.content.narrow`).
 */
export function FormLayout({ children }: FormLayoutProps) {
  return <div className="form-layout">{children}</div>
}

export interface FormRowProps {
  children: ReactNode
}

/**
 * Two even columns for paired fields (e.g. bank name / branch), collapsing to a
 * single column on narrow viewports (案C reference: `.form-row`).
 */
export function FormRow({ children }: FormRowProps) {
  return <div className="form-row">{children}</div>
}
