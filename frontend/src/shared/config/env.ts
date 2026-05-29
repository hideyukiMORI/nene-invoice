import { z } from 'zod'

/**
 * Validated client environment — the single place `import.meta.env` is read.
 * Only public `VITE_*` values plus Vite's build flags are exposed.
 */
const envSchema = z.object({
  DEV: z.boolean(),
  PROD: z.boolean(),
})

const parsed = envSchema.parse({
  DEV: import.meta.env.DEV,
  PROD: import.meta.env.PROD,
})

export const env = {
  isDev: parsed.DEV,
  isProd: parsed.PROD,
} as const
