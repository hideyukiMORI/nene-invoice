# Frontend Standards

NeNe Invoice's admin UI is a **React + TypeScript** client of the JSON API. It is
**not** the source of truth for schema, validation, tax rules, numbering, or
persistence — the PHP API (and `docs/explanation/accounting-compliance.md`) owns
those. The UI reflects API types and errors; it never replaces validation.

**Status:** Phase 2 — `frontend/` scaffold tracked by Issues. This document is the
binding policy; application code follows it as screens are added.

**Baseline & inheritance:** sibling product **NeNe Records** frontend conventions
(NENE2 frontend integration: directory layout, npm, lockfile, build output, dev
proxy). See `docs/inheritance-from-nene2.md`. Where this document and a sibling
differ, **this document wins for NeNe Invoice**.

**Enforcement level:** violations of placement, dependency direction, data flow,
security, naming, or testing rules **block merge to `main`**. No temporary
exceptions without an ADR.

---

## Product-specific rules (NeNe Invoice — read first)

These adjust the generic sibling baseline for this product. They are binding.

| Topic | Rule |
| --- | --- |
| **Locales** | **`ja` (primary) + `en` (secondary) only** — ADR 0005. Not multilingual. No other locale catalogs. |
| **Statutory content** | Qualified-invoice (適格請求書) document content is **always Japanese**, regardless of UI locale — it is a legal document. `en` applies to UI chrome and operator guides only. |
| **JSON shape** | API JSON is **snake_case**; the API client maps it to typed models **without renaming fields** in transport. DTO→model mapping happens in `entities/{r}/mapper.ts`. |
| **Money** | Always **integer cents** (smallest currency unit; JPY ¥1 = 1 cent). Never floats. Format for display only at the UI edge; never compute money in floats. |
| **Tax rate** | **Basis points** (`1000` = 10%, `800` = 8%). Allowed rates are API-authoritative; the UI offers the registered set but never invents rates. |
| **Auth token** | The API issues a **Bearer JWT** in the login response body. Storing it in `localStorage`/`sessionStorage` requires an **ADR** (XSS exfiltration risk — forbidden by default). Default: **in-memory** session (fail-closed; re-login on reload). A cookie-based session (httpOnly, SameSite) is the preferred long-term answer and needs its own ADR + API change. |
| **Public document pages** | Never embed an admin JWT in a public PDF/download page. Public download uses a scoped token per API policy. |
| **Build output** | Production admin bundle builds to **`public_html/admin/`** for **Tier A same-origin** hosting (ADR 0003). No cross-origin setup required of shared-hosting operators. |
| **RBAC in UI** | Hide/disable actions by API-exposed capability (`manage_billing`, `view_billing`, `manage_users`, …). UI gating is **UX only** — the API enforces authorization. |

---

## Principles

| Principle | Meaning |
| --- | --- |
| **API first** | OpenAPI (`docs/openapi/openapi.yaml`) is the contract; UI reflects API types and errors, never replaces validation. |
| **Unidirectional flow** | Data flows **down** (API → entity → feature → UI); events flow **up** (UI → feature hook → mutation → API). No sideways shortcuts. |
| **Strict TypeScript** | `strict` plus extra compiler guards; no untyped escape hatches by default. |
| **Fixed placement** | Models, enums, hooks, tests live in mandated paths — **placement violations block merge**. |
| **Explicit dependencies** | The import graph encodes architecture; ESLint enforces it. |
| **Loose coupling** | Layers talk through public surfaces (`index.ts`, props, hooks) — not internals. |
| **Secure by default** | Fail closed on auth errors; minimal trust of client input and third-party markup. |
| **Test by behavior** | Tests assert user-observable outcomes; MSW mirrors OpenAPI at boundaries. |
| **Theme by substitution** | All visual values live in theme token files; swapping the active theme restyles the whole app without touching components. |
| **No magic styling** | Margin, padding, color, typography, background never appear as raw literals outside the theme layer. |

---

## Stack

Adopt current stable majors at scaffold time; keep them current.

