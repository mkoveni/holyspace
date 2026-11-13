export { moneyLabel } from '@/workflows/money'

/** Short calendar date such as "13 Nov 2026". Empty or invalid values give an em dash. */
export function formatDate (value?: string | null): string {
  if (!value) {
    return '—'
  }
  const date = new Date(value)
  return Number.isNaN(date.getTime())
    ? '—'
    : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

/** Date with a time of day, for audit trails and deliveries. */
export function formatDateTime (value?: string | null): string {
  if (!value) {
    return '—'
  }
  const date = new Date(value)
  return Number.isNaN(date.getTime())
    ? '—'
    : date.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

/** "Ada Lovelace" -> "AL", for avatars and monograms. */
export function initialsOf (name?: string | null, fallback = '?'): string {
  const letters = (name ?? '')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(part => part[0])
    .join('')
    .toUpperCase()
  return letters || fallback
}
