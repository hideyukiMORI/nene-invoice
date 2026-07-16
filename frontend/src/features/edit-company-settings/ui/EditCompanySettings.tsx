import { useWatch, type Control } from 'react-hook-form'
import { useTranslation } from '@/shared/i18n'
import {
  Button,
  ErrorState,
  Field,
  FormLayout,
  FormRow,
  Input,
  LoadingState,
  Select,
  Stack,
  Text,
} from '@/shared/ui'
import {
  useEditCompanySettings,
  type EditCompanySettingsFormValues,
} from '../model/use-edit-company-settings'

/** Curated closing/pay days (design 03); 末日 is the empty option. */
const DAY_OPTIONS = [25, 20, 15, 10, 5]

const lastDayOfMonth = (year: number, monthIndex: number): number =>
  new Date(year, monthIndex + 1, 0).getDate()

/**
 * Live preview of the payment-terms rule and a worked example from today's date
 * (design 03). Mirrors the backend PaymentTerms math; the issued due date stays
 * backend-authoritative.
 */
function PayPreview({ control }: { control: Control<EditCompanySettingsFormValues> }) {
  const { t } = useTranslation()
  const closing = useWatch({ control, name: 'default_payment_closing_day' })
  const offset = useWatch({ control, name: 'default_payment_month_offset' })
  const pay = useWatch({ control, name: 'default_payment_pay_day' })

  const calIcon = (
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.6" aria-hidden="true">
      <rect x="3" y="4" width="14" height="13" rx="1.5" />
      <path d="M3 8h14M7 2.5v3M13 2.5v3" />
    </svg>
  )

  if (offset === '') {
    return (
      <div className="pay-preview" role="status" style={{ opacity: 0.72 }}>
        {calIcon}
        <span className="pp-rule">{t('admin.settings.payPreviewNone')}</span>
      </div>
    )
  }

  const dayLabel = (v: string): string =>
    v === '' ? t('admin.settings.monthEnd') : t('admin.settings.dayNth', { n: Number(v) })
  const monthLabel = (v: string): string =>
    ({ '0': t('admin.settings.offsetCurrent'), '1': t('admin.settings.offsetNext') })[v] ??
    t('admin.settings.offsetNext2')

  const now = new Date()
  const cy = now.getFullYear()
  const cm = now.getMonth()
  const cutD = closing === '' ? lastDayOfMonth(cy, cm) : Number(closing)
  const closeStr = `${String(cm + 1)}/${String(cutD)}`

  let py = cy
  let pmo = cm + Number(offset)
  while (pmo > 11) {
    pmo -= 12
    py += 1
  }
  const payD = pay === '' ? lastDayOfMonth(py, pmo) : Number(pay)
  const payStr = `${String(pmo + 1)}/${String(payD)}`

  return (
    <div className="pay-preview" role="status">
      {calIcon}
      <span className="pp-rule">
        {t('admin.settings.payPreviewRule', {
          closing: dayLabel(closing),
          month: monthLabel(offset),
          pay: dayLabel(pay),
        })}
      </span>
      <span className="pp-eg">
        {t('admin.settings.payPreviewExamplePrefix')}
        <b>{closeStr}</b>
        {t('admin.settings.payPreviewExampleMid')}
        <b>{payStr}</b>
        {t('admin.settings.payPreviewExampleSuffix')}
      </span>
    </div>
  )
}

