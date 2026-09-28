import { describe, expect, it } from 'vitest'
import {
  centsToEurosInput,
  chantierFromClient,
  eurosToCents,
  formatDate,
  formatMoney,
  formatQuantityInput,
  parseQuantity,
  sanitizeDecimalInput,
  sanitizeIntegerInput,
  statusLabel,
  typeLabel,
} from './format'

describe('formatMoney', () => {
  it('affiche le format français avec euro', () => {
    expect(formatMoney(123456)).toMatch(/1[\s\u202f]234,56\s*€/)
  })

  it('gère zéro', () => {
    expect(formatMoney(0)).toMatch(/0,00\s*€/)
  })
})

describe('formatDate', () => {
  it('convertit YYYY-MM-DD en jj/mm/aaaa', () => {
    expect(formatDate('2026-09-22')).toBe('22/09/2026')
  })

  it('accepte un datetime', () => {
    expect(formatDate('2026-01-05 10:00:00')).toBe('05/01/2026')
  })

  it('renvoie tiret si vide', () => {
    expect(formatDate(null)).toBe('—')
    expect(formatDate(undefined)).toBe('—')
  })
})

describe('eurosToCents / centsToEurosInput', () => {
  it('parse virgule et point', () => {
    expect(eurosToCents('12,50')).toBe(1250)
    expect(eurosToCents('12.50')).toBe(1250)
    expect(eurosToCents('1 234,56')).toBe(123456)
  })

  it('arrondit au centime', () => {
    expect(eurosToCents('10,999')).toBe(1100)
  })

  it('traite le point et la virgule comme décimales, y compris les milliers', () => {
    expect(eurosToCents('12.5')).toBe(1250)
    expect(eurosToCents('1.234,56')).toBe(123456)
    expect(eurosToCents('1,234.56')).toBe(123456)
    expect(eurosToCents('')).toBe(0)
    expect(eurosToCents('abc')).toBe(0)
  })

  it('filtre la saisie décimale et entière', () => {
    expect(sanitizeDecimalInput('12.50', 2)).toBe('12,50')
    expect(sanitizeDecimalInput('12,', 2)).toBe('12,')
    expect(sanitizeDecimalInput('12a,5b9', 2)).toBe('12,59')
    expect(sanitizeDecimalInput('2,56789', 4)).toBe('2,5678')
    expect(sanitizeIntegerInput('12a0')).toBe('120')
  })

  it('parse une quantité sans perdre la partie décimale', () => {
    expect(parseQuantity('2,5')).toBe(2.5)
    expect(parseQuantity('2.5')).toBe(2.5)
    expect(parseQuantity('')).toBe(0)
    expect(formatQuantityInput(2.5)).toBe('2,5')
    expect(formatQuantityInput('2,')).toBe('2')
  })

  it('formate les centimes en saisie', () => {
    expect(centsToEurosInput(4500)).toBe('45,00')
  })
})

describe('labels', () => {
  it('libellés de statut sans paiement', () => {
    expect(statusLabel('draft', 'quote')).toBe('Brouillon')
    expect(statusLabel('sent', 'quote')).toBe('Envoyé')
    expect(statusLabel('sent', 'invoice')).toBe('Envoyée')
    expect(statusLabel('accepted', 'quote')).toBe('Accepté')
    expect(statusLabel('rejected', 'quote')).toBe('Refusé')
    expect(statusLabel('paid', 'invoice')).not.toBe('Payé')
  })

  it('libellés de type', () => {
    expect(typeLabel('quote')).toBe('Devis')
    expect(typeLabel('invoice')).toBe('Facture')
  })

  it('préremplit le chantier depuis le client', () => {
    expect(chantierFromClient({ name: 'M. Johan PASCAL', city: 'suze la rousse' })).toBe(
      'Chantier M. Johan PASCAL à suze la rousse',
    )
    expect(chantierFromClient({ name: 'M. BONNEFOUX', city: '' })).toBe('Chantier M. BONNEFOUX')
    expect(chantierFromClient({ name: '', city: 'Valréas' })).toBe('')
  })
})