| Layer | Choice | Notes |
| --- | --- | --- |
| UI | **React** (latest stable) | Function components + hooks only — no class components |
| Language | **TypeScript** (latest stable) | All app source in `.ts` / `.tsx` |
| Bundler | **Vite** | Dev server + production build to `public_html/admin/` |
| Package manager | **npm** | Commit `frontend/package-lock.json`; CI uses `npm ci` |
| Node.js | **Active LTS (≥22)** | `engines` + `packageManager` in `frontend/package.json` |
| Routing | **React Router** | URL is shareable state |
| Server state | **TanStack Query v5** | Queries, mutations, cache, invalidation |
| Forms | **React Hook Form** + **Zod** | Client UX validation only — API authoritative |
| Lint | **ESLint** flat config: `typescript-eslint` strict-type-checked, `react-hooks`, `jsx-a11y`, `import/no-restricted-paths` | `--max-warnings 0` |
| Format | **Prettier** | Single formatter |
| Unit / integration | **Vitest** + **Testing Library** + **MSW** | jsdom |
| Dead code | **knip** | Fail CI on unused exports |
| Styling | **Tailwind CSS v4** | Semantic utilities mapped to CSS custom properties via `@theme` |
| Design tokens | **CSS custom properties** in `shared/ui/theme/` | Single source of truth for all visual values |
| Component catalog | **Storybook** | Required for `shared/ui` primitives + composed components |
| API types | **openapi-typescript** | Generate from `docs/openapi/openapi.yaml` into `shared/api/schema.gen.ts` |

Alternate UI framework, state library, CSS approach, or package manager requires an ADR.
Do **not** mix Tailwind with CSS Modules / CSS-in-JS without an ADR.

---

## Architecture

Strict layered architecture (adjacent to Feature-Sliced Design):
`app → pages → features → entities → shared`. It is **not** generic FSD — entity
modules and API boundaries are NeNe-specific and stricter.

### Layer responsibilities

| Layer | Owns | Must not own |
| --- | --- | --- |
| **`shared/`** | Transport, design tokens, pure utils, env, i18n | Routes, features, resource models, business workflows |
| **`entities/`** | One API resource: DTO mapping, query keys, TanStack hooks | JSX, cross-resource orchestration, feature copy |
| **`features/`** | User workflows composing entities + UI | Raw HTTP, DTO types, direct TanStack key strings |
| **`pages/`** | Route wiring, lazy loading, layout slots | Business rules, API calls |
| **`app/`** | Providers, router, global error boundary, auth gate shell | Feature-specific screens |

### Dependency direction (hard rule)

```
app → pages → features → entities → shared/api → API
                      ↘ shared/ui      entities → shared/lib
app → shared/api (providers), app → shared/ui
```

**No arrow may point upward** (e.g. `entities → features`, `shared → entities`).
Cross-feature sharing: extract to `entities/` (resource-level) or `shared/` (generic,
ADR) — **never** import `features/foo` from `features/bar`.

### Import matrix (mandatory)

| From ↓ / To → | `shared/ui` | `shared/api` | `shared/lib` | `entities/*` | `features/*` | `pages/*` | `app/*` |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `shared/ui` | ✓ internal | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ |
| `shared/api` | ✗ | ✓ internal | ✓ | ✗ | ✗ | ✗ | ✗ |
| `shared/lib` | ✗ | ✗ | ✓ internal | ✗ | ✗ | ✗ | ✗ |
| `entities/{r}` | ✗ | ✓ client only | ✓ | ✗ sibling | ✗ | ✗ | ✗ |
| `features/{f}` | ✓ | ✗ | ✓ | ✓ via `index.ts` | ✗ cross-feature | ✗ | ✗ |
| `pages/` | ✓ | ✗ | ✓ | ✗ direct | ✓ via `index.ts` | ✗ | ✗ |
| `app/` | ✓ | ✓ providers | ✓ | ✗ | ✗ | ✓ | ✓ internal |

### Public surfaces

Every `entities/{resource}/` and `features/{feature}/` exposes **`index.ts` only** to
upper layers. Internals (`mapper.ts`, `api-types.ts`, `ui/*.tsx`) are private to the slice.

---

## Repository layout

