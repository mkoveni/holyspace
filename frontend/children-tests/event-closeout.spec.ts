import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'
import { readinessFixture } from './event-readiness-fixture'
async function fixture(page: Page, options: { ended?: boolean, manage?: boolean, closed?: boolean, conflict?: boolean } = {}) {
 const permissions = ['events.read', 'events.registrations.read', 'volunteering.read', 'events.communications.send', ...(options.manage === false ? [] : ['events.manage'])]
 const base = await ministryFixture(page, permissions)
 const startsAt = new Date(Date.now() + (options.ended ? -7200000 : 86400000)).toISOString()
 const endsAt = new Date(Date.now() + (options.ended ? -3600000 : 90000000)).toISOString()
 const occurrence = { id: 'occurrence-1', eventId: 'event-1', seriesId: 'series-1', originDate: startsAt.slice(0, 10), timeZone: 'Africa/Johannesburg', startsAt, endsAt, venueId: 'venue-1', venueName: 'Main hall', venueAddress: '10 Church Road', status: 'SCHEDULED', overridden: false, version: 0, scheduleRevision: 0 }
 const data = readinessFixture(occurrence, options.ended)
 if (options.closed) { data.closeout = { ...data.closeout, status: 'CLOSED', version: 1, note: 'Original attendance review', followUp: 'Coordinator calls family tomorrow' }; data.changedSinceCloseout = true }
 const requests: { path: string, body: any }[] = []
 await page.route('**/api/events**', async route => {
  const request = route.request(), path = new URL(request.url()).pathname, body = request.postDataJSON()
  requests.push({ path, body })
  if (path.endsWith('/readiness')) return route.fulfill({ json: data })
  if (path.endsWith('/closeout/history')) return route.fulfill({ json: [{ id: 'history-1', version: 1, action: 'CLOSED', note: 'Original attendance review', followUp: 'Coordinator calls family tomorrow', attended: 1, absent: 0, waitlisted: 1, unresolvedNotices: 1, actorId: 'actor-1', actorName: 'Naledi Dube', occurredAt: endsAt }] })
  if (path.endsWith('/closeout/reopen')) { data.closeout.status = 'OPEN'; data.closeout.version++; data.changedSinceCloseout = false; return route.fulfill({ status: 204 }) }
  if (path.endsWith('/closeout')) {
   if (options.conflict) return route.fulfill({ status: 409, json: { message: 'Attendance changed. Refresh and review before saving.' } })
   data.closeout = { ...data.closeout, status: 'CLOSED', version: data.closeout.version + 1, note: body.note, followUp: body.followUp }; return route.fulfill({ status: 204 })
  }
  if (path.endsWith('/registrations')) return route.fulfill({ json: { registrations: [], total: 0, counts: data.review.attendance } })
  if (path.endsWith('/occurrences/occurrence-1')) return route.fulfill({ json: { occurrence, title: 'Sunday gathering', type: 'SERVICE', series: {}, registrationPolicy: { open: false, capacity: 20, waitlistEnabled: true, version: 1 }, registrations: data.review.attendance } })
  return route.fulfill({ status: 500, json: { message: 'Unexpected closeout request: ' + path } })
 })
 return { ...base, requests, data }
}
for (const width of [390, 1440]) test(`event readiness and closeout fit at ${width}px`, async ({ page }) => {
 await page.setViewportSize({ width, height: 1000 }); const { errors } = await fixture(page, { ended: true }); await page.goto('/events/occurrences/occurrence-1')
 await expect(page.getByRole('heading', { name: 'Readiness & closeout' })).toBeVisible()
 await expect(page.getByText('No duties planned. Confirm whether staffing is needed.')).toBeVisible()
 await expect(page.getByRole('link', { name: 'Plan the team' })).toHaveAttribute('href', '/volunteering/schedule')
 await expect(page.getByRole('link', { name: 'Review bookings' })).toHaveAttribute('href', '#event-bookings')
 await expect(page.getByRole('button', { name: 'Review and close out', exact: true })).toBeEnabled()
 expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
 await page.screenshot({ path: `/app/test-results/event-readiness-${width}.png`, fullPage: true }); expect(errors).toEqual([])
})
test('closeout requires review acknowledgements and recorded follow-up', async ({ page }) => {
 const { requests, errors } = await fixture(page, { ended: true }); await page.goto('/events/occurrences/occurrence-1'); await page.getByRole('button', { name: 'Review and close out' }).click()
 const dialog = page.getByRole('dialog'), save = dialog.getByRole('button', { name: 'Close gathering', exact: true })
 await expect(save).toBeDisabled(); await dialog.getByLabel('I reviewed and reconciled attendance', { exact: true }).check(); await expect(save).toBeDisabled()
 await dialog.getByLabel('I reviewed waitlists and email outcomes', { exact: true }).check(); await dialog.getByLabel('Closeout note', { exact: true }).fill('Team reconciled every arrival'); await save.click(); await expect(dialog).toBeVisible(); expect(requests.filter(r => r.path.endsWith('/closeout'))).toHaveLength(0)
 await dialog.getByLabel('Follow-up actions (required)', { exact: true }).fill('Naledi will call waitlisted families tomorrow'); await save.click(); await expect(dialog).toBeHidden(); await expect(page.getByRole('heading', { name: 'Gathering closed out' })).toBeVisible()
 expect(requests.find(r => r.path.endsWith('/closeout'))?.body).toMatchObject({ expectedVersion: 0, reviewHash: 'fresh-review', attendanceReviewed: true, followUpReviewed: true, note: 'Team reconciled every arrival', followUp: 'Naledi will call waitlisted families tomorrow' }); expect(errors).toEqual([])
})
test('stale review conflicts preserve the draft and require a refresh', async ({ page }) => {
 const { requests } = await fixture(page, { ended: true, conflict: true }); await page.goto('/events/occurrences/occurrence-1'); await page.getByRole('button', { name: 'Review and close out' }).click(); const dialog = page.getByRole('dialog')
 await dialog.getByLabel('I reviewed and reconciled attendance', { exact: true }).check(); await dialog.getByLabel('I reviewed waitlists and email outcomes', { exact: true }).check(); await dialog.getByLabel('Closeout note', { exact: true }).fill('Verified attendance'); await dialog.getByLabel('Follow-up actions (required)', { exact: true }).fill('Coordinator will call tomorrow'); await dialog.getByRole('button', { name: 'Close gathering', exact: true }).click()
 await expect(dialog.getByText('Attendance changed. Refresh and review before saving.')).toBeVisible(); await expect(dialog.getByRole('button', { name: 'Close gathering', exact: true })).toBeDisabled(); await expect(dialog.getByLabel('Closeout note', { exact: true })).toHaveValue('Verified attendance'); expect(requests.filter(r => r.path.endsWith('/closeout'))).toHaveLength(1)
})
test('changed closeouts can be reopened with a reason and retain history', async ({ page }) => {
 const { requests, errors } = await fixture(page, { ended: true, closed: true }); await page.goto('/events/occurrences/occurrence-1'); await expect(page.getByText('Attendance, bookings or notice outcomes changed after closeout. Reopen and review the updated record.')).toBeVisible()
 await page.getByRole('button', { name: 'Closeout history', exact: true }).click(); await expect(page.getByRole('dialog').getByText('Original attendance review')).toBeVisible(); await expect(page.getByRole('dialog').getByText(/Naledi Dube/)).toBeVisible(); await page.getByRole('button', { name: 'Close closeout history', exact: true }).click()
 await page.getByRole('button', { name: 'Reopen for review', exact: true }).click(); const dialog = page.getByRole('dialog'); await dialog.getByLabel('Reason for reopening', { exact: true }).fill('Review the corrected attendance'); await dialog.getByRole('button', { name: 'Reopen review', exact: true }).click(); await expect(dialog).toBeHidden(); await expect(page.getByRole('button', { name: 'Review and close out' })).toBeEnabled(); expect(requests.find(r => r.path.endsWith('/closeout/reopen'))?.body).toMatchObject({ expectedVersion: 1, reason: 'Review the corrected attendance' }); expect(errors).toEqual([])
})
test('upcoming gatherings explain why closeout is unavailable', async ({ page }) => {
 await fixture(page); await page.goto('/events/occurrences/occurrence-1'); await expect(page.getByRole('button', { name: 'Review and close out' })).toBeDisabled(); await expect(page.getByText('Closeout becomes available when the gathering ends or is cancelled. The notice summary must be complete.')).toBeVisible()
})
test('event readers do not receive management readiness or closeout controls', async ({ page }) => {
 const { requests, errors } = await fixture(page, { manage: false }); await page.goto('/events/occurrences/occurrence-1'); await expect(page.getByRole('heading', { name: 'Bookings' })).toBeVisible(); await expect(page.getByRole('heading', { name: 'Readiness & closeout' })).toHaveCount(0); expect(requests.some(r => r.path.endsWith('/readiness'))).toBe(false); expect(errors).toEqual([])
})
