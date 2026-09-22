import CircularProgress from '@mui/material/CircularProgress'
import Box from '@mui/material/Box'
import { Navigate, Route, Routes } from 'react-router-dom'
import { useAuth } from './auth/AuthContext'
import AppLayout from './components/AppLayout'
import ClientEditPage from './pages/ClientEditPage'
import ClientsPage from './pages/ClientsPage'
import DocumentEditPage from './pages/DocumentEditPage'
import HomePage from './pages/HomePage'
import LoginPage from './pages/LoginPage'
import SettingsPage from './pages/SettingsPage'
import SetupPage from './pages/SetupPage'

function Protected({ children }: { children: React.ReactNode }) {
  const { loading, authenticated, needsSetup } = useAuth()
  if (loading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', mt: 8 }}>
        <CircularProgress />
      </Box>
    )
  }
  if (needsSetup) return <Navigate to="/setup" replace />
  if (!authenticated) return <Navigate to="/login" replace />
  return children
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/setup" element={<SetupPage />} />
      <Route
        element={
          <Protected>
            <AppLayout />
          </Protected>
        }
      >
        <Route path="/" element={<HomePage />} />
        <Route path="/clients" element={<ClientsPage />} />
        <Route path="/clients/:id" element={<ClientEditPage />} />
        <Route path="/documents/:id" element={<DocumentEditPage />} />
        <Route path="/settings" element={<SettingsPage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