/** Company settings (自社情報) edit form — upserts on submit, stays on page. */
export function EditCompanySettings() {
  const { t } = useTranslation()
  const state = useEditCompanySettings()

  if (state.kind === 'loading') {
    return <LoadingState message={t('admin.settings.loading')} />
  }

  if (state.kind === 'error') {
    return (
      <ErrorState
        message={t('admin.settings.loadError')}
        retryLabel={t('common.actions.retry')}
        onRetry={state.retry}
      />
    )
  }

  const {
    register,
    formState: { errors },
  } = state.form

  return (
    <FormLayout>
      <form onSubmit={state.onSubmit} noValidate>
        <Stack gap="md">
          <Text as="h1" variant="heading-md">
            {t('admin.settings.title')}
          </Text>

          <div className="card">
            <div className="section-title">{t('admin.settings.basicSection')}</div>
            <Stack gap="md">
              <Field
                id="legal_name"
                label={t('admin.settings.legalName')}
                error={errors.legal_name ? t('admin.settings.legalNameRequired') : undefined}
              >
                <Input
                  id="legal_name"
                  aria-invalid={errors.legal_name ? true : undefined}
                  {...register('legal_name')}
                />
              </Field>

              <Field id="address" label={t('admin.settings.address')}>
                <Input id="address" {...register('address')} />
              </Field>

              <FormRow>
                <Field id="phone" label={t('admin.settings.phone')}>
                  <Input id="phone" type="tel" {...register('phone')} />
                </Field>
                <Field id="email" label={t('admin.settings.email')}>
                  <Input id="email" type="email" {...register('email')} />
                </Field>
              </FormRow>

              <Field id="registration_number" label={t('admin.settings.registrationNumber')}>
                <Input
                  id="registration_number"
                  placeholder="T1234567890123"
                  {...register('registration_number')}
                />
                <Text variant="muted" className="text-caption">
                  {t('admin.settings.registrationNumberHint')}
                </Text>
              </Field>
            </Stack>
          </div>

          <div className="card">
            <div className="section-title">{t('admin.settings.bankSection')}</div>
            <Stack gap="md">
              <FormRow>
                <Field id="bank_name" label={t('admin.settings.bankName')}>
                  <Input id="bank_name" {...register('bank_name')} />
                </Field>
                <Field id="bank_branch" label={t('admin.settings.bankBranch')}>
                  <Input id="bank_branch" {...register('bank_branch')} />
                </Field>
              </FormRow>

              <FormRow>
                <Field id="account_type" label={t('admin.settings.accountType')}>
                  <Input
                    id="account_type"
                    placeholder={t('admin.settings.accountTypePlaceholder')}
                    {...register('account_type')}
                  />
                </Field>
                <Field id="account_number" label={t('admin.settings.accountNumber')}>
                  <Input id="account_number" {...register('account_number')} />
                </Field>
              </FormRow>
            </Stack>
          </div>

          <div className="card">
            <div className="section-title">{t('admin.settings.billingSection')}</div>
            <Stack gap="md">
              <Field id="default_quote_validity_days" label={t('admin.settings.quoteValidityDays')}>
                <Input
                  id="default_quote_validity_days"
                  type="number"
                  min={1}
                  max={3650}
                  inputMode="numeric"
                  placeholder={t('admin.settings.quoteValidityPlaceholder')}
                  {...register('default_quote_validity_days')}
                />
                <Text variant="muted" className="text-caption">
                  {t('admin.settings.quoteValidityHint')}
                </Text>
              </Field>

              <div className="field-sep" />

              <Text variant="muted" className="text-caption">
                {t('admin.settings.paymentTermsHint')}
              </Text>
              <div className="pay-site">
                <Field id="default_payment_closing_day" label={t('admin.settings.closingDay')}>
                  <Select
                    id="default_payment_closing_day"
                    {...register('default_payment_closing_day')}
                  >
                    <option value="">{t('admin.settings.monthEnd')}</option>
                    {DAY_OPTIONS.map((d) => (
                      <option key={d} value={d}>
                        {t('admin.settings.dayNth', { n: d })}
                      </option>
                    ))}
                  </Select>
                </Field>
                <div className="ps-arrow" aria-hidden="true">
                  <svg
                    viewBox="0 0 16 16"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.7"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  >
                    <path d="M2 8h11M9 4l4 4-4 4" />
                  </svg>
                </div>
                <Field id="default_payment_month_offset" label={t('admin.settings.monthOffset')}>
                  <Select
                    id="default_payment_month_offset"
                    {...register('default_payment_month_offset')}
                  >
                    <option value="0">{t('admin.settings.offsetCurrent')}</option>
                    <option value="1">{t('admin.settings.offsetNext')}</option>
                    <option value="2">{t('admin.settings.offsetNext2')}</option>
                    <option value="">{t('admin.settings.paymentTermsOff')}</option>
                  </Select>
                </Field>
                <Field id="default_payment_pay_day" label={t('admin.settings.payDay')}>
                  <Select id="default_payment_pay_day" {...register('default_payment_pay_day')}>
                    <option value="">{t('admin.settings.monthEnd')}</option>
                    {DAY_OPTIONS.map((d) => (
                      <option key={d} value={d}>
                        {t('admin.settings.dayNth', { n: d })}
                      </option>
                    ))}
                  </Select>
                </Field>
              </div>

              <PayPreview control={state.form.control} />
            </Stack>
          </div>

          <div className="card">
            <div className="section-title">{t('admin.settings.pdfSection')}</div>
            <Stack gap="md">
              <Text variant="muted" className="text-caption">
                {t('admin.settings.pdfHint')}
              </Text>
              <FormRow>
                <Field id="pdf_template" label={t('admin.settings.pdfTemplate')}>
                  <Select id="pdf_template" {...register('pdf_template')}>
                    <option value="standard">{t('admin.settings.pdfTemplateStandard')}</option>
                    <option value="modern">{t('admin.settings.pdfTemplateModern')}</option>
                    <option value="classic">{t('admin.settings.pdfTemplateClassic')}</option>
                  </Select>
                </Field>
                <Field id="pdf_spacing" label={t('admin.settings.pdfSpacing')}>
                  <Select id="pdf_spacing" {...register('pdf_spacing')}>
                    <option value="small">{t('admin.settings.pdfSpacingSmall')}</option>
                    <option value="medium">{t('admin.settings.pdfSpacingMedium')}</option>
                    <option value="large">{t('admin.settings.pdfSpacingLarge')}</option>
                  </Select>
                </Field>
                <Field id="pdf_heading_font" label={t('admin.settings.pdfHeadingFont')}>
                  <Select id="pdf_heading_font" {...register('pdf_heading_font')}>
                    <option value="gothic">{t('admin.settings.pdfFontGothic')}</option>
                    <option value="mincho">{t('admin.settings.pdfFontMincho')}</option>
                  </Select>
                </Field>
              </FormRow>
            </Stack>
          </div>

          {state.errorMessage !== null && (
            <Text variant="muted" role="alert">
              {state.errorMessage}
            </Text>
          )}

          {state.savedMessage !== null && (
            <Text variant="muted" role="status">
              {state.savedMessage}
            </Text>
          )}

          <div>
            <Button type="submit" disabled={state.isPending}>
              {state.isPending ? t('admin.settings.submitting') : t('admin.settings.submit')}
            </Button>
          </div>
        </Stack>
      </form>
    </FormLayout>
  )
}
