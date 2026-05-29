# Frontend Self-Review

Use for any React / TypeScript admin UI change. Source policy:
`docs/development/frontend-standards.md` (binding). Items here are
**merge-blocking** unless an ADR records an exception.

## Placement & boundaries

- [ ] New API resource lives in `entities/{resource}/` (kebab-case = OpenAPI tag) with the canonical files.
- [ ] Upper layers import entities **only** via `entities/{resource}/index.ts`.
- [ ] No `fetch` outside `shared/api/client.ts`.
- [ ] No DTO / API shapes in `features/`, `pages/`, `shared/ui/`, or `.tsx` (except `*Props`).
- [ ] No sibling-entity or cross-feature imports.
- [ ] `npm run lint` (import boundaries) passes with `--max-warnings 0`.

## Data flow

- [ ] Mapper runs in the entity hook; components receive `model` types + callbacks, never DTOs/`Response`.
- [ ] Queries use stable keys from `query-keys.ts`; mutations live in `mutations.ts` with explicit invalidation.
- [ ] Every data screen renders Loading / Empty / Error / Success.

## TypeScript

- [ ] `strict` + extra guards pass (`npm run type-check`); no `any`.
- [ ] Branded IDs for resource ids; `interface` for props, `type` for unions.
- [ ] `@ts-expect-error` / `!` carry an Issue/ADR id or invariant comment.

## Product rules (NeNe Invoice)

- [ ] UI strings in `ja`/`en` catalogs only — no hardcoded strings, no third locale (ADR 0005).
- [ ] Statutory invoice content renders in Japanese regardless of UI locale.
- [ ] Money handled as integer cents; tax as basis points; no float math; formatted only at the UI edge.
- [ ] API JSON consumed as snake_case; DTO→model mapping in `mapper.ts` (no transport renaming).
- [ ] Admin JWT **not** in `localStorage`/`sessionStorage` without an ADR (default in-memory); never in public document pages.
- [ ] Actions hidden/disabled by API capability (UX only; API still enforces).

## Design system

- [ ] No raw color/spacing/typography/px literals or Tailwind arbitrary values outside `shared/ui/theme/`.
- [ ] Components use `shared/ui` primitives via the barrel; no feature-local styled `<button>`/`<input>`.
- [ ] New/changed `shared/ui` primitive or composed component has a colocated story (In/Out/Does-not contract).

## Testing

- [ ] Entity: mapper tests (+ query-key tests if non-trivial) + a hook test with MSW for the primary query/mutation.
- [ ] Feature: ≥1 feature-hook test against MSW; UI test for happy path + primary Problem Details error.
- [ ] Queries by role/label/accessible name; MSW shapes match OpenAPI.
- [ ] `npm run test` green.

## Security & a11y

- [ ] Fail closed on 401 → login, 403 → forbidden.
- [ ] `target="_blank"` carries `rel="noopener noreferrer"`; no `dangerouslySetInnerHTML` without policy.
- [ ] `eslint-plugin-jsx-a11y` clean; form errors linked via `aria-describedby`.

## Gate

- [ ] `npm run check --prefix frontend` passes (type-check + lint + format + test + knip + build-storybook).
- [ ] `npm audit --audit-level=high` has no high/critical.
