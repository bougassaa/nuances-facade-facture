export function formatMoney(cents: number): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
  }).format(cents / 100)
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  const d = value.slice(0, 10)
  const [y, m, day] = d.split('-')
  if (!y || !m || !day) return value
  return `${day}/${m}/${y}`
}

export function eurosToCents(input: string): number {
  const normalized = input.replace(/\s/g, '').replace(',', '.')
  const n = Number.parseFloat(normalized)
  if (Number.isNaN(n)) return 0
  return Math.round(n * 100)
}

export function centsToEurosInput(cents: number): string {
  return (cents / 100).toFixed(2).replace('.', ',')
}

export function statusLabel(status: string, docType: string): string {
  switch (status) {
    case 'draft':
      return 'Brouillon'
    case 'sent':
      return docType === 'invoice' ? 'Envoyée' : 'Envoyé'
    case 'accepted':
      return 'Accepté'
    case 'rejected':
      return 'Refusé'
    default:
      return status
  }
}

export function typeLabel(docType: string): string {
  return docType === 'invoice' ? 'Facture' : 'Devis'
}

/** Libellé chantier prérempli : « Chantier NOM à VILLE ». */
export function chantierFromClient(client: { name: string; city: string }): string {
  const name = client.name.trim()
  const city = client.city.trim()
  if (!name) return ''
  if (!city) return `Chantier ${name}`
  return `Chantier ${name} à ${city}`
}
