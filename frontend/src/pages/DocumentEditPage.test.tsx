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
    const deposit = await screen.findByLabelText('Acompte (€)')
    expect(deposit).toHaveAttribute('inputmode', 'decimal')
    expect(screen.getByLabelText('Qté')).toHaveAttribute('inputmode', 'decimal')
    expect(screen.getByLabelText('P.U. HT (€)')).toHaveAttribute('inputmode', 'decimal')
    expect(screen.getByLabelText('Code postal')).toHaveAttribute('inputmode', 'numeric')
    expect(screen.getByLabelText('TVA')).toBeInTheDocument()
  })

  it('calcule un total avec virgule en quantité et point en prix', async () => {
    const user = userEvent.setup()
    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo) => {
        const url = String(input)
        if (url.includes('/company')) {
          return {
            ok: true,
            status: 200,
            text: async () => JSON.stringify({ vat_exempt: true, vat_rates: [] }),
          }
        }
        if (url.includes('/clients')) {
          return { ok: true, status: 200, text: async () => JSON.stringify({ items: [] }) }
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

    const qty = await screen.findByLabelText('Qté')
    await user.type(screen.getByLabelText('Désignation'), 'Enduit')
    await user.clear(qty)
    await user.type(qty, '2,5')
    expect(qty).toHaveValue('2,5')
    const price = screen.getByLabelText('P.U. HT (€)')
    await user.clear(price)
    await user.paste('10.00')
    expect(price).toHaveValue('10,00')
    expect(screen.getByText(/Total HT/)).toHaveTextContent(/25,00/)
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
