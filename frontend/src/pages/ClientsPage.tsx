import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardActionArea from '@mui/material/CardActionArea'
import CardContent from '@mui/material/CardContent'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogTitle from '@mui/material/DialogTitle'
import Fab from '@mui/material/Fab'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import AddIcon from '@mui/icons-material/Add'
import type { FormEvent } from 'react'
import { useEffect, useState } from 'react'
import { Link as RouterLink, useNavigate } from 'react-router-dom'
import { api } from '../api/client'
import type { Client } from '../types'

const emptyClient = {
  name: '',
  address_line1: '',
  address_line2: '',
  postal_code: '',
  city: '',
  email: '',
  phone: '',
  vat_number: '',
  notes: '',
}

const SEARCH_DEBOUNCE_MS = 250

export default function ClientsPage() {
  const [items, setItems] = useState<Client[]>([])
  const [q, setQ] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState(emptyClient)
  const [busy, setBusy] = useState(false)
  const navigate = useNavigate()

  useEffect(() => {
    const handle = window.setTimeout(() => {
      const query = q.trim()
      api<{ items: Client[] }>(
        `/clients${query ? `?q=${encodeURIComponent(query)}` : ''}`,
      )
        .then((res) => {
          setItems(res.items)
          setError(null)
        })
        .catch((e) => setError(e instanceof Error ? e.message : 'Erreur'))
    }, SEARCH_DEBOUNCE_MS)

    return () => window.clearTimeout(handle)
  }, [q])

  async function onCreate(e: FormEvent) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const created = await api<Client>('/clients', {
        method: 'POST',
        body: JSON.stringify(form),
      })
      setOpen(false)
      setForm(emptyClient)
      navigate(`/clients/${created.id}`)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erreur')
    } finally {
      setBusy(false)
    }
  }

  return (
    <Stack spacing={2}>
      <Typography variant="h5">Clients</Typography>
      {error && <Alert severity="error">{error}</Alert>}
      <TextField
        label="Rechercher"
        value={q}
        onChange={(e) => setQ(e.target.value)}
        autoComplete="off"
      />

      {items.map((c) => (
        <Card key={c.id}>
          <CardActionArea component={RouterLink} to={`/clients/${c.id}`}>
            <CardContent>
              <Typography variant="subtitle1" sx={{ fontWeight: 600 }}>
                {c.name}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {[c.postal_code, c.city].filter(Boolean).join(' ') || '—'}
              </Typography>
            </CardContent>
          </CardActionArea>
        </Card>
      ))}

      <Fab
        color="primary"
        aria-label="Nouveau client"
        sx={{ position: 'fixed', bottom: 72, right: 16 }}
        onClick={() => setOpen(true)}
      >
        <AddIcon />
      </Fab>

      <Dialog open={open} onClose={() => setOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>Nouveau client</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }} component="form" id="new-client" onSubmit={onCreate}>
            <TextField
              label="Nom / raison sociale"
              required
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
            />
            <TextField
              label="Adresse"
              value={form.address_line1}
              onChange={(e) => setForm({ ...form, address_line1: e.target.value })}
            />
            <TextField
              label="Complément"
              value={form.address_line2}
              onChange={(e) => setForm({ ...form, address_line2: e.target.value })}
            />
            <Stack direction="row" spacing={1}>
              <TextField
                label="Code postal"
                value={form.postal_code}
                onChange={(e) => setForm({ ...form, postal_code: e.target.value })}
              />
              <TextField
                label="Ville"
                value={form.city}
                onChange={(e) => setForm({ ...form, city: e.target.value })}
              />
            </Stack>
            <TextField
              label="Email"
              type="email"
              value={form.email}
              onChange={(e) => setForm({ ...form, email: e.target.value })}
            />
            <TextField
              label="Téléphone"
              value={form.phone}
              onChange={(e) => setForm({ ...form, phone: e.target.value })}
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setOpen(false)}>Annuler</Button>
          <Button type="submit" form="new-client" variant="contained" disabled={busy}>
            Créer
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  )
}
