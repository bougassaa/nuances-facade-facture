import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { api, setCsrfToken } from '../api/client'

type AuthState = {
  loading: boolean
  needsSetup: boolean
  authenticated: boolean
  email: string | null
  refresh: () => Promise<void>
  login: (email: string, password: string) => Promise<void>
  setup: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthState | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [loading, setLoading] = useState(true)
  const [needsSetup, setNeedsSetup] = useState(false)
  const [authenticated, setAuthenticated] = useState(false)
  const [email, setEmail] = useState<string | null>(null)

  const refresh = useCallback(async () => {
    const status = await api<{
      needs_setup: boolean
      authenticated: boolean
      csrf_token: string
    }>('/setup/status')
    setCsrfToken(status.csrf_token)
    setNeedsSetup(status.needs_setup)
    setAuthenticated(status.authenticated)
    if (status.authenticated) {
      const me = await api<{ user: { email: string }; csrf_token: string }>('/auth/me')
      setEmail(me.user.email)
    } else {
      setEmail(null)
    }
  }, [])

  useEffect(() => {
    refresh()
      .catch(() => {
        setAuthenticated(false)
      })
      .finally(() => setLoading(false))
  }, [refresh])

  const login = useCallback(async (mail: string, password: string) => {
    const res = await api<{ email: string; csrf_token: string }>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email: mail, password }),
    })
    setAuthenticated(true)
    setNeedsSetup(false)
    setEmail(res.email)
  }, [])

  const setup = useCallback(async (mail: string, password: string) => {
    await api('/setup', {
      method: 'POST',
      body: JSON.stringify({ email: mail, password }),
    })
    setAuthenticated(true)
    setNeedsSetup(false)
    setEmail(mail)
  }, [])

  const logout = useCallback(async () => {
    await api('/auth/logout', { method: 'POST', body: JSON.stringify({}) })
    setAuthenticated(false)
    setEmail(null)
    await refresh()
  }, [refresh])

  const value = useMemo(
    () => ({ loading, needsSetup, authenticated, email, refresh, login, setup, logout }),
    [loading, needsSetup, authenticated, email, refresh, login, setup, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth hors AuthProvider')
  return ctx
}
