import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'

test('coordinators review the room and record a closeout note', async ({
  page,
}) => {
  const fixture = await ministryFixture(page)
  await page.goto('/children/sessions/session-1')
  const panel = page.getByRole('region', { name: 'Session closeout' })
  await expect(
    panel.getByText('Every child is accounted for.', { exact: false }),
  ).toBeVisible()
  await panel.getByRole('button', { name: 'Review & close session' }).click()
  const dialog = page.getByRole('dialog')
  await expect(
    dialog.getByRole('button', { name: 'Close session', exact: true }),
  ).toBeDisabled()
  await dialog
    .getByLabel('I have checked the room and every child is accounted for.')
    .check()
  await dialog
    .getByLabel('Closeout note *')
    .fill('Final room check complete; all children accounted for.')
  await dialog
    .getByRole('button', { name: 'Close session', exact: true })
    .click()
  await expect(dialog).toHaveCount(0)
  await expect(panel.getByText('Session closed', { exact: true })).toBeVisible()
  expect(
    fixture.requests.find(
      value => value.path === '/api/children/sessions/status',
    )?.body.status,
  ).toBe('CLOSED')
  expect(fixture.errors).toEqual([])
})

test('global attendance and unresolved handovers prevent closure even with a filtered roster', async ({
  page,
}) => {
  const fixture = await ministryFixture(page)
  await page.route('**/api/children/sessions/session-1/closeout', route =>
    route.fulfill({
      json: {
        visits: 105,
        children: 105,
        awaitingCollection: 101,
        collected: 3,
        transferredOut: 1,
        labelsNeedingAttention: 2,
        failedHandoverAttempts: 1,
        supervisorCollections: 1,
        checkedAt: new Date().toISOString(),
      },
    }),
  )
  fixture.attendance.push({
    id: 'visit-1',
    childId: 'child-1',
    sessionId: 'session-1',
    arrivedAt: new Date().toISOString(),
    checkedOutAt: null,
    labelIssued: false,
    version: 0,
  })
  await page.setViewportSize({ width: 768, height: 1024 })
  await page.goto('/children/sessions/session-1')
  const panel = page.getByRole('region', { name: 'Session closeout' })
  await expect(
    panel.getByRole('button', { name: 'Review & close session' }),
  ).toBeDisabled()
  await expect(
    panel.getByText(
      '2 attending children need a new or replacement pickup label.',
    ),
  ).toBeVisible()
  await panel.getByRole('button', { name: 'Review attending children' }).click()
  await expect(page.getByRole('tab', { name: /Attending/i })).toHaveAttribute(
    'aria-selected',
    'true',
  )
  const child = page.getByRole('row').filter({ hasText: 'Lindiwe Mabasa' })
  await expect(
    child
      .getByText('Issue a pickup label before collection.', { exact: true })
      .last(),
  ).toBeVisible()
  await panel.screenshot({
    path: 'test-results/closeout-review-tablet.png',
    animations: 'disabled',
  })
  expect(
    fixture.requests.filter(
      value => value.path === '/api/children/sessions/status',
    ),
  ).toHaveLength(0)
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= window.innerWidth,
    ),
  ).toBe(true)
  expect(fixture.errors).toEqual([])
})
