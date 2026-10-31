import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'

test('a planned session explains why check-in is disabled and guides the coordinator to open it', async ({
  page,
}) => {
  const fixture = await ministryFixture(page)
  fixture.session.session.status = 'PLANNED'
  await page.goto('/children/sessions/session-1')
  const child = page.getByRole('row').filter({ hasText: 'Lindiwe Mabasa' })
  await expect(
    child.getByRole('button', { name: 'Check in', exact: true }),
  ).toBeDisabled()
  await expect(
    child.getByText(
      'This session is still planned. Open it for arrivals after confirming the team and child preparation.',
      { exact: true },
    ),
  ).toBeVisible()
  await page
    .getByRole('button', { name: 'Open for arrivals', exact: true })
    .click()
  await page
    .getByLabel('Reason *', { exact: true })
    .fill('Team present and child preparation reviewed')
  await page
    .getByRole('dialog')
    .getByRole('button', { name: 'Save changes', exact: true })
    .click()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(
    child.getByRole('button', { name: 'Check in', exact: true }),
  ).toBeEnabled()
  expect(
    fixture.requests.find(
      request => request.path === '/api/children/sessions/status',
    )?.body.status,
  ).toBe('OPEN')
  expect(fixture.errors).toEqual([])
})

test('the arrival window updates automatically as time passes without refreshing', async ({
  page,
}) => {
  const fixture = await ministryFixture(page)
  const now = Date.now()
  await page.clock.install({ time: new Date(now) })
  fixture.session.session.startsAt = new Date(now + 31 * 60_000).toISOString()
  fixture.session.session.endsAt = new Date(now + 90 * 60_000).toISOString()
  await page.goto('/children/sessions/session-1')
  const child = page.getByRole('row').filter({ hasText: 'Lindiwe Mabasa' })
  await expect(
    child.getByRole('button', { name: 'Check in', exact: true }),
  ).toBeDisabled()
  await expect(child.getByText(/Check-in opens at/)).toBeVisible()
  const reads = fixture.requests.filter(request =>
    request.path.endsWith('/day'),
  ).length
  await page.clock.fastForward(61_000)
  await expect(
    child.getByRole('button', { name: 'Check in', exact: true }),
  ).toBeEnabled()
  expect(
    fixture.requests.filter(request => request.path.endsWith('/day')).length,
  ).toBe(reads)
  await page.clock.fastForward(90 * 60_000)
  await expect(
    child.getByRole('button', { name: 'Check in', exact: true }),
  ).toBeDisabled()
  await expect(child.getByText(/This session has ended/)).toBeVisible()
  expect(fixture.errors).toEqual([])
})
