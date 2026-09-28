import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ThemeProvider } from '@mui/material/styles'
import CssBaseline from '@mui/material/CssBaseline'
import SettingsPage from './SettingsPage'
import theme from '../theme'

const company = {
  id: 1,
  name: 'Nuances',
  address_line1: '',
  address_line2: '',
  postal_code: '',
  city: '',
  phone: '',
  email: '',
  siret: '',
  vat_number: '',
  iban: '',
  bic: '',
  website: '',
  legal_form: 'EI',
  payment_terms: null,
  vat_exempt: false,
  legal_decennale: null,
  legal_late_penalties: null,
  legal_recovery_fee: null,
  legal_quote_validity: null,
  legal_extra: null,
  has_logo: false,
  counters: { year: 2026, quote: 0, invoice: 0 },
  vat_rates: [],
}

describe('SettingsPage designations', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('crée, modifie le prix et supprime une désignation', async () => {
    const user = userEvent.setup()
    let items = [{ id: 1, label: 'Enduit', unit_price_ht_cents: 4000 }]

    const fetchMock = vi.fn(async (input: RequestInfo, init?: RequestInit) => {
      const url = String(input)
      const method = (init?.method ?? 'GET').toUpperCase()

      if (url.includes('/company') && method === 'GET') {
        return { ok: true, status: 200, text: async () => JSON.stringify(company) }
      }
      if (url.includes('/designations') && method === 'GET') {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify({ items }),
        }
      }
      if (url.endsWith('/designations') && method === 'POST') {
        const body = JSON.parse(String(init?.body ?? '{}'))
        const created = {
          id: 2,
          label: body.label,
          unit_price_ht_cents: body.unit_price_ht_cents,
        }
        items = [...items, created]
        return { ok: true, status: 201, text: async () => JSON.stringify(created) }
      }
      if (url.match(/\/designations\/\d+$/) && method === 'PUT') {
        const id = Number(url.split('/').pop())
        const body = JSON.parse(String(init?.body ?? '{}'))
        items = items.map((d) =>
          d.id === id
            ? { ...d, label: body.label, unit_price_ht_cents: body.unit_price_ht_cents }
            : d,
        )
        const updated = items.find((d) => d.id === id)!
        return { ok: true, status: 200, text: async () => JSON.stringify(updated) }
      }
      if (url.match(/\/designations\/\d+$/) && method === 'DELETE') {
        const id = Number(url.split('/').pop())
        items = items.filter((d) => d.id !== id)
        return { ok: true, status: 204, text: async () => '' }
      }
      return { ok: true, status: 200, text: async () => '{}' }
    })
    vi.stubGlobal('fetch', fetchMock)

    render(
      <ThemeProvider theme={theme}>
        <CssBaseline />
        <SettingsPage />
      </ThemeProvider>,
    )

    expect(await screen.findByRole('heading', { name: 'Désignations' })).toBeInTheDocument()
    expect(screen.getByDisplayValue('Enduit')).toBeInTheDocument()
    expect(screen.getByDisplayValue('40,00')).toBeInTheDocument()

    const price = screen.getByLabelText('Prix HT par défaut (€)')
    await user.clear(price)
    await user.type(price, '45,00')
    await user.click(screen.getAllByRole('button', { name: 'Enregistrer' })[1])

    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('/designations/1'))).toBe(
        true,
      )
    })

    await user.click(screen.getByRole('button', { name: 'Ajouter une désignation' }))
    const labels = screen.getAllByLabelText('Désignation')
    await user.type(labels[labels.length - 1], 'Peinture')
    const prices = screen.getAllByLabelText('Prix HT par défaut (€)')
    await user.clear(prices[prices.length - 1])
    await user.type(prices[prices.length - 1], '12,50')
    await user.click(screen.getAllByRole('button', { name: 'Enregistrer' }).at(-1)!)

    await waitFor(() => {
      expect(
        fetchMock.mock.calls.some(
          (c) => String(c[0]).endsWith('/designations') && (c[1] as RequestInit)?.method === 'POST',
        ),
      ).toBe(true)
    })

    await waitFor(() => {
      expect(screen.getByDisplayValue('Peinture')).toBeInTheDocument()
    })

    await user.click(screen.getAllByLabelText('Supprimer la désignation')[0])
    await waitFor(() => {
      expect(
        fetchMock.mock.calls.some(
          (c) =>
            String(c[0]).includes('/designations/1') &&
            (c[1] as RequestInit)?.method === 'DELETE',
        ),
      ).toBe(true)
    })
  })
})
