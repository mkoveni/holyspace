import { describe, expect, it } from 'vitest'
import { zarMinorAmount } from '../src/payments/money'

describe('ZAR checkout and refund amounts', () => {
  it('preserves exact cents rather than rounding a floating-point amount', () => {
    expect(zarMinorAmount('123.45')).toBe(12_345)
    expect(zarMinorAmount('0.29', 1)).toBe(29)
    expect(zarMinorAmount(' 5.1 ')).toBe(510)
  })
  it('rejects ambiguous notation and fractional cents', () => {
    for (const value of ['1e4', '-10', 'R100', '1,000', '100.001', '', 'Infinity']) {
      expect(zarMinorAmount(value)).toBeNull()
    }
  })
  it('enforces checkout minimums and permits exact smaller refunds', () => {
    expect(zarMinorAmount('4.99')).toBeNull()
    expect(zarMinorAmount('5.00')).toBe(500)
    expect(zarMinorAmount('0.01', 1)).toBe(1)
    expect(zarMinorAmount('1000000.00')).toBe(100_000_000)
    expect(zarMinorAmount('1000000.01')).toBeNull()
  })
})
