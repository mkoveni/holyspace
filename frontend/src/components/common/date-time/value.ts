// Local wall-clock values deliberately contain no UTC offset. The caller decides
// when to convert them to an instant for its API.
export function parseLocalDateTime (value: string): Date | null {
  if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value)) {
    return null
  }
  const date = new Date(value)
  return Number.isNaN(date.getTime()) || localDateTimeValue(date) !== value
    ? null
    : date
}

export function localDateTimeValue (date: Date): string {
  const pad = (value: number) => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

export function dateTimeLabel (value: string): string {
  const date = parseLocalDateTime(value)
  return date
    ? new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
      }).format(date)
    : value
}
