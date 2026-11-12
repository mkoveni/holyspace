import { describe, expect, it } from 'vitest'
import { formatRand, randsToMinor } from '@/payments/giving-amount'

describe('public giving amounts', () => {
  it.each([
    ['250', 25_000],
    ['250.5', 25_050],
    ['250,50', 25_050],
    ['5', 500],
    [' 1000000 ', 100_000_000],
    ['0.1', null],
    ['4.99', null],
    ['', null],
    ['-5', null],
    ['1e3', null],
    ['12.345', null],
    ['1000000.01', null],
    ['R250', null],
  ])('reads %j as %j cents', (input, expected) => {
    expect(randsToMinor(input)).toBe(expected)
  })

  it('formats cents as rand', () => {
    expect(formatRand(25_050).replace(/\s/g, ' ')).toMatch(/^R25 ?050[.,]50$|^R250[.,]50$/)
  })
})
