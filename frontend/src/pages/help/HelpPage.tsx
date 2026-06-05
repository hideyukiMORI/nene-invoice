import { useTranslation } from '@/shared/i18n'
import { Stack } from '@/shared/ui'
import { HELP_FAQ, HELP_LABELS, HELP_SECTIONS, type Bi, type HelpBlock } from './help-content'

/**
 * Help landing page (Issue #306): a beginner-oriented walkthrough of the
 * quote → invoice → payment flow plus an FAQ. Content lives in `help-content.ts`
 * as ja/en pairs and is picked by the active locale. Distinct from the `?`
 * shortcut cheat-sheet (keys), which is a separate overlay.
 */
export function HelpPage() {
  const { t, locale } = useTranslation()
  const pick = (b: Bi): string => (locale === 'en' ? b.en : b.ja)

  return (
    <Stack gap="md">
      <div className="page-head">
        <div>
          <h1 className="page-title">{t('admin.help.title')}</h1>
          <p className="page-sub">{t('admin.help.subtitle')}</p>
        </div>
      </div>

      <p className="help-intro">{pick(HELP_LABELS.intro)}</p>

      <nav id="contents" className="card help-toc" aria-label={pick(HELP_LABELS.toc)}>
        <p className="help-toc-h">{pick(HELP_LABELS.toc)}</p>
        <ol>
          {HELP_SECTIONS.map((s) => (
            <li key={s.id}>
              <a href={`#${s.id}`}>{pick(s.title)}</a>
            </li>
          ))}
          <li>
            <a href="#faq">{pick(HELP_LABELS.faqTitle)}</a>
          </li>
        </ol>
      </nav>

      {HELP_SECTIONS.map((s) => (
        <section key={s.id} id={s.id} className="card help-section">
          <h2 className="help-h">{pick(s.title)}</h2>
          {s.lead !== undefined && <p className="help-lead">{pick(s.lead)}</p>}
          {s.blocks.map((block, i) => (
            <HelpBlockView key={i} block={block} pick={pick} />
          ))}
          <a className="help-top" href="#contents">
            {pick(HELP_LABELS.backToTop)}
          </a>
        </section>
      ))}

      <section id="faq" className="card help-section">
        <h2 className="help-h">{pick(HELP_LABELS.faqTitle)}</h2>
        <dl className="help-faq">
          {HELP_FAQ.map((item, i) => (
            <div key={i} className="help-faq-item">
              <dt>{pick(item.q)}</dt>
              <dd>{pick(item.a)}</dd>
            </div>
          ))}
        </dl>
        <a className="help-top" href="#contents">
          {pick(HELP_LABELS.backToTop)}
        </a>
      </section>
    </Stack>
  )
}

function HelpBlockView({ block, pick }: { block: HelpBlock; pick: (b: Bi) => string }) {
  switch (block.kind) {
    case 'p':
      return <p className="help-p">{pick(block.text)}</p>
    case 'steps':
      return (
        <ol className="help-steps">
          {block.items.map((it, i) => (
            <li key={i}>{pick(it)}</li>
          ))}
        </ol>
      )
    case 'list':
      return (
        <ul className="help-list">
          {block.items.map((it, i) => (
            <li key={i}>{pick(it)}</li>
          ))}
        </ul>
      )
    case 'note':
      return <p className="help-note">{pick(block.text)}</p>
    case 'defs':
      return (
        <dl className="help-defs">
          {block.items.map((it, i) => (
            <div key={i}>
              <dt>{pick(it.term)}</dt>
              <dd>{pick(it.desc)}</dd>
            </div>
          ))}
        </dl>
      )
  }
}
