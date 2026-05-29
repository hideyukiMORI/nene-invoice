# NeNe Invoice — Admin Frontend

React + TypeScript admin SPA for NeNe Invoice.

Policy (binding): [`docs/development/frontend-standards.md`](../docs/development/frontend-standards.md)

## Commands

```bash
npm install
npm run dev          # Vite dev server; API paths proxied to the PHP app
npm run codegen      # regenerate src/shared/api/schema.gen.ts from ../docs/openapi/openapi.yaml
npm run storybook    # component catalog on :6006
npm run check        # type-check, lint, format, test, knip, build-storybook
npm run build        # production build → ../public_html/admin/
```

Set `VITE_API_TARGET` (e.g. `http://127.0.0.1:8123`) to point the dev proxy at a
non-default API port.

## Structure

- `src/app/` — providers, router, auth gate (fail-closed), error boundary
- `src/pages/` — route wiring
- `src/features/` — user workflows (hook + view)
- `src/entities/` — API resource slices (TanStack Query)
- `src/shared/` — api client, config, i18n (ja/en), theme tokens + UI primitives

Theme swap: edit the `@import` in `src/shared/ui/theme/active.css` only.