```text
frontend/
  package.json
  package-lock.json
  tsconfig.json / tsconfig.app.json / tsconfig.node.json
  vite.config.ts
  vitest.config.ts
  eslint.config.js
  .prettierrc
  knip.json
  index.html
  README.md                   # links to this document
  src/
    main.tsx
    app/
      providers.tsx           # QueryClientProvider, Router, theme, i18n, auth gate
      router.tsx
      root-error-boundary.tsx
      auth-gate.tsx           # fail-closed session check
    pages/
      login/
      invoices/
      …
    features/
      list-invoices/
        index.ts
        hooks/use-list-invoices.ts
        ui/ListInvoices.tsx
        ui/ListInvoices.test.tsx
      …
    entities/
      invoice/
        index.ts ids.ts enum.ts api-types.ts model.ts mapper.ts
        query-keys.ts queries.ts mutations.ts
        mapper.test.ts
      …
    shared/
      api/
        client.ts             # only place fetch() lives
        errors.ts             # Problem Details → AppError
        schema.gen.ts         # openapi-typescript output (generated; not edited)
      config/env.ts           # Zod-validated env (once)
      i18n/                   # ja + en only (ADR 0005)
        locales.ts messages/ja.ts messages/en.ts i18n-context.tsx
        use-translation.ts translate.ts
      lib/
      ui/
        theme/
          index.css           # Tailwind entry + imports active theme
          active.css          # @import './themes/default.css'
          themes/default.css  # complete theme (all tokens)
        primitives/           # Button, Input, Text, Stack, Spinner, …  (+ *.stories.tsx)
        components/           # composed UI (Dialog, ConfirmDialog, EmptyState, …)
        index.ts              # public barrel
  .storybook/
  tests/
    setup/                    # vitest setup, MSW server bootstrap
    msw/                      # MSW handlers mirroring OpenAPI
    factories/                # build models, not raw DTOs
    render/                   # renderWithProviders(), createTestQueryClient()
```

Built admin assets land in `public_html/admin/` (Tier A same-origin).

---

## Type and module placement (zero tolerance)

Enforced by ESLint `import/no-restricted-paths`; placement drift is rejected — no "fix later".

### Canonical entity tree

Each API resource → one `entities/{resource}/` folder (**kebab-case**, matches the
OpenAPI tag): `invoice`, `quote`, `client`, `payment`, `organization`, `user`,
`company-settings`, `audit-log`, `auth`.

```text
entities/invoice/
  index.ts          # ONLY public import surface
  ids.ts            # branded IDs (InvoiceId)
  enum.ts           # resource-scoped enums (InvoiceStatus)
  api-types.ts      # DTOs (aliases of schema.gen.ts where possible)
  model.ts          # UI read models
  mapper.ts         # DTO ↔ model (pure)
  query-keys.ts     # TanStack key factory
  queries.ts        # useQuery hooks
  mutations.ts      # useMutation hooks
  mapper.test.ts
```

### Placement matrix

| Artifact | Required path |
| --- | --- |
| OpenAPI-generated types | `shared/api/schema.gen.ts` |
| Hand-written / aliased API DTOs | `entities/{resource}/api-types.ts` |
| Branded IDs | `entities/{resource}/ids.ts` |
| Enums | `entities/{resource}/enum.ts` or `shared/lib/enums/{name}.ts` (ADR) |
| UI models | `entities/{resource}/model.ts` |
| Mappers | `entities/{resource}/mapper.ts` |
| Query keys | `entities/{resource}/query-keys.ts` |
| `useQuery` | `entities/{resource}/queries.ts` |
| `useMutation` | `entities/{resource}/mutations.ts` |
| HTTP transport (`fetch`) | `shared/api/client.ts` only |
| Problem Details mapping | `shared/api/errors.ts` |
| Component props | same `.tsx` file as component |
| Feature orchestration hooks | `features/{feature}/hooks/` |
| MSW handlers | `tests/msw/{resource}.ts` |
| Test factories | `tests/factories/{resource}.ts` |
| Design token CSS | `shared/ui/theme/themes/*.css` only |
| UI primitives | `shared/ui/primitives/` |
| Composed components | `shared/ui/components/` |
| Stories | colocated `shared/ui/**/*.stories.tsx` |

### Forbidden placements (automatic reject)

