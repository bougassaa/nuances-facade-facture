import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import type { FormEvent } from 'react'
import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../api/client'
import type { Client } from '../types'

export default function ClientEditPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [form, setForm] = useState<Partial<Client> | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    api<Client>(`/clients/${id}`)
      .then(setForm)
      .catch((e) => setError(e instanceof Error ? e.message : 'Erreur'))
  }, [id])

  async function onSave(e: FormEvent) {
    e.preventDefault()
    if (!form) return
    setBusy(true)
    setError(null)
    try {
      await api(`/clients/${id}`, { method: 'PUT', body: JSON.stringify(form) })
      navigate('/clients')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erreur')
    } finally {
      setBusy(false)
    }
  }

  async function onDelete() {
    if (!window.confirm('Supprimer ce client ?')) return
    setBusy(true)
    try {
      await api(`/clients/${id}`, { method: 'DELETE' })
      navigate('/clients')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erreur')
      setBusy(false)
    }
  }

  if (!form && !error) return null

  return (
    <Stack spacing={2} component="form" onSubmit={onSave}>
      <Typography variant="h5">Client</Typography>
      {error && <Alert severity="error">{error}</Alert>}
      {form && (
        <>
          <TextField
            label="Nom / raison sociale"
            required
            value={form.name ?? ''}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
          />
          <TextField
            label="Adresse"
            value={form.address_line1 ?? ''}
            onChange={(e) => setForm({ ...form, address_line1: e.target.value })}
          />
          <TextField
            label="Complément"
            value={form.address_line2 ?? ''}
            onChange={(e) => setForm({ ...form, address_line2: e.target.value })}
          />
          <Stack direction="row" spacing={1}>
            <TextField
              label="Code postal"
              value={form.postal_code ?? ''}
              onChange={(e) => setForm({ ...form, postal_code: e.target.value })}
            />
            <TextField
              label="Ville"
              value={form.city ?? ''}
              onChange={(e) => setForm({ ...form, city: e.target.value })}
            />
          </Stack>
          <TextField
            label="Email"
            type="email"
            value={form.email ?? ''}
            onChange={(e) => setForm({ ...form, email: e.target.value })}
          />
          <TextField
            label="Téléphone"
            value={form.phone ?? ''}
            onChange={(e) => setForm({ ...form, phone: e.target.value })}
          />
          <TextField
            label="N° TVA"
            value={form.vat_number ?? ''}
            onChange={(e) => setForm({ ...form, vat_number: e.target.value })}
          />
          <TextField
            label="Notes"
            multiline
            minRows={2}
            value={form.notes ?? ''}
            onChange={(e) => setForm({ ...form, notes: e.target.value })}
          />
          <Button type="submit" variant="contained" disabled={busy}>
            Enregistrer
          </Button>
          <Button color="error" onClick={onDelete} disabled={busy}>
            Supprimer
          </Button>
        </>
      )}
    </Stack>
  )
}
