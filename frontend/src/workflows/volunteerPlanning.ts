import type { OperationalRecord } from '@/types/workflow'

export function planningRecords (
  section: string,
  records: OperationalRecord[],
  shifts: OperationalRecord[],
  occurrenceId: string | null,
): OperationalRecord[] {
  if (!occurrenceId) {
    return records
  }
  if (section === 'shifts') {
    return records.filter(record => record.occurrenceId === occurrenceId)
  }
  if (section === 'assignments') {
    const shiftIds = new Set(shifts.filter(shift => shift.occurrenceId === occurrenceId).map(shift => shift.id))
    return records.filter(record => shiftIds.has(String(record.shiftId)))
  }
  return records
}
