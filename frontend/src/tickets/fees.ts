/** The buyer's processing-fee preview. Mirrors the server's integer maths; only the server's number is ever charged. */
export interface TicketFees {
  effectiveRatePpm: number
  fixedMinor: number
}

const MILLION = 1_000_000

/** What the buyer pays for a ticket subtotal (cents): the church keeps the price after the provider takes its share. */
export function totalFor (subtotalMinor: number, fees: TicketFees): number {
  if (subtotalMinor <= 0) {
    return 0
  }
  const denominator = MILLION - fees.effectiveRatePpm
  return Math.floor(((subtotalMinor + fees.fixedMinor) * MILLION + denominator - 1) / denominator)
}

/** Rands typed by staff ("200", "200.50") to cents; null when invalid. Free is "0". */
export function priceToMinor (value: string): number | null {
  const text = value.trim().replace(',', '.')
  if (!/^\d{1,7}(?:\.\d{1,2})?$/.test(text)) {
    return null
  }
  const [whole, fraction = ''] = text.split('.')
  const minor = Number(whole) * 100 + Number(fraction.padEnd(2, '0'))
  return minor === 0 || (minor >= 500 && minor <= 100_000_000) ? minor : null
}
