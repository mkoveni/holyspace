import type { SessionView } from '../src/children/types'
import { describe, expect, it } from 'vitest'
import { arrivalBlocker } from '../src/children/arrivalAvailability'

const start = Date.parse('2026-10-26T12:00:00Z')
function session (): SessionView {
  return {
    session: {
      id: 's',
      classId: 'c',
      roomId: 'r',
      startsAt: new Date(start).toISOString(),
      endsAt: new Date(start + 3_600_000).toISOString(),
      seatLimit: 10,
      status: 'OPEN',
      version: 0,
    },
    className: 'Class',
    roomName: 'Room',
    timeZone: 'Africa/Johannesburg',
    effectiveCapacity: 10,
    occupancy: 0,
    staffingNeedsReview: false,
    staff: [],
  }
}
describe('arrival availability', () => {
  it('opens exactly thirty minutes before the start and closes exactly at the end', () => {
    expect(arrivalBlocker(session(), start - 1_800_001)).toContain(
      'Check-in opens at',
    )
    expect(arrivalBlocker(session(), start - 1_800_000)).toBe('')
    expect(arrivalBlocker(session(), start + 3_599_999)).toBe('')
    expect(arrivalBlocker(session(), start + 3_600_000)).toContain(
      'This session has ended',
    )
  })
  it('explains that a planned session must be opened even when it has capacity', () => {
    const value = session()
    value.session.status = 'PLANNED'
    expect(arrivalBlocker(value, start)).toContain('still planned')
  })
  it('distinguishes staffing, capacity and terminal session blockers', () => {
    const value = session()
    value.staffingNeedsReview = true
    expect(arrivalBlocker(value, start)).toContain('Review team presence')
    value.staffingNeedsReview = false
    value.occupancy = 10
    expect(arrivalBlocker(value, start)).toContain('Safe capacity')
    value.session.status = 'CLOSED'
    expect(arrivalBlocker(value, start)).toContain('Arrivals are closed')
  })
})
