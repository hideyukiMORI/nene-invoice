import { useState } from 'react'
import { toTemplateId, type TemplateWithLines } from '@/entities/template'
import { useTranslation } from '@/shared/i18n'
import { Button, Input, Select, Stack } from '@/shared/ui'
import { useTemplateBar, type TemplateSnapshot } from '../model/use-template-bar'

export interface TemplateBarProps {
  /** Reads the current form content for "save as template". */
  getSnapshot: () => TemplateSnapshot
  /** Applies a loaded template's presets to the form. */
  onApply: (template: TemplateWithLines) => void
}

/**
 * Template toolbar for the create forms (#329 PR-3): load a saved template into
 * the form, or save the current lines + notes as a new named template.
 */
export function TemplateBar({ getSnapshot, onApply }: TemplateBarProps) {
  const { t } = useTranslation()
  const bar = useTemplateBar({ getSnapshot, onApply })
  const [name, setName] = useState('')

  return (
    <div className="card">
      <Stack direction="row" gap="sm" className="flex-wrap items-end justify-between">
        <div className="field-inline">
          <Select
            id="template-pick"
            aria-label={t('admin.templateBar.load')}
            value=""
            disabled={bar.applying || bar.templates.length === 0}
            onChange={(e) => {
              const id = Number(e.target.value)
              if (Number.isInteger(id) && id > 0) void bar.apply(toTemplateId(id))
            }}
          >
            <option value="">{t('admin.templateBar.load')}</option>
            {bar.templates.map((tpl) => (
              <option key={tpl.id} value={tpl.id}>
                {tpl.name}
              </option>
            ))}
          </Select>
        </div>

        {bar.saveOpen ? (
          <Stack direction="row" gap="sm" className="items-center">
            <Input
              id="template-save-name"
              aria-label={t('admin.templateBar.nameLabel')}
              placeholder={t('admin.templateBar.namePlaceholder')}
              value={name}
              onChange={(e) => {
                setName(e.target.value)
              }}
            />
            <Button
              type="button"
              size="sm"
              disabled={bar.saving || name.trim() === ''}
              onClick={() => {
                void bar.save(name).then(() => {
                  setName('')
                })
              }}
            >
              {bar.saving ? t('admin.templateBar.saving') : t('admin.templateBar.confirmSave')}
            </Button>
            <Button
              type="button"
              size="sm"
              variant="ghost"
              onClick={() => {
                bar.closeSave()
              }}
            >
              {t('common.actions.cancel')}
            </Button>
          </Stack>
        ) : (
          <Button type="button" size="sm" variant="ghost" onClick={bar.openSave}>
            {t('admin.templateBar.save')}
          </Button>
        )}
      </Stack>
    </div>
  )
}
