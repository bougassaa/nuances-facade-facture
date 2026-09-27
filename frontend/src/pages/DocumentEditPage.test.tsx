import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ThemeProvider } from '@mui/material/styles'
import CssBaseline from '@mui/material/CssBaseline'
import DocumentEditPage from './DocumentEditPage'
import theme from '../theme'

describe('DocumentEditPage', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('affiche chantier, adresse projet et acompte sur un devis', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo) => {
        const url = String(input)
        if (url.includes('/company')) {
          return {
            ok: true,
            status: 200,
            text: async () => JSON.stringify({ vat_exempt: false, vat_rates: [] }),
          }
        }
        if (url.includes('/clients') && !url.match(/\/clients\/\d+/)) {
          return {
            ok: true,
            status: 200,
            text: async () =>
              JSON.stringify({
                items: [
                  {
                    id: 1,
                    name: 'M. Johan PASCAL',
                    address_line1: '56 chemin de la berche',
                    address_line2: '',
                    postal_code: '26790',
                    city: 'suze la rousse',
                    email: 'a@b.fr',
                    phone: '06',
                    vat_number: '',
                    notes: null,
                  },
                ],
              }),
          }
        }
        return { ok: true, status: 200, text: async () => '{}' }
      }),
    )

    render(
      <ThemeProvider theme={theme}>
        <CssBaseline />
        <MemoryRouter initialEntries={['/documents/new?type=quote']}>
          <Routes>
            <Route path="/documents/:id" element={<DocumentEditPage />} />
          </Routes>
        </MemoryRouter>
      </ThemeProvider>,
    )

    expect(await screen.findByLabelText('Chantier')).toBeInTheDocument()
    expect(screen.getByText('Adresse du projet')).toBeInTheDocument()
    expect(await screen.findByLabelText('Acompte (€)')).toBeInTheDocument()
    expect(screen.getByLabelText('TVA')).toBeInTheDocument()
  })

  it('affiche déduction et reste à payer sur une facture', async () => {
    const user = userEvent.setup()
    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo) => {
        const url = String(input)
        if (url.includes('/company')) {
          return {
            ok: true,
            status: 200,
            text: async () => JSON.stringify({ vat_exempt: false, vat_rates: [] }),
          }
        }
        if (url.includes('/clients') && !url.match(/\/clients\/\d+/)) {
          return {
            ok: true,
            status: 200,
            text: async () => JSON.stringify({ items: [] }),
          }
        }
        return { ok: true, status: 200, text: async () => '{}' }
      }),
    )

    render(
      <ThemeProvider theme={theme}>
        <CssBaseline />
        <MemoryRouter initialEntries={['/documents/new?type=invoice']}>
          <Routes>
            <Route path="/documents/:id" element={<DocumentEditPage />} />
          </Routes>
        </MemoryRouter>
      </ThemeProvider>,
    )

    expect(await screen.findByLabelText('Libellé de déduction')).toBeInTheDocument()
    const amount = screen.getByLabelText(/Montant déduit TTC/)
    await user.clear(amount)
    await user.type(amount, '100,00')
    await waitFor(() => {
      expect(screen.getByText(/Reste à payer/)).toBeInTheDocument()
    })
  })
})