- DTOs / API shapes in `features/`, `pages/`, `shared/ui/`, or `.tsx` (except `*Props`)
- Models, enums, mappers outside `entities/{resource}/`
- TanStack logic outside `query-keys.ts` / `queries.ts` / `mutations.ts`
- `fetch` outside `shared/api/client.ts`
- `shared/api/schema.gen.ts` imported from any `.tsx`
- Deep entity imports from features (must go through `index.ts`)
- Root-level `src/types/`, `src/utils/` dumps

---

## Data flow

### Read path (server → UI)

```text
API JSON
  → shared/api/client.ts        (transport, auth header, status parse)
  → entities/{r}/api-types.ts   (wire shape; snake_case)
  → entities/{r}/mapper.ts      (map to model; format money/bps at edges)
  → entities/{r}/queries.ts     (TanStack Query cache)
  → features/{f}/hooks/*.ts     (compose, derive view state)
  → features/{f}/ui/*.tsx       (render props)
```

- **Mappers run inside entity hooks**, not in components.
- Components receive **`model` types** and plain callbacks — never raw `Response`, never DTOs.
- List screens use **stable query keys** from `query-keys.ts` only.

### Write path (UI → server)

```text
UI event → features/{f}/hooks → entities/{r}/mutations.ts → shared/api/client.ts → API
  → onSuccess: invalidate query-keys (explicit, colocated)
  → onError: Problem Details → AppError → UI feedback
```

- **Mutations live in `mutations.ts`**; features call exported hooks, not inline `useMutation`.
- Optimistic updates require rollback on failure and a test proving rollback.

### URL and shareable state

| State | Location |
| --- | --- |
| Resource id in detail view | route param (`/invoices/:id`) |
| Filters, sort, page | `searchParams` (serializable) |
| Modal open, tab | local `useState` in feature |
| Server data | TanStack Query cache — **not** duplicated in a global store |

### Four explicit UI states (every data screen)

**Loading** (skeleton/spinner) · **Empty** (intentional copy) · **Error** (safe message
+ retry; Problem Details `type` logged dev-only) · **Success**.

---

## Design patterns

| Pattern | Where | Purpose |
| --- | --- | --- |
| **Hook + View** | `features/{f}/hooks` + `ui` | Logic in hooks; UI prop-driven |
| **Entity module** | `entities/{r}/` | One resource: types, map, cache, API |
| **Query key factory** | `query-keys.ts` | Typed hierarchical keys — no string literals in features |
| **Mapper purity** | `mapper.ts` | Pure, unit-tested, no side effects |
| **Barrel public API** | `index.ts` | Encapsulation + ESLint boundary target |
| **Problem Details mapping** | `shared/api/errors.ts` | Single parse path for `application/problem+json` |
| **Provider stack** | `app/providers.tsx` | One composition root |
| **Route error boundary** | `app/root-error-boundary.tsx` | Safe fallback on render error |
| **Fail-closed auth gate** | `app/auth-gate.tsx` | 401 → login; 403 → forbidden |

### Forms

- React Hook Form + Zod resolver for UX validation (required, format hints).
- Submit calls an **entity mutation hook** — not inline fetch.
- Server validation errors map from Problem Details `errors` to form field errors when present.
- Destructive submits require an explicit confirm dialog from `shared/ui`.

### Forbidden anti-patterns

`useEffect`+`fetch` for server data · prop-drilling server data 3+ layers · global
pub/sub · storing API responses in `useState` · class components · **default exports** ·
business rules in `shared/ui` · string query keys in features · `dangerouslySetInnerHTML`
without policy · auth token in `localStorage` without ADR · raw color/spacing/type
literals in components · Tailwind arbitrary values (`p-[13px]`) · inline `style` with
design literals · feature-local `<button>`/`<input>` styling.

---

## TypeScript strictness

`tsconfig.app.json` minimum: `strict`, `noUncheckedIndexedAccess`, `noImplicitOverride`,
`exactOptionalPropertyTypes`, `verbatimModuleSyntax`, `noUnusedLocals`,
`noUnusedParameters`, `noFallthroughCasesInSwitch`, `forceConsistentCasingInFileNames`,
`isolatedModules`, `jsx: react-jsx`, `moduleResolution: bundler`, `noEmit`.

