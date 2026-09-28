import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ThemeProvider } from '@mui/material/styles'
import CssBaseline from '@mui/material/CssBaseline'
import DocumentEditPage from './DocumentEditPage'
import theme from '../theme'

function mockFetch(handlers: Record<string, unknown> = {}) {
  return vi.fn(async (input: RequestInfo) => {
    const url = String(input)
    if (url.includes('/designations')) {
      return {
        ok: true,
        status: 200,
        text: async () =>
          JSON.stringify(
            handlers.designations ?? {
              items: [],
            },
          ),
      }
    }
    if (url.includes('/company')) {
      return {
        ok: true,
        status: 200,
        text: async () =>
          JSON.stringify(handlers.company ?? { vat_exempt: false, vat_rates: [] }),
      }
    }
    if (url.includes('/clients') && !url.match(/\/clients\/\d+/)) {
      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify(handlers.clients ?? { items: [] }),
      }
    }
    return { ok: true, status: 200, text: async () => '{}' }
  })
}

describe('DocumentEditPage', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('affiche chantier, adresse projet et acompte sur un devis', async () => {
    vi.stubGlobal(
      'fetch',
      mockFetch({
        clients: {
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
        },
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

  it('propose les unités prédéfinies et n’affiche que l’abréviation une fois choisie', async () => {
    const user = userEvent.setup()
    vi.stubGlobal('fetch', mockFetch())

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

    const unit = await screen.findByRole('combobox', { name: 'Unité' })
    expect(unit).toHaveTextContent('m²')

    await user.click(unit)
    expect(await screen.findByRole('option', { name: 'm²' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'ml (mètre linéaire)' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'u (unité)' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'forfait' })).toBeInTheDocument()
    expect(screen.getAllByRole('option')).toHaveLength(4)

    await user.click(screen.getByRole('option', { name: 'ml (mètre linéaire)' }))
    expect(unit).toHaveTextContent('ml')
    expect(unit).not.toHaveTextContent('mètre')
  })

  it('préremplit le prix HT à la sélection d’une désignation', async () => {
    const user = userEvent.setup()
    vi.stubGlobal(
      'fetch',
      mockFetch({
        designations: {
          items: [{ id: 1, label: 'Enduit monocouche', unit_price_ht_cents: 4500 }],
        },
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

    const designation = await screen.findByLabelText('Désignation')
    await user.click(designation)
    const option = await screen.findByRole('option', { name: /Enduit monocouche/ })
    await user.click(option)

    expect(designation).toHaveValue('Enduit monocouche')
    expect(screen.getByLabelText('P.U. HT (€)')).toHaveValue('45,00')
  })

  it('calcule un total avec virgule en quantité et point en prix', async () => {
    const user = userEvent.setup()
    vi.stubGlobal('fetch', mockFetch({ company: { vat_exempt: true, vat_rates: [] } }))

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
    vi.stubGlobal('fetch', mockFetch())

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
