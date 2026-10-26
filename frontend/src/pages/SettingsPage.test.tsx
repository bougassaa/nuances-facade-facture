import { fireEvent, render, screen, waitFor } from '@testing-library/react'
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
  has_signature: false,
  counters: { year: 2026, quote: 0, invoice: 0 },
  vat_rates: [],
}

function callMethod(call: unknown[]): string {
  return String((call[1] as RequestInit | undefined)?.method ?? 'GET').toUpperCase()
}

function callUrl(call: unknown[]): string {
  return String(call[0])
}

describe('SettingsPage numérotation', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('laisse les compteurs saisissables même quand une séquence existe', async () => {
    const user = userEvent.setup()
    const started = {
      ...company,
      counters: { year: 2026, quote: 12, invoice: 4 },
    }

    const fetchMock = vi.fn(async (input: RequestInfo, init?: RequestInit) => {
      const url = String(input)
      const method = (init?.method ?? 'GET').toUpperCase()
      if (url.includes('/company') && method === 'GET') {
        return { ok: true, status: 200, text: async () => JSON.stringify(started) }
      }
      if (url.includes('/company') && method === 'PUT') {
        const body = JSON.parse(String(init?.body ?? '{}'))
        return {
          ok: true,
          status: 200,
          text: async () =>
            JSON.stringify({
              ...started,
              counters: {
                year: 2026,
                quote: body.last_quote_number,
                invoice: body.last_invoice_number,
              },
            }),
        }
      }
      if (url.includes('/designations') && method === 'GET') {
        return { ok: true, status: 200, text: async () => JSON.stringify({ items: [] }) }
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

    const quote = await screen.findByLabelText('Dernier n° devis')
    const invoice = screen.getByLabelText('Dernier n° facture')
    expect(quote).toBeEnabled()
    expect(invoice).toBeEnabled()
    expect(quote).toHaveValue('12')
    expect(invoice).toHaveValue('4')

    fireEvent.change(quote, { target: { value: '40' } })
    await user.click(screen.getAllByRole('button', { name: 'Enregistrer' })[0])

    await waitFor(() => {
      const put = fetchMock.mock.calls.find(
        (c) => callUrl(c).includes('/company') && callMethod(c) === 'PUT',
      )
      expect(put).toBeTruthy()
      const body = JSON.parse(String((put![1] as RequestInit).body))
      expect(body.last_quote_number).toBe(40)
      expect(body.last_invoice_number).toBe(4)
    })
  })
})

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
      if (url.includes('/designations') && !url.match(/\/designations\/\d+/) && method === 'POST') {
        const body = JSON.parse(String(init?.body ?? '{}'))
        const created = {
          id: 2,
          label: body.label,
          unit_price_ht_cents: body.unit_price_ht_cents,
        }
        items = [...items, created]
        return { ok: true, status: 201, text: async () => JSON.stringify(created) }
      }
      if (url.match(/\/designations\/\d+/) && method === 'PUT') {
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
      if (url.match(/\/designations\/\d+/) && method === 'DELETE') {
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

    fireEvent.change(screen.getByLabelText('Prix HT par défaut (€)'), {
      target: { value: '45,00' },
    })
    // Deux « Enregistrer » : formulaire entreprise puis carte désignation
    const designationSaveButtons = screen.getAllByRole('button', { name: 'Enregistrer' })
    await user.click(designationSaveButtons[designationSaveButtons.length - 1])

    await waitFor(() => {
      expect(
        fetchMock.mock.calls.some(
          (c) => callUrl(c).includes('/designations/1') && callMethod(c) === 'PUT',
        ),
      ).toBe(true)
    })

    await user.click(screen.getByRole('button', { name: 'Ajouter une désignation' }))
    const labels = screen.getAllByLabelText('Désignation')
    fireEvent.change(labels[labels.length - 1], { target: { value: 'Peinture' } })
    const prices = screen.getAllByLabelText('Prix HT par défaut (€)')
    fireEvent.change(prices[prices.length - 1], { target: { value: '12,50' } })
    const saveButtons = screen.getAllByRole('button', { name: 'Enregistrer' })
    await user.click(saveButtons[saveButtons.length - 1])

    await waitFor(() => {
      expect(
        fetchMock.mock.calls.some(
          (c) => callUrl(c).includes('/designations') && callMethod(c) === 'POST',
        ),
      ).toBe(true)
    })
    expect(await screen.findByDisplayValue('Peinture')).toBeInTheDocument()

    await user.click(screen.getAllByLabelText('Supprimer la désignation')[0])
    await waitFor(() => {
      expect(
        fetchMock.mock.calls.some(
          (c) => callUrl(c).includes('/designations/1') && callMethod(c) === 'DELETE',
        ),
      ).toBe(true)
    })
  })
})

describe('SettingsPage signature', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('envoie l’image puis permet de la retirer', async () => {
    const user = userEvent.setup()
    let current = { ...company, has_signature: false }

    const fetchMock = vi.fn(async (input: RequestInfo, init?: RequestInit) => {
      const url = String(input)
      const method = (init?.method ?? 'GET').toUpperCase()
      if (url.includes('/company/signature') && method === 'POST') {
        current = { ...current, has_signature: true }
        return { ok: true, status: 200, text: async () => JSON.stringify(current) }
      }
      if (url.includes('/company/signature') && method === 'DELETE') {
        current = { ...current, has_signature: false }
        return { ok: true, status: 200, text: async () => JSON.stringify(current) }
      }
      if (url.includes('/company') && method === 'GET') {
        return { ok: true, status: 200, text: async () => JSON.stringify(current) }
      }
      if (url.includes('/designations') && method === 'GET') {
        return { ok: true, status: 200, text: async () => JSON.stringify({ items: [] }) }
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

    const input = await screen.findByLabelText('Fichier de signature')
    const file = new File([new Uint8Array([137, 80, 78, 71])], 'signature.png', {
      type: 'image/png',
    })
    await user.upload(input, file)

    await waitFor(() => {
      const post = fetchMock.mock.calls.find(
        (c) => callUrl(c).includes('/company/signature') && callMethod(c) === 'POST',
      )
      expect(post).toBeTruthy()
      const body = (post![1] as RequestInit).body
      expect(body).toBeInstanceOf(FormData)
      expect((body as FormData).get('signature')).toBeInstanceOf(File)
    })
    expect(await screen.findByRole('img', { name: 'Signature' })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Retirer la signature' }))
    await waitFor(() => {
      expect(
        fetchMock.mock.calls.some(
          (c) => callUrl(c).includes('/company/signature') && callMethod(c) === 'DELETE',
        ),
      ).toBe(true)
    })
    expect(screen.queryByRole('img', { name: 'Signature' })).not.toBeInTheDocument()
  })
})
