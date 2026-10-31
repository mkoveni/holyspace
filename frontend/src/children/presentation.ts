export function required (value: unknown) {
  return (
    (value !== null
      && value !== undefined
      && String(value).trim().length > 0)
    || 'This field is required.'
  )
}
export function positive (value: unknown) {
  return (
    (Number.isInteger(Number(value)) && Number(value) > 0)
    || 'Enter a whole number greater than zero.'
  )
}
export function dayAfter (day: string): string {
  const date = new Date(day + 'T12:00:00Z')
  date.setUTCDate(date.getUTCDate() + 1)
  return date.toISOString().slice(0, 10)
}
export function lastDay (day: string): string {
  const date = new Date(day + 'T12:00:00Z')
  date.setUTCDate(date.getUTCDate() - 1)
  return date.toISOString().slice(0, 10)
}
export function localInput (date = new Date()): string {
  return new Date(date.getTime() - date.getTimezoneOffset() * 60_000)
    .toISOString()
    .slice(0, 16)
}
export function instant (value: string): string {
  if (!value || Number.isNaN(Date.parse(value))) {
    throw new Error('Choose a valid date and time.')
  }
  return new Date(value).toISOString()
}
export function timeLabel (value: string, timeZone?: string): string {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone,
  }).format(new Date(value))
}
export function sessionRange (day: string): { from: string, until: string } {
  return {
    from: new Date(day + 'T00:00:00').toISOString(),
    until: new Date(dayAfter(day) + 'T00:00:00').toISOString(),
  }
}
export function safeLink (value: string): string | undefined {
  try {
    const url = new URL(value)
    return ['https:', 'http:'].includes(url.protocol) ? url.href : undefined
  } catch {
    return undefined
  }
}
export function statusLabel (value: string): string {
  return (
    (
      {
        PLANNED: 'Planned',
        OPEN: 'Open for arrivals',
        NEEDS_REVIEW: 'Staffing needs attention',
        CLOSED: 'Closed',
        CANCELLED: 'Cancelled',
      } as Record<string, string>
    )[value] ?? value
  )
}
export async function documentHash (file: File): Promise<string> {
  return Array.from(
    new Uint8Array(
      await crypto.subtle.digest('SHA-256', await file.arrayBuffer()),
    ),
    byte => byte.toString(16).padStart(2, '0'),
  ).join('')
}
export function validPeriod (from: string, until: string): void {
  if (!from || !until || until <= from) {
    throw new Error('The end must be after the start.')
  }
}

export { dateLabel } from '@/directory/presentation'
