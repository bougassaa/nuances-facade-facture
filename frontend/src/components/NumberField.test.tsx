import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { describe, expect, it } from 'vitest'
import NumberField from './NumberField'

function Harness({ mode }: { mode: 'money' | 'quantity' | 'integer' }) {
  const [value, setValue] = useState(mode === 'money' ? '0,00' : '')
  return <NumberField label="Montant" mode={mode} value={value} onChange={setValue} />
}

describe('NumberField', () => {
  it('ouvre un clavier décimal et unifie point et virgule pour la monnaie', async () => {
    const user = userEvent.setup()
    render(<Harness mode="money" />)
    const input = screen.getByLabelText('Montant')
    expect(input).toHaveAttribute('inputmode', 'decimal')
    expect(input).toHaveAttribute('lang', 'fr')
    await user.clear(input)
    await user.paste('12.5')
    expect(input).toHaveValue('12,5')
    await user.tab()
    expect(input).toHaveValue('12,50')
  })

  it('garde la virgule d’une quantité', async () => {
    const user = userEvent.setup()
    render(<Harness mode="quantity" />)
    const input = screen.getByLabelText('Montant')
    expect(input).toHaveAttribute('inputmode', 'decimal')
    await user.type(input, '2,5')
    expect(input).toHaveValue('2,5')
  })

  it('ouvre un clavier numérique pour un entier', async () => {
    const user = userEvent.setup()
    render(<Harness mode="integer" />)
    const input = screen.getByLabelText('Montant')
    expect(input).toHaveAttribute('inputmode', 'numeric')
    await user.type(input, '12a0')
    expect(input).toHaveValue('120')
  })
})
