import { useState } from 'react'
import {
  useRevokeServiceToken,
  type IssuedServiceToken,
  type ServiceScope,
  type ServiceToken,
  type ServiceTokenStatus,
} from '@/entities/service-token'
import { useTranslation } from '@/shared/i18n'
import {
  Badge,
  type BadgeTone,
  Button,
  ConfirmDialog,
  EmptyState,
  ErrorState,
  Field,
  InlineAlert,
  Input,
  LoadingState,
  Stack,
  Text,
  useToast,
} from '@/shared/ui'
import { useIssueServiceToken } from '../model/use-issue-service-token'
import { useListServiceTokens } from '../model/use-list-service-tokens'

const ALL_SCOPES: ServiceScope[] = ['read:invoices', 'write:payments']

const STATUS_TONE: Record<ServiceTokenStatus, BadgeTone> = {
  active: 'ok',
  revoked: 'neutral',
}

/** One-time reveal of a freshly issued token (never retrievable again). */
function IssuedTokenPanel({
  token,
  onDismiss,
}: {
  token: IssuedServiceToken
  onDismiss: () => void
}) {
  const { t } = useTranslation()
  const { showToast } = useToast()

  const copy = (): void => {
    void navigator.clipboard
      .writeText(token.token)
      .then(() => {
        showToast({ tone: 'ok', title: t('admin.serviceTokens.issued.copied') })
      })
      .catch(() => {
        showToast({ tone: 'err', title: t('admin.serviceTokens.issued.copyError') })
      })
  }

  return (
    <div className="rounded-md border border-border bg-surface-raised p-stack-md">
      <Stack gap="sm">
        <Text variant="heading-sm">
          {t('admin.serviceTokens.issued.title', { label: token.label })}
        </Text>
        <InlineAlert tone="info" message={t('admin.serviceTokens.issued.warning')} />
        <Field id="issued-token" label={t('admin.serviceTokens.issued.tokenLabel')}>
          <Input
            id="issued-token"
            readOnly
            value={token.token}
            onFocus={(e) => {
              e.currentTarget.select()
            }}
          />
        </Field>
        <Stack direction="row" gap="sm">
          <Button type="button" size="sm" onClick={copy}>
            {t('admin.serviceTokens.issued.copy')}
          </Button>
          <Button type="button" size="sm" variant="ghost" onClick={onDismiss}>
            {t('admin.serviceTokens.issued.done')}
          </Button>
        </Stack>
      </Stack>
    </div>
  )
}

/** Issue-token form: label + scope checkboxes. */
function IssueForm({ onIssued }: { onIssued: (token: IssuedServiceToken) => void }) {
  const { t } = useTranslation()
  const { form, onSubmit, isPending, errorMessage } = useIssueServiceToken(onIssued)
  const {
    register,
    formState: { errors },
  } = form

  return (
    <form
      onSubmit={onSubmit}
      noValidate
      className="rounded-md border border-border bg-surface-raised p-stack-md"
    >
      <Stack gap="md">
        <Text variant="heading-sm">{t('admin.serviceTokens.issue.title')}</Text>

        <Field
          id="label"
          label={t('admin.serviceTokens.issue.label')}
          error={errors.label ? t('admin.serviceTokens.issue.labelRequired') : undefined}
        >
          <Input id="label" aria-invalid={errors.label ? true : undefined} {...register('label')} />
        </Field>

        <Stack gap="sm">
          <Text variant="muted">{t('admin.serviceTokens.issue.scopes')}</Text>
          {ALL_SCOPES.map((scope) => (
            <label key={scope} className="flex items-center gap-inline-sm text-body">
              <input type="checkbox" value={scope} {...register('scopes')} />
              <span>{t(`admin.serviceTokens.scope.${scope}`)}</span>
            </label>
          ))}
          {errors.scopes && (
            <Text variant="muted" role="alert" className="text-danger">
              {t('admin.serviceTokens.issue.scopesRequired')}
            </Text>
          )}
        </Stack>

        {errorMessage !== null && <InlineAlert tone="error" message={errorMessage} />}

        <div>
          <Button type="submit" disabled={isPending}>
            {isPending
              ? t('admin.serviceTokens.issue.submitting')
              : t('admin.serviceTokens.issue.submit')}
          </Button>
        </div>
      </Stack>
    </form>
  )
}

