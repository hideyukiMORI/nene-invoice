// Repo-root ESLint config — for the Playwright specs only.
//
// The app's config lives in frontend/ (that is where the plugins and their node
// deps resolve from). This file exists because the E2E specs sit at the repo
// root (`tests/e2e/`, 判例4) and ESLint refuses to lint files located above its
// config file: without a config here, moving the specs out of frontend/ would
// have silently dropped 24 spec files from the lint gate.
//
// It re-exports only the `e2eConfig` array, not the whole frontend config — the
// typed `tests/**` block there would otherwise match `tests/e2e/**` from this
// base path and fail parsing (the specs are not in tsconfig.app.json).
//
// Used by `npm run lint --prefix frontend`, which runs eslint twice:
//   eslint .                         (app sources; config = frontend/eslint.config.js)
//   eslint tests/e2e  from the root  (the specs; config = this file)
export { e2eConfig as default } from './frontend/eslint.config.js'
