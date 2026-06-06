export { type TemplateId, toTemplateId } from './ids'
export type {
  Template,
  TemplateLine,
  TemplateWithLines,
  TemplatePage,
  CreateTemplateInput,
  UpdateTemplateInput,
} from './model'
export { templateKeys, type TemplateListParams } from './query-keys'
export { useTemplateList, useTemplate, fetchTemplate } from './queries'
export { useCreateTemplate, useUpdateTemplate, useDeleteTemplate } from './mutations'
