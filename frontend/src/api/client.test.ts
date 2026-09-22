import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api, setCsrfToken } from './client'

describe('api client', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    setCsrfToken('csrf-abc')
  })

  it('ajoute le header CSRF sur POST', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => ({
        ok: true,
        status: 200,
        text: async () => JSON.stringify({ ok: true }),
      })),
    )

    await api('/clients', { method: 'POST', body: JSON.stringify({ name: 'X' }) })

    expect(fetch).toHaveBeenCalledWith(
      '/api/clients',
      expect.objectContaining({
        method: 'POST',
        credentials: 'include',
        headers: expect.any(Headers),
      }),
    )
    const headers = (fetch as unknown as ReturnType<typeof vi.fn>).mock.calls[0][1]
      .headers as Headers
    expect(headers.get('X-CSRF-Token')).toBe('csrf-abc')
  })

  it('lève une erreur avec le message API', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => ({
        ok: false,
        status: 400,
        text: async () => JSON.stringify({ error: 'Client introuvable' }),
      })),
    )

    await expect(api('/clients/9')).rejects.toThrow('Client introuvable')
  })

  it('ne met pas CSRF sur GET', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => ({
        ok: true,
        status: 200,
        text: async () => JSON.stringify({ items: [] }),
      })),
    )
    await api('/clients')
    const headers = (fetch as unknown as ReturnType<typeof vi.fn>).mock.calls[0][1]
      .headers as Headers
    expect(headers.get('X-CSRF-Token')).toBeNull()
  })
})
