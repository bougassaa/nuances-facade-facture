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
  const [lastQuote, setLastQuote] = useState('0')
  const [lastInvoice, setLastInvoice] = useState('0')
  const [error, setError] = useState<string | null>(null)
  const [ok, setOk] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    api<Company>('/company')
      .then((c) => {
        setForm(c)
        setLastQuote(String(c.counters?.quote ?? 0))
        setLastInvoice(String(c.counters?.invoice ?? 0))
      })
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
          last_quote_number: Number.parseInt(lastQuote, 10) || 0,
          last_invoice_number: Number.parseInt(lastInvoice, 10) || 0,
        }),
      })
      setForm(updated)
      setLastQuote(String(updated.counters?.quote ?? 0))
      setLastInvoice(String(updated.counters?.invoice ?? 0))
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

  const year = form?.counters?.year ?? new Date().getFullYear()
  const canSeed =
    (form?.counters?.quote ?? 0) === 0 && (form?.counters?.invoice ?? 0) === 0

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
            label="Site web"
            value={form.website ?? ''}
            onChange={(e) => setForm({ ...form, website: e.target.value })}
          />
          <TextField
            label="Forme juridique"
            value={form.legal_form ?? 'EI'}
            onChange={(e) => setForm({ ...form, legal_form: e.target.value })}
            helperText="Affiché dans le pied de page PDF (ex. EI)"
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
          <TextField
            label="Conditions de paiement (factures)"
            multiline
            minRows={2}
            value={form.payment_terms ?? ''}
            onChange={(e) => setForm({ ...form, payment_terms: e.target.value })}
            helperText="Ex. Paiement à réception."
          />

          <Typography variant="subtitle1">Mentions légales</Typography>
          <TextField
            label="Assurance décennale"
            multiline
            minRows={2}
            value={form.legal_decennale ?? ''}
            onChange={(e) => setForm({ ...form, legal_decennale: e.target.value })}
            helperText="Ex. ERGO ASSURANCES SV75020721/09686 — affiché dans le pied de page"
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
            helperText="Ex. Valable 3 mois — affiché sous le numéro du devis"
          />
          <TextField
            label="Mentions complémentaires"
            multiline
            minRows={2}
            value={form.legal_extra ?? ''}
            onChange={(e) => setForm({ ...form, legal_extra: e.target.value })}
          />

          <Typography variant="subtitle1">Numérotation {year}</Typography>
          <Alert severity="info">
            Indiquez le dernier numéro déjà émis hors de l’application (ex. 120). Le prochain sera
            0121. Uniquement si aucun devis/facture de {year} n’a encore de numéro ici.
          </Alert>
          <Stack direction="row" spacing={1}>
            <TextField
              label="Dernier n° devis"
              value={lastQuote}
              onChange={(e) => setLastQuote(e.target.value)}
              disabled={!canSeed && (form.counters?.quote ?? 0) > 0}
              helperText={`Compteur actuel : ${form.counters?.quote ?? 0}`}
            />
            <TextField
              label="Dernier n° facture"
              value={lastInvoice}
              onChange={(e) => setLastInvoice(e.target.value)}
              disabled={!canSeed && (form.counters?.invoice ?? 0) > 0}
              helperText={`Compteur actuel : ${form.counters?.invoice ?? 0}`}
            />
          </Stack>

          <Typography variant="subtitle1">Logo</Typography>
          {form.has_logo && <BoxLogo />}
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
