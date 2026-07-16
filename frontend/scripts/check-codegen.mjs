// Freshness gate for the generated OpenAPI types (Issue #668).
// Regenerates schema.gen.ts into a temp file and fails if it differs from the
// committed one — i.e. someone edited docs/openapi/openapi.yaml without running:
//   npm run codegen
// A-5 ("generated types are actually imported") only proves the file is wired
// up, not that it is current; #626 drifted for a month under a green CI because
// nothing compared the output. Mirrors the regen-diff the standards apply to
// themegen (03:575): deterministic regeneration, compared, mismatch = FAIL.
// Local and hermetic — no network, so it is safe to run per-PR in `check`.
import { execFileSync } from 'node:child_process'
import { mkdtempSync, readFileSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const dirname = path.dirname(fileURLToPath(import.meta.url))
const SPEC = path.resolve(dirname, '../../docs/openapi/openapi.yaml')
const COMMITTED = path.resolve(dirname, '../src/shared/api/schema.gen.ts')

const tmp = mkdtempSync(path.join(tmpdir(), 'nene-invoice-codegen-'))
const fresh = path.join(tmp, 'schema.gen.ts')

try {
  execFileSync('openapi-typescript', [SPEC, '-o', fresh], { stdio: 'pipe' })

  if (readFileSync(fresh, 'utf8') !== readFileSync(COMMITTED, 'utf8')) {
    console.error(
      'schema.gen.ts is stale: docs/openapi/openapi.yaml has changed since it was generated.\n' +
        'Run `npm run codegen` and commit the result.',
    )
    process.exit(1)
  }
} finally {
  rmSync(tmp, { recursive: true, force: true })
}
