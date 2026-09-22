import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import StatusChip from './StatusChip'

describe('StatusChip', () => {
  it('affiche Brouillon', () => {
    render(<StatusChip status="draft" docType="quote" />)
    expect(screen.getByText('Brouillon')).toBeInTheDocument()
  })

  it('affiche Envoyée pour une facture', () => {
    render(<StatusChip status="sent" docType="invoice" />)
    expect(screen.getByText('Envoyée')).toBeInTheDocument()
  })

  it('affiche Accepté', () => {
    render(<StatusChip status="accepted" docType="quote" />)
    expect(screen.getByText('Accepté')).toBeInTheDocument()
  })

  it('n’affiche jamais Payé', () => {
    const { container } = render(<StatusChip status="sent" docType="invoice" />)
    expect(container.textContent).not.toMatch(/payé|paiement/i)
  })
})
