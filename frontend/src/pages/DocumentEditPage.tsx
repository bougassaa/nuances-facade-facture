import Alert from '@mui/material/Alert'
import Autocomplete from '@mui/material/Autocomplete'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Divider from '@mui/material/Divider'
import IconButton from '@mui/material/IconButton'
import MenuItem from '@mui/material/MenuItem'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import DeleteIcon from '@mui/icons-material/Delete'
import type { FormEvent } from 'react'
import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { api, downloadPdf } from '../api/client'
import NumberField, { keyboardSlot } from '../components/NumberField'
import StatusChip from '../components/StatusChip'
import {
  centsToEurosInput,
  chantierFromClient,
  eurosToCents,
  formatMoney,
  formatQuantityInput,
  parseQuantity,
  typeLabel,
} from '../format'
import type { Client, Company, Document, DocumentLine, LineDesignation } from '../types'

type LineForm = {
  key: string
  label: string
  quantity: string
  unit: string
  unit_price: string
}

const VAT_OPTIONS = [
  { value: 2000, label: '20 %' },
  { value: 1000, label: '10 %' },
  { value: 550, label: '5,5 %' },
  { value: 0, label: '0 %' },
]

const UNIT_OPTIONS = [
  { value: 'm²', menu: 'm²' },
  { value: 'ml', menu: 'ml (mètre linéaire)' },
  { value: 'u', menu: 'u (unité)' },
  { value: 'forfait', menu: 'forfait' },
] as const

function normalizeUnit(raw: string): string {
  const value = raw.trim()
  const key = value.toLowerCase()
  if (key === 'm2' || key === 'm²') return 'm²'
  if (key === 'ml') return 'ml'
  if (key === 'u' || key === 'unité' || key === 'unite') return 'u'
  if (key === 'forfait') return 'forfait'
  return value || 'm²'
}

function unitOptionsFor(current: string) {
  if (UNIT_OPTIONS.some((option) => option.value === current)) return UNIT_OPTIONS
  return [...UNIT_OPTIONS, { value: current, menu: current }]
}

function emptyLine(): LineForm {
  return {
    key: crypto.randomUUID(),
    label: '',
    quantity: '1',
    unit: 'm²',
    unit_price: '0,00',
  }
}

function linesFromDoc(lines: DocumentLine[] | undefined): LineForm[] {
  if (!lines || lines.length === 0) return [emptyLine()]
  return lines.map((l) => ({
    key: crypto.randomUUID(),
    label: l.label,
    quantity: formatQuantityInput(l.quantity),
    unit: normalizeUnit(l.unit),
    unit_price: centsToEurosInput(l.unit_price_ht_cents),
  }))
}

function toPayloadLines(lines: LineForm[]) {
  return lines
    .filter((l) => l.label.trim() !== '')
    .map((l) => ({
      label: l.label.trim(),
      quantity: parseQuantity(l.quantity),
      unit: normalizeUnit(l.unit),
      unit_price_ht_cents: eurosToCents(l.unit_price),
    }))
}

function siteFromClient(c: Client) {
  return {
    site_address_line1: c.address_line1 ?? '',
    site_address_line2: c.address_line2 ?? '',
    site_postal_code: c.postal_code ?? '',
    site_city: c.city ?? '',
  }
}

