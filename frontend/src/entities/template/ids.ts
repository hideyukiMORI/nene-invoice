export type TemplateId = number & { readonly __brand: 'TemplateId' }

export function toTemplateId(value: number): TemplateId {
  return value as TemplateId
}
