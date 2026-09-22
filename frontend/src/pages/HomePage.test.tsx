import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ThemeProvider } from '@mui/material/styles'
import CssBaseline from '@mui/material/CssBaseline'
import HomePage from './HomePage'
import theme from '../theme'

describe('HomePage', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('liste les documents et n’affiche pas de paiement', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => ({
        ok: true,
        status: 200,
        text: async () =>
          JSON.stringify({
            items: [
              {
                id: 1,
                doc_type: 'quote',
                status: 'sent',
                number: 'DEV-2026-001',
                client_name: 'Dupont',
                updated_at: '2026-09-22 10:00:00',
                total_ttc_cents: 690000,
              },
            ],
          }),
      })),
    )

    render(
      <ThemeProvider theme={theme}>
        <CssBaseline />
        <MemoryRouter>
          <HomePage />
        </MemoryRouter>
      </ThemeProvider>,
    )

    expect(await screen.findByText(/Devis DEV-2026-001/)).toBeInTheDocument()
    expect(screen.getByText('Dupont')).toBeInTheDocument()
    expect(screen.getByText('Envoyé')).toBeInTheDocument()
    expect(document.body.textContent).not.toMatch(/payé|paiement/i)
    expect(screen.queryByRole('link', { name: /gérer les clients/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /gérer les clients/i })).not.toBeInTheDocument()
  })

  it('affiche un message si liste vide', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => ({
        ok: true,
        status: 200,
        text: async () => JSON.stringify({ items: [] }),
      })),
    )

    render(
      <ThemeProvider theme={theme}>
        <CssBaseline />
        <MemoryRouter>
          <HomePage />
        </MemoryRouter>
      </ThemeProvider>,
    )

    await waitFor(() => {
      expect(screen.getByText(/Aucun document/i)).toBeInTheDocument()
    })
  })
})
