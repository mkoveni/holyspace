import { describe, expect, it } from 'vitest'
import { priceToMinor, totalFor } from '@/tickets/fees'

const yocoCore = { effectiveRatePpm: 33_925, fixedMinor: 0 }

describe('ticket fee preview', () => {
  it('matches the server for the documented R200 example', () => {
    expect(totalFor(20_000, yocoCore)).toBe(20_703)
  })

  it('matches the server for a mixed order', () => {
    expect(totalFor(120_000, yocoCore)).toBe(124_214)
  })

  it('charges nothing for a free order, even with a fixed fee', () => {
    expect(totalFor(0, { effectiveRatePpm: 33_925, fixedMinor: 100 })).toBe(0)
  })

  it('grosses up a fixed fee', () => {
    expect(totalFor(1000, { effectiveRatePpm: 0, fixedMinor: 100 })).toBe(1100)
  })
})

describe('staff ticket prices', () => {
  it.each([
    ['0', 0],
    ['200', 20_000],
    ['200,5', 20_050],
    ['4.99', null],
    ['', null],
    ['-1', null],
  ])('reads %j as %j cents', (input, expected) => {
    expect(priceToMinor(input)).toBe(expected)
  })
})
