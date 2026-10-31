import type { ChurchEvent, EventOccurrence } from '@/types/event'

export function nextOccurrence (
  event: ChurchEvent,
  now = Date.now(),
): EventOccurrence | undefined {
  return event.occurrences.reduce<EventOccurrence | undefined>(
    (next, occurrence) => {
      if (
        occurrence.status === 'cancelled'
        || new Date(occurrence.endsAt).getTime() < now
      ) {
        return next
      }
      return !next
        || new Date(occurrence.startsAt).getTime()
        < new Date(next.startsAt).getTime()
        ? occurrence
        : next
    },
    undefined,
  )
}
