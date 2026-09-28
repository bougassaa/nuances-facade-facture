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

/** Chaîne canonique « 1234.56 ». Virgule et point sont des décimales ; si les deux sont présents, le dernier est la décimale. */
export function normalizeDecimalString(raw: string): string {
  let s = raw.replace(/[\s\u00a0\u202f]/g, '')
  if (s === '') return ''
  const negative = s.startsWith('-')
  if (negative) s = s.slice(1)
  const lastComma = s.lastIndexOf(',')
  const lastDot = s.lastIndexOf('.')
  if (lastComma >= 0 && lastDot >= 0) {
    const dec = Math.max(lastComma, lastDot)
    const intPart = s.slice(0, dec).replace(/[.,]/g, '')
    const frac = s.slice(dec + 1).replace(/[.,]/g, '')
    s = `${intPart}.${frac}`
  } else {
    s = s.replace(',', '.')
  }
  s = s.replace(/[^\d.]/g, '')
  const dot = s.indexOf('.')
  if (dot >= 0) {
    s = s.slice(0, dot + 1) + s.slice(dot + 1).replace(/\./g, '')
  }
  if (s === '' || s === '.') return ''
  return negative ? `-${s}` : s
}

export function eurosToCents(input: string): number {
  const normalized = normalizeDecimalString(input)
  if (normalized === '') return 0
  const negative = normalized.startsWith('-')
  const unsigned = negative ? normalized.slice(1) : normalized
  const [intRaw, fracRaw = ''] = unsigned.split('.')
  const intPart = intRaw === '' ? 0 : Number(intRaw)
  if (!Number.isFinite(intPart)) return 0
  const frac = (fracRaw + '00').slice(0, 3)
  let cents = intPart * 100 + Number(frac.slice(0, 2))
  if (Number(frac[2] ?? '0') >= 5) cents += 1
  return negative ? -cents : cents
}

/** Saisie affichée avec une virgule. `maxFractionDigits` tronque sans arrondir (l’arrondi se fait au blur / à l’envoi). */
export function sanitizeDecimalInput(raw: string, maxFractionDigits: number): string {
  const compact = raw.replace(/[\s\u00a0\u202f]/g, '').replace(/[^\d.,]/g, '')
  if (compact === '') return ''
  const trailingSep = /[.,]$/.test(compact)
  const normalized = normalizeDecimalString(compact)
  if (normalized === '') return trailingSep ? '0,' : ''
  const [intPart, frac = ''] = normalized.split('.')
  const fracCut = frac.slice(0, maxFractionDigits)
  if (trailingSep && fracCut === '') return `${intPart},`
  if (!normalized.includes('.')) return intPart
  return `${intPart},${fracCut}`
}

export function sanitizeIntegerInput(raw: string): string {
  return raw.replace(/\D/g, '')
}

export function parseQuantity(input: string): number {
  const normalized = normalizeDecimalString(input)
  if (normalized === '') return 0
  const n = Number(normalized)
  if (!Number.isFinite(n) || n < 0) return 0
  return Math.round(n * 10000) / 10000
}

export function formatQuantityInput(input: string | number): string {
  if (typeof input === 'number') {
    if (!Number.isFinite(input)) return ''
    const rounded = Math.round(input * 10000) / 10000
    if (Math.abs(rounded - Math.round(rounded)) < 1e-9) return String(Math.round(rounded))
    return String(rounded).replace('.', ',')
  }
  const sanitized = sanitizeDecimalInput(input, 4).replace(/,$/, '')
  if (sanitized === '') return ''
  return formatQuantityInput(parseQuantity(sanitized))
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
