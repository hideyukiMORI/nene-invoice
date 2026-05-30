import js from '@eslint/js'
import eslintConfigPrettier from 'eslint-config-prettier'
import importPlugin from 'eslint-plugin-import'
import jsxA11y from 'eslint-plugin-jsx-a11y'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import storybook from 'eslint-plugin-storybook'
import globals from 'globals'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import tseslint from 'typescript-eslint'

const dirname = path.dirname(fileURLToPath(import.meta.url))

// Entity internals are private to the slice — only entities/{r}/index.ts is public.
const entityInternalFiles = [
  './src/entities/*/api-types.ts',
  './src/entities/*/mapper.ts',
  './src/entities/*/queries.ts',
  './src/entities/*/mutations.ts',
  './src/entities/*/query-keys.ts',
  './src/entities/*/ids.ts',
  './src/entities/*/model.ts',
  './src/entities/*/enum.ts',
  './src/entities/*/session.ts',
]

const importZones = [
  { target: './src/features', from: entityInternalFiles },
  { target: './src/features', from: './src/shared/api' },
  { target: './src/pages', from: entityInternalFiles },
  { target: './src/pages', from: './src/shared/api' },
  { target: './src/shared/ui', from: './src/entities' },
  { target: './src/shared/ui', from: './src/features' },
  { target: './src/shared/ui', from: './src/shared/api' },
]

export default tseslint.config(
  {
    ignores: [
      'dist',
      'storybook-static',
      'node_modules',
      'coverage',
      'src/shared/api/schema.gen.ts',
      'public/mockServiceWorker.js',
    ],
  },
  {
    extends: [js.configs.recommended, ...tseslint.configs.strictTypeChecked],
    files: ['src/**/*.{ts,tsx}', 'tests/**/*.{ts,tsx}'],
    languageOptions: {
      ecmaVersion: 2023,
      globals: globals.browser,
      parserOptions: {
        project: ['./tsconfig.app.json'],
        tsconfigRootDir: dirname,
      },
    },
    plugins: {
      'react-hooks': reactHooks,
      'react-refresh': reactRefresh,
      'jsx-a11y': jsxA11y,
      import: importPlugin,
    },
    settings: {
      'import/resolver': {
        typescript: { project: './tsconfig.app.json' },
      },
    },
    rules: {
      ...reactHooks.configs.recommended.rules,
      'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],
      ...jsxA11y.configs.recommended.rules,
      // Numbers in template literals are safe (and React Hook Form's typed field
      // paths require a numeric index, e.g. `line_items.${index}.description`).
      '@typescript-eslint/restrict-template-expressions': ['error', { allowNumber: true }],
      'import/no-restricted-paths': ['error', { zones: importZones }],
      'no-restricted-syntax': [
        'error',
        {
          selector: 'JSXAttribute[name.name="className"] Literal[value=/\\[.*\\]/]',
          message: 'Tailwind arbitrary values are forbidden outside shared/ui/theme.',
        },
      ],
    },
  },
  {
    // Tests and test helpers may inspect internals across layer boundaries.
    files: ['**/*.test.ts', '**/*.test.tsx', 'tests/**/*.{ts,tsx}'],
    rules: {
      '@typescript-eslint/no-unsafe-call': 'off',
      '@typescript-eslint/no-unsafe-member-access': 'off',
      '@typescript-eslint/no-unsafe-argument': 'off',
      '@typescript-eslint/no-unsafe-assignment': 'off',
      'import/no-restricted-paths': 'off',
      'react-refresh/only-export-components': 'off',
    },
  },
  {
    files: ['.storybook/**/*.{ts,tsx}', 'vite.config.ts', 'vitest.config.ts'],
    extends: [js.configs.recommended, ...tseslint.configs.recommended],
    languageOptions: {
      ecmaVersion: 2023,
      globals: { ...globals.browser, ...globals.node },
    },
  },
  {
    // Browser E2E specs run under the Playwright (node) runner, not the typed
    // app project, so they use the untyped recommended rules.
    files: ['e2e/**/*.ts', 'playwright.config.ts'],
    extends: [js.configs.recommended, ...tseslint.configs.recommended],
    languageOptions: {
      ecmaVersion: 2023,
      globals: { ...globals.node },
    },
  },
  ...storybook.configs['flat/recommended'],
  eslintConfigPrettier,
)
