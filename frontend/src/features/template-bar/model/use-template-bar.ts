import { useState } from 'react'
import {
  fetchTemplate,
  useCreateTemplate,
  useTemplateList,
  type Template,
  type TemplateId,
  type TemplateLine,
  type TemplateWithLines,
} from '@/entities/template'
import { useTranslation } from '@/shared/i18n'
import { useToast } from '@/shared/ui'

/** The current form content a "save as template" captures. */
export interface TemplateSnapshot {
  notes: string | null
  line_items: TemplateLine[]
}

export interface UseTemplateBar {
  templates: Template[]
  /** Loads a template's presets into the form via `onApply`. */
  apply: (id: TemplateId) => Promise<void>
  applying: boolean
  saveOpen: boolean
  openSave: () => void
  closeSave: () => void
  /** Saves the current snapshot as a new named template. */
  save: (name: string) => Promise<void>
  saving: boolean
}

export interface UseTemplateBarOptions {
  getSnapshot: () => TemplateSnapshot
  onApply: (template: TemplateWithLines) => void
}

export function useTemplateBar({ getSnapshot, onApply }: UseTemplateBarOptions): UseTemplateBar {
  const { t } = useTranslation()
  const { showToast } = useToast()
  const list = useTemplateList({ limit: 100, offset: 0 })
  const create = useCreateTemplate()
  const [applying, setApplying] = useState(false)
  const [saveOpen, setSaveOpen] = useState(false)

  const apply = async (id: TemplateId): Promise<void> => {
    setApplying(true)
    try {
      const template = await fetchTemplate(id)
      onApply(template)
      showToast({ tone: 'ok', title: t('admin.templateBar.applied', { name: template.name }) })
    } catch {
      showToast({ tone: 'err', title: t('admin.templateBar.applyError') })
    } finally {
      setApplying(false)
    }
  }

  const save = async (name: string): Promise<void> => {
    const trimmed = name.trim()
    if (trimmed === '') return
    const snapshot = getSnapshot()
    try {
      await create.mutateAsync({
        name: trimmed,
        notes: snapshot.notes,
        line_items: snapshot.line_items,
      })
      showToast({ tone: 'ok', title: t('admin.templateBar.saved', { name: trimmed }) })
      setSaveOpen(false)
    } catch {
      showToast({ tone: 'err', title: t('admin.templateBar.saveError') })
    }
  }

  return {
    templates: list.data?.items ?? [],
    apply,
    applying,
    saveOpen,
    openSave: () => {
      setSaveOpen(true)
    },
    closeSave: () => {
      setSaveOpen(false)
    },
    save,
    saving: create.isPending,
  }
}
