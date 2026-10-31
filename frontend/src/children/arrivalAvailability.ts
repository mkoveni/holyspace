import type { SessionView } from './types'
import { timeLabel } from './presentation'

export function arrivalBlocker (view: SessionView, now: number): string {
  if (['CLOSED', 'CANCELLED'].includes(view.session.status)) {
    return 'Arrivals are closed. Children still with the team can be collected through the verified handover flow.'
  }
  if (now >= Date.parse(view.session.endsAt)) {
    return 'This session has ended. Arrivals are closed; complete any remaining collections.'
  }
  if (view.session.status === 'PLANNED') {
    return 'This session is still planned. Open it for arrivals after confirming the team and child preparation.'
  }
  if (view.session.status !== 'OPEN' || view.staffingNeedsReview) {
    return 'Review team presence, leader coverage and clearance before admitting more children.'
  }
  const opensAt = Date.parse(view.session.startsAt) - 30 * 60_000
  if (now < opensAt) {
    return `Check-in opens at ${timeLabel(new Date(opensAt).toISOString(), view.timeZone)} (30 minutes before the session).`
  }
  if (view.occupancy >= view.effectiveCapacity) {
    return 'Safe capacity has been reached. Wait for a place to become available before another arrival.'
  }
  return ''
}
