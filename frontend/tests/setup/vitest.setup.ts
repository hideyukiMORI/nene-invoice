import '@testing-library/jest-dom/vitest'
// vitest.config.ts uses `globals: false`, so React Testing Library cannot register its own
// auto-cleanup (it only does so when a global `afterEach` exists). Manual cleanup is therefore
// required here — removing it leaks the DOM between tests (measured: 11 files / 42 tests fail).
// The matching no-manual-cleanup exception is registered in eslint.config.js.
import { cleanup } from '@testing-library/react'
import { afterAll, afterEach, beforeAll } from 'vitest'
import { setAuthToken } from '@/shared/api/client'
import { server } from '@tests/msw/server'

// jsdom does not implement these browser APIs needed for blob downloads.
global.URL.createObjectURL = () => 'blob:mock-url'
global.URL.revokeObjectURL = () => {}

beforeAll(() => {
  server.listen({ onUnhandledRequest: 'error' })
})

afterEach(() => {
  cleanup()
  server.resetHandlers()
  setAuthToken(null)
})

afterAll(() => {
  server.close()
})