- **`any` forbidden** — use `unknown` and narrow.
- `@ts-expect-error` / `@ts-ignore` need an Issue/ADR id in the comment.
- No `!` non-null assertion without an invariant comment.
- `interface` for component props; `type` for unions/mapped types.
- `satisfies` for const config (query defaults, route maps).
- **Branded IDs** in `ids.ts` — no bare `string` for resource ids across layers.
- Exhaustive `switch` on discriminated unions.
- Env validated once in `shared/config/env.ts` (Zod).

---

## Design system and theming (zero tolerance)

All visual values live in **`shared/ui/theme/`**. Components never hard-code margin,
padding, color, font, background, radius, shadow, or z-index.

**Theme swap rule:** replacing the active theme changes **every** visual aspect without
editing component source. `themes/{name}.css` holds the full token set; `active.css` is a
one-line `@import`. `app/providers.tsx` imports `theme/index.css` once. Features/pages
**must not** import theme CSS directly.

Token categories (full set required per theme file): Color (`color-surface`,
`color-text-primary`, `color-border`, `color-accent`, `color-danger`, `color-focus-ring`,
…), Spacing (`spacing-inline-*`, `spacing-stack-*`), Typography (`font-family-sans`,
`font-size-body`, …), Radius, Shadow, Border, Motion, Z-index.

Consume via **Tailwind semantic utilities only** (`bg-surface`, `text-primary`,
`p-inline-md`, `rounded-md`). **No arbitrary values** (`[...]`) and **no hex/rgb/px
literals** in `.ts`/`.tsx` outside the theme layer.

`shared/ui` layering: `theme/` (no React) → `primitives/` → `components/` → `index.ts`.
Features import the `shared/ui` barrel only.

---

## Storybook

Required for every exported `shared/ui` primitive and composed component. Stories
**forbidden** under `features/`, `pages/`, `entities/`. Each story documents the
**In / Out / Does not** contract in a header comment and covers default + each variant +
disabled. Colocate `Button.tsx` + `Button.stories.tsx`. `npm run build-storybook` in CI.

---

## API and data access

```text
UI → feature hook → entity query/mutation → shared/api/client → API
```

### HTTP client (`shared/api/client.ts`)

- Single `apiClient` with typed methods (`get`, `post`, `patch`, `delete`).
- Attaches the bearer token from the in-memory session (see auth-token rule); fail-closed.
- Parses JSON; throws **`AppError`** from Problem Details on 4xx/5xx.
- **No domain logic** — transport only.

### TanStack Query (`app/providers.tsx`)

```ts
new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      retry: (n, e) => n < 2 && e instanceof AppError && e.isRetryable,
      refetchOnWindowFocus: import.meta.env.PROD,
    },
    mutations: { retry: false },
  },
})
```

Per entity: hooks with explicit return types (`UseQueryResult<Invoice, AppError>`);
`queryFn` calls the mapper before returning to cache; document non-default `staleTime`.

---

## State management

| State | Tool | Location |
| --- | --- | --- |
| Remote server data | TanStack Query | `entities/*/queries.ts` |
| Writes | TanStack mutations | `entities/*/mutations.ts` |
| URL / shareable | React Router | `pages/` + feature hooks reading `searchParams` |
| Form draft | React Hook Form | feature ui + hooks |
| Ephemeral UI | `useState` | feature ui |
| Auth session | Context in `app/` only | minimal — in-memory token + user from API |

No Redux / Zustand / Jotai without an ADR.

---

## Security

The browser is a **hostile context**.

| Topic | Rule |
| --- | --- |
| **Secrets** | Never in repo. Only public `VITE_*` in frontend env. |
| **Auth token** | In-memory by default; `localStorage`/`sessionStorage` or cookie session needs an ADR. |
| **XSS** | No `dangerouslySetInnerHTML` without DOMPurify + Issue. |
| **Links** | `rel="noopener noreferrer"` on `target="_blank"`. |
| **Open redirects** | Validate post-login redirect against an allowlist. |
| **Dependencies** | `npm audit` in CI; block high/critical on `main`. Lockfile required. |
| **PII / money in logs** | Never log tokens, passwords, or full Problem Details in production. |
| **RBAC UI** | Hide/disable by API capability; UI gating is UX only — API enforces. |
| **Fail closed** | 401 → login; 403 → forbidden; never silent unauthenticated mutations. |

---

