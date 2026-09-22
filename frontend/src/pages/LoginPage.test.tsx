import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ThemeProvider } from '@mui/material/styles'
import CssBaseline from '@mui/material/CssBaseline'
import LoginPage from './LoginPage'
import { AuthProvider } from '../auth/AuthContext'
import theme from '../theme'

function mockFetchSequence(responses: unknown[]) {
  let i = 0
  vi.stubGlobal(
    'fetch',
    vi.fn(async () => {
      const body = responses[Math.min(i, responses.length - 1)]
      i += 1
      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify(body),
      }
    }),
  )
}

function renderLogin() {
  return render(
    <ThemeProvider theme={theme}>
      <CssBaseline />
      <MemoryRouter initialEntries={['/login']}>
        <AuthProvider>
          <LoginPage />
        </AuthProvider>
      </MemoryRouter>
    </ThemeProvider>,
  )
}

describe('LoginPage', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('affiche le formulaire de connexion', async () => {
    mockFetchSequence([
      { needs_setup: false, authenticated: false, csrf_token: 'tok' },
    ])
    renderLogin()
    expect(await screen.findByRole('heading', { name: 'Connexion' })).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: /email/i })).toBeInTheDocument()
    expect(screen.getByLabelText(/mot de passe/i)).toBeInTheDocument()
  })

  it('soumet email et mot de passe', async () => {
    const user = userEvent.setup()
    mockFetchSequence([
      { needs_setup: false, authenticated: false, csrf_token: 'tok' },
      { ok: true, email: 'a@b.fr', csrf_token: 'tok2' },
    ])
    renderLogin()
    await screen.findByRole('heading', { name: 'Connexion' })

    await user.type(screen.getByRole('textbox', { name: /email/i }), 'a@b.fr')
    await user.type(screen.getByLabelText(/mot de passe/i), 'secret123')
    await user.click(screen.getByRole('button', { name: 'Se connecter' }))

    await waitFor(() => {
      const calls = (fetch as unknown as ReturnType<typeof vi.fn>).mock.calls
      const loginCall = calls.find((c) => String(c[0]).includes('/auth/login'))
      expect(loginCall).toBeTruthy()
      expect(loginCall?.[1]?.body).toContain('a@b.fr')
    })
  })
})
