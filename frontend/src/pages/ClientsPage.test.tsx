import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ThemeProvider } from '@mui/material/styles'
import CssBaseline from '@mui/material/CssBaseline'
import ClientsPage from './ClientsPage'
import theme from '../theme'

describe('ClientsPage', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    vi.useFakeTimers({ shouldAdvanceTime: true })
  })

  it('n’affiche pas de bouton Filtrer', async () => {
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
          <ClientsPage />
        </MemoryRouter>
      </ThemeProvider>,
    )

    await waitFor(() => {
      expect(screen.getByRole('textbox', { name: /rechercher/i })).toBeInTheDocument()
    })
    expect(screen.queryByRole('button', { name: /filtrer/i })).not.toBeInTheDocument()
  })

  it('lance la recherche à la saisie', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    const fetchMock = vi.fn(async (url: string) => ({
      ok: true,
      status: 200,
      text: async () => {
        if (String(url).includes('q=')) {
          return JSON.stringify({
            items: [{ id: 2, name: 'Alpha SARL', postal_code: '', city: 'Lyon' }],
          })
        }
        return JSON.stringify({
          items: [
            { id: 1, name: 'Beta SAS', postal_code: '', city: 'Lyon' },
            { id: 2, name: 'Alpha SARL', postal_code: '', city: 'Lyon' },
          ],
        })
      },
    }))
    vi.stubGlobal('fetch', fetchMock)

    render(
      <ThemeProvider theme={theme}>
        <CssBaseline />
        <MemoryRouter>
          <ClientsPage />
        </MemoryRouter>
      </ThemeProvider>,
    )

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalled()
    })

    await user.type(screen.getByRole('textbox', { name: /rechercher/i }), 'Alpha')
    await vi.advanceTimersByTimeAsync(300)

    await waitFor(() => {
      const searched = fetchMock.mock.calls.some((c) => String(c[0]).includes('q=Alpha'))
      expect(searched).toBe(true)
    })
    expect(await screen.findByText('Alpha SARL')).toBeInTheDocument()
  })
})
