import js from '@eslint/js'
import globals from 'globals'
import tseslint from 'typescript-eslint'

// Browser E2E specs run under the Playwright (node) runner, not the typed app
// project, so they use the untyped recommended rules.
//
// The specs live at the repo root (`tests/e2e/`, 判例4) and ESLint cannot lint
// files above its own config file. `npm run lint` therefore runs eslint twice:
// once here in frontend/ for the app, and once from the repo root, where
// ../eslint.config.js re-exports THIS array alone. Exporting it separately
// matters — re-exporting the whole config would let the typed app blocks match
// `tests/e2e/**` from the root and fail with "file was not found in any of the
// provided project(s)".
export const e2eConfig = tseslint.config({
  files: ['tests/e2e/**/*.ts', 'playwright.config.ts'],
  extends: [js.configs.recommended, ...tseslint.configs.recommended],
  languageOptions: {
    ecmaVersion: 2023,
    globals: { ...globals.node },
  },
})