## Internationalisation (ja + en only — ADR 0005)

| Module | Purpose |
| --- | --- |
| `shared/i18n/locales.ts` | `SupportedLocale = 'ja' \| 'en'` + `LocaleMeta` |
| `shared/i18n/messages/ja.ts` | Source-of-truth catalog (primary locale) |
| `shared/i18n/messages/en.ts` | Secondary catalog; missing keys fall back to `ja` |
| `shared/i18n/i18n-context.tsx` | `I18nProvider` — detection, persistence, context |
| `shared/i18n/use-translation.ts` | `useTranslation()` |
| `shared/i18n/translate.ts` | pure `translate()` + `MessageKey` |

Rules: **no hardcoded user-facing strings** → `t('admin.invoices.title')`; key naming
`admin.{feature}.{element}` / `common.{element}`; **`ja` is authoritative**, `en` is
`Partial`; detection order `localStorage['nene-locale']` → `navigator.language` → `ja`.
**Statutory invoice content stays Japanese irrespective of UI locale.** Do not add a
third locale without superseding ADR 0005.

---

## Testing

| Level | Tool | Required when |
| --- | --- | --- |
| Unit | Vitest | `mapper.ts`, `query-keys.ts`, pure `lib/` — every entity |
| Integration | Vitest + Testing Library + MSW | every feature PR |
| Contract | MSW vs OpenAPI | endpoint touched |

Rules: query by role / label / accessible name (not class/`data-testid` unless no a11y
hook); `userEvent.setup()`; wrap with `createTestQueryClient()` (retries off); MSW shapes
match OpenAPI; mock only the API boundary; no full-page snapshots; bug fixes ship a
regression test.

**Before merge:** entity → mapper tests (+ query-key tests if non-trivial) + a hook test
with MSW for the primary query/mutation. **Every new feature ships ≥1 feature-hook test**
(render the hook against MSW; assert the primary query loads and each mutation drives its
observable outcome). New `use-{feature}` hooks without a colocated `*.test.ts(x)` block merge.

---

## Accessibility / performance / observability

- **WCAG 2.2 AA**; focus management on route change and modal open/close;
  `eslint-plugin-jsx-a11y` errors fail CI; form errors via `aria-describedby`.
- Route-level code splitting (`React.lazy`); virtualize lists > 100 rows.
- Dev-only structured logging behind `import.meta.env.DEV`; TanStack Query Devtools dev only.

---

## Commands and CI

```bash
npm ci --prefix frontend
npm run dev --prefix frontend          # Vite dev server; API proxied to the PHP app
npm run codegen --prefix frontend      # regenerate shared/api/schema.gen.ts from OpenAPI
npm run check --prefix frontend        # type-check + lint + format + test + knip + build-storybook
npm run build --prefix frontend        # production build → public_html/admin/
```

CI on frontend changes: `npm ci` → `npm run check` → `npm audit --audit-level=high`.

ESLint encodes the boundaries:

- `features/**` → no `shared/api/schema.gen.ts`, no `shared/api`, no deep `entities/**` (only `index.ts`)
- `pages/**` → no `shared/api`, no deep `entities/**`
- `shared/ui/**` → no `entities/**`, `features/**`, `shared/api`
- `entities/*/**` → no sibling `entities/*/**`
- forbid Tailwind arbitrary-value `className` literals outside `shared/ui/theme/`

---

## Non-goals

- Duplicating API validation / tax / numbering rules in the browser as source of truth.
- Locales beyond `ja` + `en` (ADR 0005).
- Hard-coded visual values outside `shared/ui/theme/`.
- DB or MCP access from the browser.
- Committing `node_modules/` or generated assets to source (built `public_html/admin/` is a release artifact).
- Alternate UI stack without an ADR.

---

## Related documents

- Self-review: `docs/review/frontend.md`
- API contract: `docs/openapi/openapi.yaml`, `docs/review/openapi-contract.md`
- Naming: `docs/development/naming-conventions.md`
- Terminology (identifiers): `docs/explanation/terminology.md`
- Compliance (binding): `docs/explanation/accounting-compliance.md`
- Deployment tiers: `docs/adr/0003-dual-deployment-tiers.md`
- Localization: `docs/adr/0005-bilingual-ja-en-scope.md`
