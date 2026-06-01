import type { ReactNode } from 'react'
import { SignInForm } from '@/features/sign-in'
import { useTranslation } from '@/shared/i18n'

/** Overlapping-N monogram (案C 採用ロゴ 03). */
function Monogram({ backOpacity = 0.4 }: { backOpacity?: number }) {
  return (
    <span className="mono-mark" aria-hidden="true">
      <svg viewBox="0 0 42 42">
        <text
          x="-2"
          y="31"
          fontFamily="sans-serif"
          fontWeight="800"
          fontSize="32"
          fill="currentColor"
          opacity={backOpacity}
        >
          N
        </text>
        <text
          x="11"
          y="31"
          fontFamily="sans-serif"
          fontWeight="800"
          fontSize="32"
          fill="currentColor"
        >
          N
        </text>
      </svg>
    </span>
  )
}

const featIcon = (path: ReactNode): ReactNode => (
  <svg
    viewBox="0 0 20 20"
    fill="none"
    stroke="currentColor"
    strokeWidth="1.7"
    strokeLinecap="round"
    strokeLinejoin="round"
  >
    {path}
  </svg>
)

const trustIcon = (path: ReactNode): ReactNode => (
  <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.7">
    {path}
  </svg>
)

/** Login screen: enterprise split-screen — brand/trust panel + sign-in form. */
export function LoginPage() {
  const { t } = useTranslation()

  const feats = [
    {
      icon: featIcon(<path d="M3.5 10.5l4 4 9-9" />),
      title: t('admin.auth.feat1Title'),
      desc: t('admin.auth.feat1Desc'),
    },
    {
      icon: featIcon(<path d="M10 2.5l6 2.5v4.5c0 3.6-2.4 6.6-6 8-3.6-1.4-6-4.4-6-8V5z" />),
      title: t('admin.auth.feat2Title'),
      desc: t('admin.auth.feat2Desc'),
    },
    {
      icon: featIcon(
        <>
          <circle cx="10" cy="10" r="7" />
          <path d="M10 6v4l2.5 2.5" />
        </>,
      ),
      title: t('admin.auth.feat3Title'),
      desc: t('admin.auth.feat3Desc'),
    },
  ]

  return (
    <main className="auth">
      <aside className="auth-brandside">
        <div className="auth-bs-top">
          <Monogram />
          <div>
            <div className="abt-name">NeNe Invoice</div>
            <div className="abt-sub">Invoice Management</div>
          </div>
        </div>

        <div className="auth-bs-mid">
          <p className="tagline">{t('admin.auth.brandTagline')}</p>
          <p className="lead">{t('admin.auth.brandLead')}</p>
          <ul className="auth-feats">
            {feats.map((f) => (
              <li key={f.title}>
                <span className="af-ico">{f.icon}</span>
                <span>
                  <b>{f.title}</b>
                  <span className="af-d">{f.desc}</span>
                </span>
              </li>
            ))}
          </ul>
        </div>

        <div className="auth-bs-foot">
          <div className="auth-trust">
            <span className="tb">
              {trustIcon(
                <>
                  <circle cx="7" cy="7" r="4.3" />
                  <path d="M10.2 10.2L14 14" />
                </>,
              )}
              {t('admin.auth.trustSecurity')}
            </span>
            <span className="tb">
              {trustIcon(
                <>
                  <rect x="2.5" y="3" width="11" height="4" rx="1" />
                  <rect x="2.5" y="9" width="11" height="4" rx="1" />
                  <path d="M5 5h.01M5 11h.01" />
                </>,
              )}
              {t('admin.auth.trustSelfhost')}
            </span>
            <span className="tb">
              {trustIcon(<path d="M6 5L2.5 8 6 11M10 5l3.5 3L10 11" />)}
              {t('admin.auth.trustOss')}
            </span>
            <span className="tb">
              {trustIcon(<path d="M8 2l5 2v3.5c0 3-2 5.3-5 6.5-3-1.2-5-3.5-5-6.5V4z" />)}
              {t('admin.auth.trustQualified')}
            </span>
          </div>
          <div className="copy">© {new Date().getFullYear()} NeNe Invoice</div>
        </div>
      </aside>

      <div className="auth-formside">
        <div className="auth-form">
          <div className="auth-mobilebrand">
            <Monogram backOpacity={0.32} />
            <b>NeNe Invoice</b>
          </div>
          <SignInForm />
        </div>
      </div>
    </main>
  )
}