export default function DocumentEditPage() {
  const { id } = useParams()
  const [search] = useSearchParams()
  const isNew = id === 'new'
  const navigate = useNavigate()

  const [clients, setClients] = useState<Client[]>([])
  const [designations, setDesignations] = useState<LineDesignation[]>([])
  const [client, setClient] = useState<Client | null>(null)
  const [vatExempt, setVatExempt] = useState(false)
  const [docType, setDocType] = useState<'quote' | 'invoice'>(
    search.get('type') === 'invoice' ? 'invoice' : 'quote',
  )
  const [status, setStatus] = useState('draft')
  const [number, setNumber] = useState<string | null>(null)
  const [object, setObject] = useState('')
  const [notes, setNotes] = useState('')
  const [validUntil, setValidUntil] = useState('')
  const [siteAddress1, setSiteAddress1] = useState('')
  const [siteAddress2, setSiteAddress2] = useState('')
  const [sitePostal, setSitePostal] = useState('')
  const [siteCity, setSiteCity] = useState('')
  const [vatRateBp, setVatRateBp] = useState(2000)
  const [depositAmount, setDepositAmount] = useState('0,00')
  const [deductionLabel, setDeductionLabel] = useState('')
  const [deductionAmount, setDeductionAmount] = useState('0,00')
  const [lines, setLines] = useState<LineForm[]>([emptyLine()])
  const [totals, setTotals] = useState({ ht: 0, vat: 0, ttc: 0 })
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const editable = status === 'draft'
  const effectiveVatRate = vatExempt ? 0 : vatRateBp

  useEffect(() => {
    api<{ items: Client[] }>('/clients')
      .then((r) => setClients(r.items))
      .catch((e) => setError(e instanceof Error ? e.message : 'Erreur'))
    api<{ items: LineDesignation[] }>('/designations')
      .then((r) => setDesignations(r.items))
      .catch(() => {
        /* catalogue optionnel au chargement */
      })
    api<Company>('/company')
      .then((c) => {
        const exempt = Boolean(c.vat_exempt)
        setVatExempt(exempt)
        if (exempt) setVatRateBp(0)
      })
      .catch(() => {
        /* réglages optionnels pour l’aperçu TVA */
      })
  }, [])

  async function refreshDesignations() {
    try {
      const r = await api<{ items: LineDesignation[] }>('/designations')
      setDesignations(r.items)
    } catch {
      /* ignore */
    }
  }

  useEffect(() => {
    if (isNew) return
    api<Document>(`/documents/${id}`)
      .then(async (doc) => {
        setDocType(doc.doc_type)
        setStatus(doc.status)
        setNumber(doc.number)
        setObject(doc.object)
        setNotes(doc.notes ?? '')
        setValidUntil(doc.valid_until ?? '')
        setSiteAddress1(doc.site_address_line1 ?? '')
        setSiteAddress2(doc.site_address_line2 ?? '')
        setSitePostal(doc.site_postal_code ?? '')
        setSiteCity(doc.site_city ?? '')
        setVatRateBp(doc.vat_rate_bp ?? 2000)
        setDepositAmount(centsToEurosInput(doc.deposit_ttc_cents ?? 0))
        setDeductionLabel(doc.deduction_label ?? '')
        setDeductionAmount(centsToEurosInput(doc.deduction_ttc_cents ?? 0))
        setLines(linesFromDoc(doc.lines))
        setTotals({
          ht: doc.total_ht_cents,
          vat: doc.total_vat_cents,
          ttc: doc.total_ttc_cents,
        })
        const c = await api<Client>(`/clients/${doc.client_id}`)
        setClient(c)
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Erreur'))
  }, [id, isNew])

  const previewTotals = useMemo(() => {
    let ht = 0
    for (const l of toPayloadLines(lines)) {
      ht += Math.round(l.quantity * l.unit_price_ht_cents)
    }
    const vat = Math.round((ht * effectiveVatRate) / 10000)
    return { ht, vat, ttc: ht + vat }
  }, [lines, effectiveVatRate])

  const depositCents = useMemo(() => eurosToCents(depositAmount), [depositAmount])
  const deductionCents = useMemo(() => eurosToCents(deductionAmount), [deductionAmount])
  const remainingCents = Math.max(0, (editable ? previewTotals.ttc : totals.ttc) - deductionCents)

  function applyClientSite(c: Client | null) {
    if (!c) return
    const site = siteFromClient(c)
    setSiteAddress1(site.site_address_line1)
    setSiteAddress2(site.site_address_line2)
    setSitePostal(site.site_postal_code)
    setSiteCity(site.site_city)
  }

  function applyClientSelection(c: Client | null) {
    setClient(c)
    if (!editable || !c) return
    setObject(chantierFromClient(c))
    applyClientSite(c)
  }

  async function save(e?: FormEvent) {
    e?.preventDefault()
    if (!client) {
      setError('Choisissez un client')
      return null
    }
    setBusy(true)
    setError(null)
    const body = {
      doc_type: docType,
      client_id: client.id,
      object,
      notes,
      valid_until: validUntil || null,
      site_address_line1: siteAddress1,
      site_address_line2: siteAddress2,
      site_postal_code: sitePostal,
      site_city: siteCity,
      vat_rate_bp: effectiveVatRate,
      deposit_ttc_cents: docType === 'quote' ? eurosToCents(depositAmount) : 0,
      deduction_label: docType === 'invoice' ? deductionLabel : '',
      deduction_ttc_cents: docType === 'invoice' ? eurosToCents(deductionAmount) : 0,
      lines: toPayloadLines(lines),
    }
    try {
      if (isNew) {
        const created = await api<Document>('/documents', {
          method: 'POST',
          body: JSON.stringify(body),
        })
        await refreshDesignations()
        navigate(`/documents/${created.id}`, { replace: true })
        return created
      }
      const updated = await api<Document>(`/documents/${id}`, {
        method: 'PUT',
        body: JSON.stringify(body),
      })
      setTotals({
        ht: updated.total_ht_cents,
        vat: updated.total_vat_cents,
        ttc: updated.total_ttc_cents,
      })
      await refreshDesignations()
      return updated
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erreur')
      return null
    } finally {
      setBusy(false)
    }
  }

  async function onSend() {
    let docId = isNew ? null : Number(id)
    if (editable) {
      const saved = await save()
      if (!saved) return
      docId = saved.id
    }
    if (!docId) return
    setBusy(true)
    try {
      const sent = await api<Document>(`/documents/${docId}/send`, {
        method: 'POST',
        body: '{}',
      })
      setStatus(sent.status)
      setNumber(sent.number)
      setTotals({
        ht: sent.total_ht_cents,
        vat: sent.total_vat_cents,
        ttc: sent.total_ttc_cents,
      })
      await downloadPdf(sent.id, sent.number ?? undefined)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erreur')
    } finally {
      setBusy(false)
    }
  }

  async function onStatus(next: 'accepted' | 'rejected') {
    setBusy(true)
    try {
      const updated = await api<Document>(`/documents/${id}/status`, {
        method: 'POST',
        body: JSON.stringify({ status: next }),
      })
      setStatus(updated.status)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erreur')
    } finally {
      setBusy(false)
    }
  }

  async function onConvert() {
    setBusy(true)
    try {
      const invoice = await api<Document>(`/documents/${id}/convert`, {
        method: 'POST',
        body: '{}',
      })
      navigate(`/documents/${invoice.id}`)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erreur')
    } finally {
      setBusy(false)
    }
  }

  const displayTotals = editable ? previewTotals : totals

  return (
    <Stack spacing={2} component="form" onSubmit={save}>
      <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}>
        <Typography variant="h5">
          {typeLabel(docType)} {number ?? ''}
        </Typography>
        {!isNew && <StatusChip status={status} docType={docType} />}
      </Stack>
      {error && <Alert severity="error">{error}</Alert>}

      <Autocomplete
        options={clients}
        getOptionLabel={(o) => o.name}
        value={client}
        onChange={(_, v) => applyClientSelection(v)}
        disabled={!editable}
        renderInput={(params) => <TextField {...params} label="Client" required />}
      />

      <TextField
        label="Chantier"
        value={object}
        onChange={(e) => setObject(e.target.value)}
        disabled={!editable}
        helperText="Prérempli au choix du client, modifiable"
      />

      <Typography variant="subtitle1">Adresse du projet</Typography>
      <TextField
        label="Adresse"
        value={siteAddress1}
        onChange={(e) => setSiteAddress1(e.target.value)}
        disabled={!editable}
      />
      <TextField
        label="Complément"
        value={siteAddress2}
        onChange={(e) => setSiteAddress2(e.target.value)}
        disabled={!editable}
      />
      <Stack direction="row" spacing={1}>
        <TextField
          label="Code postal"
          value={sitePostal}
          onChange={(e) => setSitePostal(e.target.value)}
          disabled={!editable}
          slotProps={keyboardSlot('numeric')}
        />
        <TextField
          label="Ville"
          value={siteCity}
          onChange={(e) => setSiteCity(e.target.value)}
          disabled={!editable}
        />
      </Stack>
      {editable && client && (
        <Button variant="text" onClick={() => applyClientSite(client)}>
          Reprendre l’adresse du client
        </Button>
      )}

      {docType === 'quote' && (
        <TextField
          label="Valable jusqu’au"
          type="date"
          slotProps={{ inputLabel: { shrink: true } }}
          value={validUntil}
          onChange={(e) => setValidUntil(e.target.value)}
          disabled={!editable}
        />
      )}

      <Typography variant="subtitle1">Lignes</Typography>
      {lines.map((line, index) => (
        <Card key={line.key} variant="outlined">
          <CardContent>
            <Stack spacing={1.5}>
              <Autocomplete
                freeSolo
                options={designations}
                getOptionLabel={(option) =>
                  typeof option === 'string' ? option : option.label
                }
                filterOptions={(options, state) => {
                  const q = state.inputValue.trim().toLowerCase()
                  if (!q) return options
                  return options.filter((o) => o.label.toLowerCase().includes(q))
                }}
                value={line.label}
                inputValue={line.label}
                disabled={!editable}
                onInputChange={(_, value, reason) => {
                  if (reason === 'reset') return
                  const next = [...lines]
                  next[index] = { ...line, label: value }
                  setLines(next)
                }}
                onChange={(_, value) => {
                  const next = [...lines]
                  if (typeof value === 'string') {
                    next[index] = { ...line, label: value }
                  } else if (value) {
                    next[index] = {
                      ...line,
                      label: value.label,
                      unit_price: centsToEurosInput(value.unit_price_ht_cents),
                    }
                  } else {
                    next[index] = { ...line, label: '' }
                  }
                  setLines(next)
                }}
                renderOption={(props, option) => (
                  <li {...props} key={option.id}>
                    {option.label}
                    {' — '}
                    {formatMoney(option.unit_price_ht_cents)}
                  </li>
                )}
                renderInput={(params) => (
                  <TextField {...params} label="Désignation" />
                )}
              />
              <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                <NumberField
                  label="Qté"
                  mode="quantity"
                  value={line.quantity}
                  disabled={!editable}
                  onChange={(quantity) => {
                    const next = [...lines]
                    next[index] = { ...line, quantity }
                    setLines(next)
                  }}
                />
                <TextField
                  select
                  label="Unité"
                  value={line.unit}
                  disabled={!editable}
                  onChange={(e) => {
                    const next = [...lines]
                    next[index] = { ...line, unit: e.target.value }
                    setLines(next)
                  }}
                  slotProps={{
                    select: {
                      renderValue: (selected) => String(selected),
                    },
                  }}
                >
                  {unitOptionsFor(line.unit).map((option) => (
                    <MenuItem key={option.value} value={option.value}>
                      {option.menu}
                    </MenuItem>
                  ))}
                </TextField>
                <NumberField
                  label="P.U. HT (€)"
                  mode="money"
                  value={line.unit_price}
                  disabled={!editable}
                  onChange={(unit_price) => {
                    const next = [...lines]
                    next[index] = { ...line, unit_price }
                    setLines(next)
                  }}
                />
                {editable && (
                  <IconButton
                    aria-label="Supprimer la ligne"
                    onClick={() => setLines(lines.filter((_, i) => i !== index))}
                    disabled={lines.length === 1}
                  >
                    <DeleteIcon />
                  </IconButton>
                )}
              </Stack>
            </Stack>
          </CardContent>
        </Card>
      ))}

      {editable && (
        <Button variant="outlined" onClick={() => setLines([...lines, emptyLine()])}>
          Ajouter une ligne
        </Button>
      )}

      <Divider />

      {(!vatExempt || docType === 'quote') && (
        <Stack direction="row" spacing={1}>
          {!vatExempt && (
            <TextField
              select
              label="TVA"
              value={vatRateBp}
              disabled={!editable}
              onChange={(e) => setVatRateBp(Number(e.target.value))}
              sx={{ flex: 1 }}
            >
              {VAT_OPTIONS.map((o) => (
                <MenuItem key={o.value} value={o.value}>
                  {o.label}
                </MenuItem>
              ))}
            </TextField>
          )}
          {docType === 'quote' && (
            <NumberField
              label="Acompte (€)"
              mode="money"
              value={depositAmount}
              onChange={setDepositAmount}
              disabled={!editable}
              sx={{ flex: 1 }}
            />
          )}
        </Stack>
      )}

      {docType === 'invoice' && (
        <Stack spacing={1.5}>
          <TextField
            label="Libellé de déduction"
            value={deductionLabel}
            onChange={(e) => setDeductionLabel(e.target.value)}
            disabled={!editable}
            placeholder="Acompte fournitures"
          />
          <NumberField
            label="Montant déduit TTC (€)"
            mode="money"
            value={deductionAmount}
            onChange={setDeductionAmount}
            disabled={!editable}
            helperText="0 = pas de déduction sur le PDF"
          />
        </Stack>
      )}

      <TextField
        label="Notes"
        multiline
        minRows={2}
        value={notes}
        onChange={(e) => setNotes(e.target.value)}
        disabled={!editable}
      />

      <Divider />
      <Stack spacing={0.5}>
        <Typography>Total HT : {formatMoney(displayTotals.ht)}</Typography>
        <Typography>TVA : {formatMoney(displayTotals.vat)}</Typography>
        <Typography sx={{ fontWeight: 700 }}>Total TTC : {formatMoney(displayTotals.ttc)}</Typography>
        {docType === 'quote' && depositCents > 0 && (
          <Typography>Acompte : {formatMoney(depositCents)}</Typography>
        )}
        {docType === 'invoice' && deductionCents > 0 && (
          <>
            <Typography>
              {deductionLabel || 'Acompte'} : − {formatMoney(deductionCents)}
            </Typography>
            <Typography sx={{ fontWeight: 700 }}>Reste à payer : {formatMoney(remainingCents)}</Typography>
          </>
        )}
      </Stack>

      {editable && (
        <Button type="submit" variant="contained" disabled={busy}>
          Enregistrer
        </Button>
      )}

      {editable && (
        <Button variant="contained" color="secondary" disabled={busy} onClick={onSend}>
          Envoyer (numéro + PDF)
        </Button>
      )}

      {!editable && (
        <Button
          variant="outlined"
          disabled={busy}
          onClick={() => downloadPdf(Number(id), number ?? undefined)}
        >
          Télécharger le PDF
        </Button>
      )}

      {docType === 'quote' && status === 'sent' && (
        <Stack direction="row" spacing={1}>
          <Button variant="contained" color="success" disabled={busy} onClick={() => onStatus('accepted')}>
            Accepté
          </Button>
          <Button variant="outlined" color="error" disabled={busy} onClick={() => onStatus('rejected')}>
            Refusé
          </Button>
        </Stack>
      )}

      {docType === 'quote' && status === 'accepted' && (
        <Button variant="contained" disabled={busy} onClick={onConvert}>
          Convertir en facture
        </Button>
      )}

      {editable && !isNew && (
        <Button
          color="error"
          disabled={busy}
          onClick={async () => {
            if (!window.confirm('Supprimer ce brouillon ?')) return
            await api(`/documents/${id}`, { method: 'DELETE' })
            navigate('/')
          }}
        >
          Supprimer le brouillon
        </Button>
      )}
    </Stack>
  )
}
