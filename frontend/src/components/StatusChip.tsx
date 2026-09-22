import Chip from '@mui/material/Chip'
import { statusLabel } from '../format'

const colorMap: Record<string, 'default' | 'warning' | 'info' | 'success' | 'error'> = {
  draft: 'default',
  sent: 'info',
  accepted: 'success',
  rejected: 'error',
}

export default function StatusChip({
  status,
  docType,
}: {
  status: string
  docType: string
}) {
  return (
    <Chip
      size="small"
      label={statusLabel(status, docType)}
      color={colorMap[status] ?? 'default'}
    />
  )
}
