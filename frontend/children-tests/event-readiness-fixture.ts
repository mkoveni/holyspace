export function readinessFixture(occurrence: any, ended = false) {
  return {
    checks: [
      { key: 'venue', title: 'Venue', status: 'READY', detail: 'Main hall · Venue available' },
      { key: 'registration', title: 'Registration and capacity', status: 'REVIEW', detail: 'Closed · 1 confirmed · 20 places · 1 waitlisted' },
      { key: 'staffing', title: 'Team coverage and readiness', status: 'REVIEW', detail: 'No duties planned. Confirm whether staffing is needed.' },
      { key: 'reminders', title: 'Participant reminders', status: 'REVIEW', detail: '0 of 1 confirmed bookings have a reminder approved for the current schedule. Approval is not delivery.' },
      { key: 'deliveries', title: 'Email follow-up', status: 'REVIEW', detail: '1 pending · 0 need attention · 0 captured locally' },
    ],
    staffing: { shifts: 0, positions: 0, confirmed: 0, ready: 0, proposed: 0, gaps: 0, complete: true },
    review: { occurrence, attendance: { confirmed: 1, waitlisted: 1, cancelled: 0, attended: 1, absent: 0 }, notices: { approved: 1, submitted: 0, delivered: 0, pending: 1, failed: 0, captured: 0, cancelled: 0, remindersApproved: 0, complete: true }, hash: 'fresh-review', canClose: ended },
    closeout: { occurrenceId: occurrence.id, status: 'OPEN', version: 0, note: '', followUp: '', updatedAt: null, updatedBy: null },
    changedSinceCloseout: false,
    assessedAt: new Date().toISOString(),
  }
}