/** Service-token management screen: issue, list, and revoke (admin oversight). */
export function ManageServiceTokens() {
  const { t } = useTranslation()
  const state = useListServiceTokens()
  const revoke = useRevokeServiceToken()
  const [issued, setIssued] = useState<IssuedServiceToken | null>(null)
  const [pendingRevoke, setPendingRevoke] = useState<ServiceToken | null>(null)

  const confirmRevoke = (): void => {
    if (pendingRevoke === null) return
    revoke.mutate(pendingRevoke.id, {
      onSuccess: () => {
        setPendingRevoke(null)
      },
    })
  }

  return (
    <Stack gap="md">
      <Stack gap="sm">
        <Text as="h1" variant="heading-md">
          {t('admin.serviceTokens.title')}
        </Text>
        <Text variant="muted">{t('admin.serviceTokens.subtitle')}</Text>
      </Stack>

      {issued !== null && (
        <IssuedTokenPanel
          token={issued}
          onDismiss={() => {
            setIssued(null)
          }}
        />
      )}

      <IssueForm onIssued={setIssued} />

      {state.kind === 'loading' && <LoadingState message={t('admin.serviceTokens.loading')} />}

      {state.kind === 'error' && (
        <ErrorState
          message={t('admin.serviceTokens.error')}
          retryLabel={t('common.actions.retry')}
          onRetry={state.retry}
        />
      )}

      {state.kind === 'empty' && <EmptyState message={t('admin.serviceTokens.empty')} />}

      {state.kind === 'ready' && (
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                <th>{t('admin.serviceTokens.col.label')}</th>
                <th>{t('admin.serviceTokens.col.scopes')}</th>
                <th>{t('admin.serviceTokens.col.createdAt')}</th>
                <th>{t('admin.serviceTokens.col.expiresAt')}</th>
                <th>{t('admin.serviceTokens.col.status')}</th>
                <th className="tr">{t('admin.serviceTokens.col.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {state.tokens.map((token) => (
                <tr key={token.id}>
                  <td data-label={t('admin.serviceTokens.col.label')}>
                    <Stack gap="sm">
                      <span>{token.label}</span>
                      <Text variant="muted">{token.subject}</Text>
                    </Stack>
                  </td>
                  <td data-label={t('admin.serviceTokens.col.scopes')}>
                    <Stack direction="row" gap="sm" className="flex-wrap">
                      {token.scopes.map((scope) => (
                        <Badge key={scope} tone="info">
                          {t(`admin.serviceTokens.scope.${scope}`)}
                        </Badge>
                      ))}
                    </Stack>
                  </td>
                  <td data-label={t('admin.serviceTokens.col.createdAt')}>{token.created_at}</td>
                  <td data-label={t('admin.serviceTokens.col.expiresAt')}>{token.expires_at}</td>
                  <td data-label={t('admin.serviceTokens.col.status')}>
                    <Badge tone={STATUS_TONE[token.status]}>
                      {t(`admin.serviceTokens.status.${token.status}`)}
                    </Badge>
                  </td>
                  <td className="tr" data-label={t('admin.serviceTokens.col.actions')}>
                    {token.status === 'active' ? (
                      <button
                        type="button"
                        className="cursor-pointer border-0 bg-transparent p-0 text-body text-danger"
                        onClick={() => {
                          setPendingRevoke(token)
                        }}
                      >
                        {t('admin.serviceTokens.revoke.action')}
                      </button>
                    ) : (
                      <Text variant="muted">—</Text>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {revoke.isError && (
        <Text variant="muted" role="alert">
          {t('admin.serviceTokens.revoke.error')}
        </Text>
      )}

      {pendingRevoke !== null && (
        <ConfirmDialog
          title={t('admin.serviceTokens.revoke.title')}
          message={t('admin.serviceTokens.revoke.message', { label: pendingRevoke.label })}
          confirmLabel={t('admin.serviceTokens.revoke.confirm')}
          cancelLabel={t('common.actions.cancel')}
          pending={revoke.isPending}
          onConfirm={confirmRevoke}
          onCancel={() => {
            setPendingRevoke(null)
          }}
        />
      )}
    </Stack>
  )
}
