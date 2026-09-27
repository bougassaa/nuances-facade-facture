import Alert from '@mui/material/Alert'
import Card from '@mui/material/Card'
import CardActionArea from '@mui/material/CardActionArea'
import CardContent from '@mui/material/CardContent'
import Fab from '@mui/material/Fab'
import Menu from '@mui/material/Menu'
import MenuItem from '@mui/material/MenuItem'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import AddIcon from '@mui/icons-material/Add'
import { useEffect, useState } from 'react'
import { Link as RouterLink, useNavigate } from 'react-router-dom'
import { api } from '../api/client'
import StatusChip from '../components/StatusChip'
import { formatDate, formatMoney, typeLabel } from '../format'
import type { Document } from '../types'

export default function HomePage() {
  const [items, setItems] = useState<Document[]>([])
  const [error, setError] = useState<string | null>(null)
  const [anchor, setAnchor] = useState<null | HTMLElement>(null)
  const navigate = useNavigate()

  useEffect(() => {
    api<{ items: Document[] }>('/documents')
      .then((r) => setItems(r.items))
      .catch((e) => setError(e instanceof Error ? e.message : 'Erreur'))
  }, [])

  return (
    <Stack spacing={2}>
      <Typography variant="h5">Documents</Typography>
      {error && <Alert severity="error">{error}</Alert>}
      {items.length === 0 && !error && (
        <Alert severity="info">Aucun document. Créez un devis ou une facture.</Alert>
      )}
      {items.map((doc) => (
        <Card key={doc.id}>
          <CardActionArea component={RouterLink} to={`/documents/${doc.id}`}>
            <CardContent>
              <Stack
                direction="row"
                spacing={1}
                sx={{ justifyContent: 'space-between', alignItems: 'center' }}
              >
                <Typography variant="subtitle1" sx={{ fontWeight: 600 }}>
                  {typeLabel(doc.doc_type)} {doc.number ?? 'brouillon'}
                </Typography>
                <StatusChip status={doc.status} docType={doc.doc_type} />
              </Stack>
              <Typography variant="body2" color="text.secondary">
                {doc.object?.trim() || '—'}
              </Typography>
              <Stack direction="row" sx={{ justifyContent: 'space-between', mt: 1 }}>
                <Typography variant="body2">{formatDate(doc.updated_at)}</Typography>
                <Typography variant="body1" sx={{ fontWeight: 600 }}>
                  {formatMoney(doc.total_ttc_cents)}
                </Typography>
              </Stack>
            </CardContent>
          </CardActionArea>
        </Card>
      ))}

      <Fab
        color="primary"
        aria-label="Nouveau"
        sx={{ position: 'fixed', bottom: 72, right: 16 }}
        onClick={(e) => setAnchor(e.currentTarget)}
      >
        <AddIcon />
      </Fab>
      <Menu anchorEl={anchor} open={Boolean(anchor)} onClose={() => setAnchor(null)}>
        <MenuItem
          onClick={() => {
            setAnchor(null)
            navigate('/documents/new?type=quote')
          }}
        >
          Nouveau devis
        </MenuItem>
        <MenuItem
          onClick={() => {
            setAnchor(null)
            navigate('/documents/new?type=invoice')
          }}
        >
          Nouvelle facture
        </MenuItem>
      </Menu>
    </Stack>
  )
}
