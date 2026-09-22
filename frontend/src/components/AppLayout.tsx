import AppBar from '@mui/material/AppBar'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Container from '@mui/material/Container'
import IconButton from '@mui/material/IconButton'
import Toolbar from '@mui/material/Toolbar'
import Typography from '@mui/material/Typography'
import BottomNavigation from '@mui/material/BottomNavigation'
import BottomNavigationAction from '@mui/material/BottomNavigationAction'
import HomeIcon from '@mui/icons-material/Home'
import PeopleIcon from '@mui/icons-material/People'
import SettingsIcon from '@mui/icons-material/Settings'
import LogoutIcon from '@mui/icons-material/Logout'
import { Link as RouterLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

export default function AppLayout() {
  const { email, logout } = useAuth()
  const location = useLocation()
  const navigate = useNavigate()

  const tab =
    location.pathname.startsWith('/clients')
      ? 1
      : location.pathname.startsWith('/settings')
        ? 2
        : 0

  return (
    <Box sx={{ pb: 9, minHeight: '100vh', bgcolor: 'background.default' }}>
      <AppBar position="sticky" elevation={1}>
        <Toolbar>
          <Typography variant="h6" sx={{ flexGrow: 1 }} noWrap>
            Nuances Facture
          </Typography>
          <Typography variant="body2" sx={{ mr: 1, display: { xs: 'none', sm: 'block' } }}>
            {email}
          </Typography>
          <IconButton
            color="inherit"
            aria-label="Déconnexion"
            onClick={async () => {
              await logout()
              navigate('/login')
            }}
          >
            <LogoutIcon />
          </IconButton>
        </Toolbar>
      </AppBar>

      <Container maxWidth="sm" sx={{ pt: 2, pb: 2 }}>
        <Outlet />
      </Container>

      <PaperNav value={tab} />

      <Box sx={{ display: { xs: 'none', md: 'flex' }, gap: 1, position: 'fixed', top: 72, right: 16 }}>
        <Button component={RouterLink} to="/" variant="outlined">
          Accueil
        </Button>
        <Button component={RouterLink} to="/clients" variant="outlined">
          Clients
        </Button>
        <Button component={RouterLink} to="/settings" variant="outlined">
          Réglages
        </Button>
      </Box>
    </Box>
  )
}

function PaperNav({ value }: { value: number }) {
  const navigate = useNavigate()
  return (
    <BottomNavigation
      showLabels
      value={value}
      onChange={(_, v) => {
        navigate(v === 0 ? '/' : v === 1 ? '/clients' : '/settings')
      }}
      sx={{
        position: 'fixed',
        bottom: 0,
        left: 0,
        right: 0,
        borderTop: 1,
        borderColor: 'divider',
        display: { md: 'none' },
      }}
    >
      <BottomNavigationAction label="Accueil" icon={<HomeIcon />} />
      <BottomNavigationAction label="Clients" icon={<PeopleIcon />} />
      <BottomNavigationAction label="Réglages" icon={<SettingsIcon />} />
    </BottomNavigation>
  )
}
