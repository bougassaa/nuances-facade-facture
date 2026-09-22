import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Checkbox from '@mui/material/Checkbox'
import FormControlLabel from '@mui/material/FormControlLabel'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import type { FormEvent } from 'react'
import { useEffect, useState } from 'react'
import { api } from '../api/client'
import type { Company } from '../types'

export default function SettingsPage() {
  const [form, setForm] = useState<Company | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [ok, setOk] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    api<Company>('/company')
      .then(setForm)
      .catch((e) => setError(e instanceof Error ? e.message : 'Erreur'))
  }, [])

  async function onSave(e: FormEvent) {
    e.preventDefault()
    if (!form) return
    setBusy(true)
    setError(null)
    setOk(null)
    try {
      const updated = await api<Company>('/company', {
        method: 'PUT',
        body: JSON.stringify({
          ...form,
          vat_exempt: Boolean(form.vat_exempt),
        }),
      })
      setForm(updated)
      setOk('Enregistré')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erreur')
    } finally {
      setBusy(false)
    }
  }

  async function onLogo(file: File | null) {
    if (!file) return
    setBusy(true)
    setError(null)
    try {
      const body = new FormData()
      body.append('logo', file)
      const updated = await api<Company>('/company/logo', { method: 'POST', body })
      setForm(updated)
      setOk('Logo mis à jour')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erreur')
    } finally {
      setBusy(false)
    }
  }

  if (!form && !error) return null

  return (
    <Stack spacing={2} component="form" onSubmit={onSave}>
      <Typography variant="h5">Entreprise</Typography>
      {error && <Alert severity="error">{error}</Alert>}
      {ok && <Alert severity="success">{ok}</Alert>}
      {form && (
        <>
          <TextField
            label="Raison sociale"
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
            label="Téléphone"
            value={form.phone}
            onChange={(e) => setForm({ ...form, phone: e.target.value })}
          />
          <TextField
            label="Email"
            type="email"
            value={form.email}
            onChange={(e) => setForm({ ...form, email: e.target.value })}
          />
          <TextField
            label="SIRET"
            value={form.siret}
            onChange={(e) => setForm({ ...form, siret: e.target.value })}
          />
          <TextField
            label="N° TVA"
            value={form.vat_number}
            onChange={(e) => setForm({ ...form, vat_number: e.target.value })}
          />
          <FormControlLabel
            control={
              <Checkbox
                checked={Boolean(form.vat_exempt)}
                onChange={(e) => setForm({ ...form, vat_exempt: e.target.checked })}
              />
            }
            label="Franchise en base (art. 293 B CGI)"
          />
          <TextField
            label="IBAN (impression PDF)"
            value={form.iban}
            onChange={(e) => setForm({ ...form, iban: e.target.value })}
          />
          <TextField
            label="BIC"
            value={form.bic}
            onChange={(e) => setForm({ ...form, bic: e.target.value })}
          />

          <Typography variant="subtitle1">Mentions légales</Typography>
          <TextField
            label="Assurance décennale"
            multiline
            minRows={2}
            value={form.legal_decennale ?? ''}
            onChange={(e) => setForm({ ...form, legal_decennale: e.target.value })}
          />
          <TextField
            label="Pénalités de retard"
            multiline
            minRows={2}
            value={form.legal_late_penalties ?? ''}
            onChange={(e) => setForm({ ...form, legal_late_penalties: e.target.value })}
          />
          <TextField
            label="Indemnité forfaitaire de recouvrement"
            multiline
            minRows={2}
            value={form.legal_recovery_fee ?? ''}
            onChange={(e) => setForm({ ...form, legal_recovery_fee: e.target.value })}
          />
          <TextField
            label="Validité des devis"
            multiline
            minRows={2}
            value={form.legal_quote_validity ?? ''}
            onChange={(e) => setForm({ ...form, legal_quote_validity: e.target.value })}
          />
          <TextField
            label="Mentions complémentaires"
            multiline
            minRows={2}
            value={form.legal_extra ?? ''}
            onChange={(e) => setForm({ ...form, legal_extra: e.target.value })}
          />

          <Typography variant="subtitle1">Logo</Typography>
          {form.has_logo && (
            <BoxLogo />
          )}
          <Button variant="outlined" component="label" disabled={busy}>
            Choisir un logo
            <input
              type="file"
              hidden
              accept="image/png,image/jpeg,image/webp,image/gif"
              onChange={(e) => onLogo(e.target.files?.[0] ?? null)}
            />
          </Button>

          <Button type="submit" variant="contained" disabled={busy}>
            Enregistrer
          </Button>
        </>
      )}
    </Stack>
  )
}

function BoxLogo() {
  return (
    <Box
      component="img"
      src="/api/company/logo"
      alt="Logo"
      sx={{ maxWidth: 180, maxHeight: 80, objectFit: 'contain' }}
    />
  )
}
