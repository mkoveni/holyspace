/** Pure amount helpers for public giving (no browser or API dependencies, so they are unit-testable in Node). */
const MINIMUM_MINOR = 500
const MAXIMUM_MINOR = 100_000_000

/** Rands typed by a person ("250", "250.5", "250,50") to exact integer cents; null when it is not a valid amount. */
export function randsToMinor (value: string): number | null {
  const text = value.trim().replace(',', '.')
  if (!/^\d{1,7}(?:\.\d{1,2})?$/.test(text)) {
    return null
  }
  const [whole, fraction = ''] = text.split('.')
  const minor = Number(whole) * 100 + Number(fraction.padEnd(2, '0'))
  return minor >= MINIMUM_MINOR && minor <= MAXIMUM_MINOR ? minor : null
}

export function formatRand (minor: number): string {
  return `R${(minor / 100).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}
