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
import StatusChip from '../components/StatusChip'
import {
  centsToEurosInput,
  eurosToCents,
  formatMoney,
  typeLabel,
} from '../format'
import type { Client, Document, DocumentLine } from '../types'

type LineForm = {
  key: string
  label: string
  quantity: string
  unit: string
  unit_price: string
  vat_rate_bp: number
}

const VAT_OPTIONS = [
  { value: 2000, label: '20 %' },
  { value: 1000, label: '10 %' },
  { value: 550, label: '5,5 %' },
  { value: 0, label: '0 %' },
]

function emptyLine(): LineForm {
  return {
    key: crypto.randomUUID(),
    label: '',
    quantity: '1',
    unit: 'u',
    unit_price: '0,00',
    vat_rate_bp: 2000,
  }
}

function formatQtyInput(qty: number | string): string {
  const n = typeof qty === 'number' ? qty : Number.parseFloat(String(qty).replace(',', '.'))
  if (Number.isNaN(n)) return '1'
  if (Math.abs(n - Math.round(n)) < 0.00001) return String(Math.round(n))
  return String(n).replace('.', ',')
}

function linesFromDoc(lines: DocumentLine[] | undefined): LineForm[] {
  if (!lines || lines.length === 0) return [emptyLine()]
  return lines.map((l) => ({
    key: crypto.randomUUID(),
    label: l.label,
    quantity: formatQtyInput(l.quantity),
    unit: l.unit,
    unit_price: centsToEurosInput(l.unit_price_ht_cents),
    vat_rate_bp: l.vat_rate_bp,
  }))
}

function toPayloadLines(lines: LineForm[]) {
  return lines
    .filter((l) => l.label.trim() !== '')
    .map((l) => ({
      label: l.label.trim(),
      quantity: Number.parseFloat(l.quantity.replace(',', '.')) || 0,
      unit: l.unit || 'u',
      unit_price_ht_cents: eurosToCents(l.unit_price),
      vat_rate_bp: l.vat_rate_bp,
    }))
}

export default function DocumentEditPage() {
  const { id } = useParams()
  const [search] = useSearchParams()
  const isNew = id === 'new'
  const navigate = useNavigate()

  const [clients, setClients] = useState<Client[]>([])
  const [client, setClient] = useState<Client | null>(null)
  const [docType, setDocType] = useState<'quote' | 'invoice'>(
    search.get('type') === 'invoice' ? 'invoice' : 'quote',
  )
  const [status, setStatus] = useState('draft')
  const [number, setNumber] = useState<string | null>(null)
  const [object, setObject] = useState('')
  const [notes, setNotes] = useState('')
  const [validUntil, setValidUntil] = useState('')
  const [lines, setLines] = useState<LineForm[]>([emptyLine()])
  const [totals, setTotals] = useState({ ht: 0, vat: 0, ttc: 0 })
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const editable = status === 'draft'

  useEffect(() => {
    api<{ items: Client[] }>('/clients')
      .then((r) => setClients(r.items))
      .catch((e) => setError(e instanceof Error ? e.message : 'Erreur'))
  }, [])

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
    let vat = 0
    for (const l of toPayloadLines(lines)) {
      const lineHt = Math.round(l.quantity * l.unit_price_ht_cents)
      const lineVat = Math.round((lineHt * l.vat_rate_bp) / 10000)
      ht += lineHt
      vat += lineVat
    }
    return { ht, vat, ttc: ht + vat }
  }, [lines])

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
      lines: toPayloadLines(lines),
    }
    try {
      if (isNew) {
        const created = await api<Document>('/documents', {
          method: 'POST',
          body: JSON.stringify(body),
        })
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
        body: JSON.stringify({}),
      })
      setStatus(sent.status)
      setNumber(sent.number)
      await downloadPdf(sent.id, sent.number ?? undefined)
      navigate(`/documents/${sent.id}`, { replace: true })
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erreur')
    } finally {
      setBusy(false)
    }
  }

  async function onStatus(next: string) {
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
        body: JSON.stringify({}),
      })
      navigate(`/documents/${invoice.id}`)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erreur')
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
        onChange={(_, v) => setClient(v)}
        disabled={!editable}
        renderInput={(params) => <TextField {...params} label="Client" required />}
      />

      <TextField
        label="Objet"
        value={object}
        onChange={(e) => setObject(e.target.value)}
        disabled={!editable}
      />

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
              <TextField
                label="Désignation"
                value={line.label}
                disabled={!editable}
                onChange={(e) => {
                  const next = [...lines]
                  next[index] = { ...line, label: e.target.value }
                  setLines(next)
                }}
              />
              <Stack direction="row" spacing={1}>
                <TextField
                  label="Qté"
                  value={line.quantity}
                  disabled={!editable}
                  onChange={(e) => {
                    const next = [...lines]
                    next[index] = { ...line, quantity: e.target.value }
                    setLines(next)
                  }}
                />
                <TextField
                  label="Unité"
                  value={line.unit}
                  disabled={!editable}
                  onChange={(e) => {
                    const next = [...lines]
                    next[index] = { ...line, unit: e.target.value }
                    setLines(next)
                  }}
                />
              </Stack>
              <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                <TextField
                  label="P.U. HT (€)"
                  value={line.unit_price}
                  disabled={!editable}
                  onChange={(e) => {
                    const next = [...lines]
                    next[index] = { ...line, unit_price: e.target.value }
                    setLines(next)
                  }}
                />
                <TextField
                  select
                  label="TVA"
                  value={line.vat_rate_bp}
                  disabled={!editable}
                  onChange={(e) => {
                    const next = [...lines]
                    next[index] = { ...line, vat_rate_bp: Number(e.target.value) }
                    setLines(next)
                  }}
                >
                  {VAT_OPTIONS.map((o) => (
                    <MenuItem key={o.value} value={o.value}>
                      {o.label}
                    </MenuItem>
                  ))}
                </TextField>
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
