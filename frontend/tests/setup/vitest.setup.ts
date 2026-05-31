import '@testing-library/jest-dom/vitest'
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
