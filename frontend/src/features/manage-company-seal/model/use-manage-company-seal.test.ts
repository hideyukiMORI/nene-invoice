import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { beforeEach, describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useManageCompanySeal } from './use-manage-company-seal'

const SEAL_URL = '/admin/company-settings/seal'

/** A valid PNG-typed file whose bytes have a known base64 encoding. */
function pngFile(bytes = new Uint8Array([137, 80, 78, 71, 13, 10, 26, 10])): {
  file: File
  base64: string
} {
  return {
    file: new File([bytes], 'seal.png', { type: 'image/png' }),
    base64: Buffer.from(bytes).toString('base64'),
  }
}

describe('useManageCompanySeal', () => {
  beforeEach(() => {
    // Default: no seal set. Individual tests override as needed.
    server.use(http.get(SEAL_URL, () => HttpResponse.json({ has_seal: false, image_base64: null })))
  })

  it('rejects a non-PNG file without uploading', () => {
    let uploaded = false
    server.use(
      http.put(SEAL_URL, () => {
        uploaded = true
        return HttpResponse.json({ has_seal: true })
      }),
    )

    const { result } = renderHookWithProviders(() => useManageCompanySeal())

    act(() => {
      result.current.onSelectFile(new File(['x'], 'seal.jpg', { type: 'image/jpeg' }))
    })

    expect(result.current.errorMessage).not.toBeNull()
    expect(uploaded).toBe(false)
  })

  it('rejects a file over the 512 KB cap without uploading', () => {
    let uploaded = false
    server.use(
      http.put(SEAL_URL, () => {
        uploaded = true
        return HttpResponse.json({ has_seal: true })
      }),
    )

    const oversized = new File([new Uint8Array(512 * 1024 + 1)], 'big.png', { type: 'image/png' })
    const { result } = renderHookWithProviders(() => useManageCompanySeal())

    act(() => {
      result.current.onSelectFile(oversized)
    })

    expect(result.current.errorMessage).not.toBeNull()
    expect(uploaded).toBe(false)
  })

  it('reads a valid PNG and uploads its base64 payload', async () => {
    const { file, base64 } = pngFile()
    let captured: unknown
    server.use(
      http.put(SEAL_URL, async ({ request }) => {
        captured = await request.json()
        return HttpResponse.json({ has_seal: true })
      }),
    )

    const { result } = renderHookWithProviders(() => useManageCompanySeal())

    act(() => {
      result.current.onSelectFile(file)
    })

    await waitFor(() => {
      expect(captured).toEqual({ image_base64: base64 })
    })
    expect(result.current.errorMessage).toBeNull()
  })

  it('derives the preview data URI and hasSeal from the query', async () => {
    server.use(
      http.get(SEAL_URL, () => HttpResponse.json({ has_seal: true, image_base64: 'QUJD' })),
    )

    const { result } = renderHookWithProviders(() => useManageCompanySeal())

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false)
    })
    expect(result.current.hasSeal).toBe(true)
    expect(result.current.previewDataUri).toBe('data:image/png;base64,QUJD')
  })

  it('has no preview when no seal is set', async () => {
    const { result } = renderHookWithProviders(() => useManageCompanySeal())

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false)
    })
    expect(result.current.hasSeal).toBe(false)
    expect(result.current.previewDataUri).toBeNull()
  })

  it('calls DELETE on remove', async () => {
    let removed = false
    server.use(
      http.delete(SEAL_URL, () => {
        removed = true
        return HttpResponse.json({ has_seal: false })
      }),
    )

    const { result } = renderHookWithProviders(() => useManageCompanySeal())

    act(() => {
      result.current.onRemove()
    })

    await waitFor(() => {
      expect(removed).toBe(true)
    })
  })
})
