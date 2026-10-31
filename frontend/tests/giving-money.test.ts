import { describe, expect, it } from 'vitest'
import { money, parseMinor } from '../src/giving/money'

describe('Giving money input and display', () => {
  it('converts decimal strings into exact integer cents', () => {
    expect(parseMinor('123.45')).toBe(12345)
    expect(parseMinor('0.01')).toBe(1)
    expect(parseMinor('1.2')).toBe(120)
    expect(parseMinor('10000000000.00')).toBe(1_000_000_000_000)
  })
  it('supports bounded batch totals without changing individual gift limits', () => {
    expect(parseMinor('1000000000000.00', 100_000_000_000_000)).toBe(100_000_000_000_000)
    expect(parseMinor('1000000000000.01', 100_000_000_000_000)).toBeNull()
    expect(parseMinor('1000000000000.00')).toBeNull()
  })
  it('rejects rounding, exponents, negative amounts and oversized inputs', () => {
    for (const value of ['', '0', '-1', '1.001', '1e3', '1,000', '10000000000.01']) {
      expect(parseMinor(value)).toBeNull()
    }
  })
  it('preserves aggregate precision beyond the safe number range', () => {
    expect(money('900719925474099312', 'ZAR').replace(/\s/g, '')).toBe('ZAR9007199254740993.12')
    expect(money('-10001', 'USD')).toBe('-USD 100.01')
  })
})
