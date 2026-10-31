export const currencies = ['ZAR', 'USD', 'EUR', 'GBP']
export function parseMinor (
  value: string,
  maximum = 1_000_000_000_000,
): number | null {
  const text = value.trim()
  if (!/^\d{1,13}(?:\.\d{1,2})?$/.test(text)) {
    return null
  }
  const [whole, fraction = ''] = text.split('.')
  const minor = Number(whole) * 100 + Number(fraction.padEnd(2, '0'))
  return Number.isSafeInteger(minor) && minor > 0 && minor <= maximum
    ? minor
    : null
}
export function money (value: number | string, currency: string): string {
  const minor = BigInt(value),
    negative = minor < 0n,
    absolute = negative ? -minor : minor
  const whole = (absolute / 100n).toLocaleString('en-ZA')
  return `${negative ? '-' : ''}${currency} ${whole}.${String(absolute % 100n).padStart(2, '0')}`
}
export function today (): string {
  const date = new Date()
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

export function dateLabel (value: string): string {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
    new Date(value + 'T12:00:00'),
  )
}
