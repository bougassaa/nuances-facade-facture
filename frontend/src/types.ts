export type LineDesignation = {
  id: number
  label: string
  unit_price_ht_cents: number
}

export type Client = {
  id: number
  name: string
  address_line1: string
  address_line2: string
  postal_code: string
  city: string
  email: string
  phone: string
  vat_number: string
  notes: string | null
}

export type DocumentLine = {
  id?: number
  label: string
  quantity: number | string
  unit: string
  unit_price_ht_cents: number
  vat_rate_bp?: number
  line_ht_cents?: number
  line_vat_cents?: number
}

export type Document = {
  id: number
  doc_type: 'quote' | 'invoice'
  status: 'draft' | 'sent' | 'accepted' | 'rejected'
  number: string | null
  client_id: number
  client_name?: string
  source_document_id: number | null
  issue_date: string | null
  valid_until: string | null
  object: string
  notes: string | null
  site_address_line1: string
  site_address_line2: string
  site_postal_code: string
  site_city: string
  vat_rate_bp: number
  deposit_ttc_cents: number
  deduction_label: string
  deduction_ttc_cents: number
  remaining_due_cents?: number
  total_ht_cents: number
  total_vat_cents: number
  total_ttc_cents: number
  created_at: string
  updated_at: string
  sent_at: string | null
  lines?: DocumentLine[]
}

export type Company = {
  id: number
  name: string
  address_line1: string
  address_line2: string
  postal_code: string
  city: string
  phone: string
  email: string
  siret: string
  vat_number: string
  iban: string
  bic: string
  website: string
  legal_form: string
  payment_terms: string | null
  vat_exempt: number | boolean
  legal_decennale: string | null
  legal_late_penalties: string | null
  legal_recovery_fee: string | null
  legal_quote_validity: string | null
  legal_extra: string | null
  has_logo: boolean
  counters?: { year: number; quote: number; invoice: number }
  vat_rates: { id: number; rate_bp: number; label: string; is_default: number }[]
}
