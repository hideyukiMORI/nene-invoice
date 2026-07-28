# Dependency vulnerability gate (frontend)

Every PR runs a dependency audit as a **merge gate**. This document says what the gate is,
how an exception is granted, and what is currently excepted.

- Config: [`frontend/audit-ci.jsonc`](../../frontend/audit-ci.jsonc) (the file itself carries
  the reasoning for each entry — keep the two in sync)
- Command: `npm run audit --prefix frontend`
- CI: the `Audit (fail on high/critical)` step of `Frontend CI` (required check `frontend-check`)

## The gate

`audit-ci` fails the build on any **high** or **critical** advisory that is not explicitly
allowlisted. Moderate and below do not fail (they are still reported).

We use `audit-ci` rather than bare `npm audit --audit-level=high` for one reason: **`npm audit`
has no way to record a reasoned exception.** Without one, the only ways past a
not-yet-fixable advisory are to lower the severity threshold or drop the step — both of which
blind the gate to _everything_, not just the advisory in question.

## Rules for an exception

1. **Per advisory id, never per severity.** Allowlist `GHSA-…`; do not raise `--audit-level`
   and do not set `high: false`. A new advisory must still fail the build the day it lands.
2. **The reason must be measured, not assumed.** State why the vulnerable code path does not
   exist _in this codebase_, and how that was checked (a grep, a build artifact, a config).
   "We probably don't use that" is not a reason.
3. **Every entry has an expiry** and a named condition that removes it (an upgrade wave, an
   upstream fix). An expired entry is a task — re-argue it in a PR; do not extend it by reflex.
4. **Prefer the fix.** If a patched version exists in a range we can take, take it. An
   exception is only for "no fix exists that we can adopt".

## Current exceptions

| Advisory                                                                 | Package                                               | Why it does not apply here                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | Expires        |
| ------------------------------------------------------------------------ | ----------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------- |
| [GHSA-qwww-vcr4-c8h2](https://github.com/advisories/GHSA-qwww-vcr4-c8h2) | `react-router` (7.12.0–8.2.0)                         | The admin UI is a **static SPA built by Vite** (`vite.config.ts` → `outDir: ../public_html/admin`, `base: './'`) and served as files by the PHP app shell. `src/app/router.tsx` is the only router and uses `createBrowserRouter` with **element-only routes** — **no RSC mode, no server components, no route `action`/`loader`, no `@react-router/dev` runtime**. The advisory's attack path (a server executing a route action before returning 400) has no counterpart in a client-only bundle. Measured 2026-07-29: no `action:` / `loader:` route keys in `src/`, all 44 react-router imports are from `react-router-dom`, and the tree contains no `react-router/rsc` / `createStaticHandler` / `StaticRouterProvider` / `@react-router/dev`. | **2026-08-31** |
| [GHSA-mh99-v99m-4gvg](https://github.com/advisories/GHSA-mh99-v99m-4gvg) | `brace-expansion` (≤ 5.0.7), **1.x / 2.x paths only** | The 5.x path **is** fixed (override `^5.0.8`); this entry covers only the copies that cannot take the fix. Measured 2026-07-29: `npm ls brace-expansion --omit=dev --all` is **empty** — the package is dev-only, in no shipped bundle and on no request path. The vulnerable copies are `1.1.16` (via `minimatch@3` ← eslint / eslintrc / config-array / plugin-import / plugin-jsx-a11y) and `2.1.3` (via `glob`, `@redocly/openapi-core`) — lint and codegen tooling running over our own committed globs, while the advisory needs an attacker-supplied brace pattern.                                                                                                                                                                           | **2026-08-31** |

There is **no fix available in the 7.x line** for `react-router`: `react-router-dom` ends at
7.18.1, and the fix lands in `react-router` v8 (≥ 8.2.1) — a different package and a breaking
upgrade. The exception is removed by the **react-router v8 migration wave** (bundled with the
NENE2 RR8 re-evaluation).

For `brace-expansion` there is **no patched 1.x or 2.x release at all**, and forcing 5.0.8 into
those branches breaks the consumers — measured: brace-expansion 5 exports a _named_ `expand`
while `minimatch@3` calls the module itself, so `npm run lint` dies with
`TypeError: expand is not a function`. Overriding `minimatch@3` upward fails the same way
(`eslint-plugin-import` / `eslint-plugin-jsx-a11y` do `_interopRequireDefault(require('minimatch'))`
and call it; `minimatch` ≥ 9 has no callable default export). The exception is removed by the
**eslint 10 / plugin major wave**, which moves the chain to `minimatch@10` → `brace-expansion@^5`.

## What was fixed rather than excepted (2026-07-29)

The gate went red on six advisories at once. Five of them had a version we could take, so we
took it (rule 4); one had no adoptable fix, and one was fixed only on part of its tree:

| Advisory                                                                      | Package                        | Action                                                                      |
| ----------------------------------------------------------------------------- | ------------------------------ | --------------------------------------------------------------------------- |
| GHSA-wrjc-x8rr-h8h6 (open redirect via backslash in `<Link>` / `useNavigate`) | `react-router`                 | fixed in 7.18.0 → floor raised to `react-router-dom` `^7.18.1`              |
| GHSA-h8fp-f39c-q6mh (RSCErrorHandler XSS)                                     | `react-router`                 | same bump                                                                   |
| GHSA-337j-9hxr-rhxg (`deserializeErrors()` constructor injection)             | `react-router`                 | same bump                                                                   |
| GHSA-chx6-hx7r-mcp5 (route-matching DoS)                                      | `react-router`                 | same bump                                                                   |
| GHSA-r28c-9q8g-f849 (`sourceMappingURL` path traversal)                       | `postcss` (transitive)         | override `^8.5.24`                                                          |
| GHSA-mh99-v99m-4gvg (unbounded expansion DoS)                                 | `brace-expansion` (transitive) | override `brace-expansion@5` → `^5.0.8`; 1.x/2.x paths excepted (see above) |

Note on `react-router-dom`: the declared range is the **floor** (`^7.18.1`), not just whatever
the lockfile happens to resolve. A caret range that merely _happened_ to resolve to a patched
version can silently regress on a fresh install; the floor cannot.

Note on pins: pinning a version to dodge an advisory is a **time-limited** measure, not a fix —
the pinned version can itself fall inside a later advisory. That is exactly what happened here:
the `brace-expansion` pins added in #719 (`1.1.16` / `2.1.2` / `5.0.7`) became the vulnerable
versions on 2026-07-29. Prefer ranges, and revisit pins.

## Fleet note

The fleet reference implementation is **contact** (contact #524, 施主 GO 2026-07-29); this repo
mirrors it (#731). The RSC-unused claim above was **re-measured in this tree** before the
allowlist entry was copied — copying an exception without re-measuring is exactly the failure
mode the rules exist to prevent.

The re-measuring paid off: invoice needed a **second** entry that contact does not have.
contact's blanket `"brace-expansion": "^5.0.8"` override works there; here it breaks `npm run
lint`, because this tree still carries a `minimatch@3` chain (eslint 9 core + `eslint-plugin-import`

- `eslint-plugin-jsx-a11y`). Sibling products copying this setup should run `npm run lint` after
  the override, not just `npm run audit`.

## Related

- [`coding-standards.md`](./coding-standards.md) — the wider merge-gate set
- [`frontend-standards.md`](./frontend-standards.md) — frontend conventions
