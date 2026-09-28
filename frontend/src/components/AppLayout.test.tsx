import { render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import AppLayout from './AppLayout'
import theme from '../theme'

vi.mock('../auth/AuthContext', () => ({
  useAuth: () => ({
    email: 'artisan@example.fr',
    logout: vi.fn(async () => {}),
  }),
}))

describe('AppLayout', () => {
  it('place la barre du bas au-dessus des labels de champs', () => {
    render(
      <ThemeProvider theme={theme}>
        <MemoryRouter>
          <AppLayout />
        </MemoryRouter>
      </ThemeProvider>,
    )

    const nav = screen.getByRole('button', { name: 'Accueil' }).closest('.MuiBottomNavigation-root')
    expect(nav).not.toBeNull()
    expect(Number(getComputedStyle(nav as Element).zIndex)).toBeGreaterThanOrEqual(theme.zIndex.appBar)
  })
})
