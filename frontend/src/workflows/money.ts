export function currencyDigits (currency: string): number {
  return new Intl.NumberFormat('en', { style: 'currency', currency }).resolvedOptions().maximumFractionDigits ?? 2
}
export function amountMinor (value: unknown, currency: string): number {
  const digits = currencyDigits(currency)
  const text = String(value).trim()
  const expression = digits === 0 ? /^\d+$/ : new RegExp(String.raw`^\d+(?:\.\d{1,${digits}})?$`)
  if (!expression.test(text)) {
    throw new Error(`Enter a positive ${currency} amount with at most ${digits} decimal places.`)
  }
  const [whole, fraction = ''] = text.split('.')
  const amount = Number(whole) * 10 ** digits + Number(fraction.padEnd(digits, '0'))
  if (!Number.isSafeInteger(amount) || amount <= 0 || amount > 1_000_000_000_000) {
    throw new Error('Enter a valid positive amount.')
  }
  return amount
}
export function moneyLabel (minor: number, currency: string): string {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(minor / 10 ** currencyDigits(currency))
}
