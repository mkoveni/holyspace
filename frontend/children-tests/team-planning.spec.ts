import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'

async function planningFixture(page: Page) {
  const fixture = await ministryFixture(page)
  const requests: { path: string; body: any }[] = []
  let applied = false
  let clash = false
  const rows = (projected = false) =>
    [0, 1].map((index) => ({
      session: {
        ...fixture.session,
        id: `planned-${index}`,
        version: applied ? 1 : 0,
        status: 'PLANNED',
        startsAt: new Date(
          Date.now() + (index + 1) * 7 * 86400000,
        ).toISOString(),
        endsAt: new Date(
          Date.now() + (index + 1) * 7 * 86400000 + 3600000,
        ).toISOString(),
        seatLimit: 12,
      },
      programmeId: 'programme-1',
      programme: 'Little Lights',
      ministryClass: 'Sunday Explorers',
      room: 'Garden Room',
      timeZone: 'Africa/Johannesburg',
      requiredStaff: 2,
      qualifiedStaff: projected || applied ? 2 : 1,
      qualifiedLeader: projected || applied,
      plannedCapacity: projected || applied ? 12 : 0,
      ready: projected || applied,
      editable: true,
      issues:
        projected || applied
          ? []
          : ['A qualified, available leader is needed.'],
      team: [
        {
          workerId: 'worker-2',
          name: 'Thabo Nkosi',
          role: 'ASSISTANT',
          present: false,
          qualified: true,
          clash: false,
          screenedUntil: '2027-12-01T00:00:00Z',
          trainedUntil: '2027-12-01T00:00:00Z',
          issues: [],
        },
      ],
    }))
  await page.route('**/api/children/team-planning**', async (route) => {
    const request = route.request(),
      path = new URL(request.url()).pathname
    const body = request.postDataJSON()
    requests.push({ path, body })
    if (path.endsWith('/workers'))
      return route.fulfill({
        json: {
          workers: [
            {
              id: 'worker-1',
              name: 'Naledi Mokoena',
              screenedUntil: '2027-12-01T00:00:00Z',
              trainedUntil: '2027-12-01T00:00:00Z',
              revoked: false,
            },
          ],
          total: 1,
        },
      })
    if (path.endsWith('/preview'))
      return route.fulfill({
        json: {
          sessions: rows(true),
          blockers: clash
            ? ['The selected worker has an overlapping assignment.']
            : [],
          checkedAt: new Date().toISOString(),
        },
      })
    if (path.endsWith('/apply')) {
      applied = true
      return route.fulfill({ status: 204 })
    }
    return route.fulfill({
      json: {
        sessions: rows(),
        total: 2,
        summary: {
          sessions: 2,
          ready: applied ? 2 : 0,
          attention: applied ? 0 : 2,
          missingLeaders: applied ? 0 : 2,
          shortCapacity: applied ? 0 : 2,
          clashes: 0,
        },
        timeZone: 'Africa/Johannesburg',
        checkedAt: new Date().toISOString(),
      },
    })
  })
  return {
    ...fixture,
    plans: requests,
    clash: () => {
      clash = true
    },
  }
}
async function openPlan(page: Page) {
  await page.goto('/children/team-planning')
  await expect(
    page.getByRole('heading', { name: 'Ready teams. Room to care.' }),
  ).toBeVisible()
  await page
    .getByRole('checkbox', { name: /^Select Sunday Explorers/ })
    .nth(0)
    .check()
  await page
    .getByRole('checkbox', { name: /^Select Sunday Explorers/ })
    .nth(1)
    .check()
  await page.getByRole('button', { name: 'Plan selected (2)' }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Registered worker *', { exact: true }).fill('Naledi')
  await page.getByRole('option', { name: 'Naledi Mokoena' }).click()
  await dialog.getByLabel('Planned role *', { exact: true }).press('ArrowDown')
  await page.getByRole('option', { name: 'Leader', exact: true }).click()
  await dialog
    .getByLabel('Planning reason *', { exact: true })
    .fill('Sunday leader cover confirmed')
  return dialog
}
for (const width of [390, 1440]) {
  test(`bulk planning previews and applies an absent team at ${width}px`, async ({
    page,
  }) => {
    const fixture = await planningFixture(page)
    await page.setViewportSize({ width, height: 1000 })
    const dialog = await openPlan(page)
    await expect(
      dialog.getByRole('button', { name: 'Apply team plan', exact: true }),
    ).toBeDisabled()
    await dialog.getByRole('button', { name: 'Preview team changes' }).click()
    await expect(
      dialog
        .getByText('The planned team meets the staffing requirements.')
        .first(),
    ).toBeVisible()
    expect(
      await dialog.evaluate(
        (element) => element.scrollWidth <= element.clientWidth,
      ),
    ).toBe(true)
    await dialog.screenshot({
      path: `test-results/team-plan-${width}.png`,
      animations: 'disabled',
    })
    await dialog
      .getByRole('button', { name: 'Apply team plan', exact: true })
      .click()
    await expect(dialog).toHaveCount(0)
    await expect(page.locator('.planning-row').getByText('Team prepared', { exact: true })).toHaveCount(
      2,
    )
    const applied = fixture.plans.find((request) =>
      request.path.endsWith('/apply'),
    )?.body
    expect(applied).toEqual({
      draft: {
        targets: [
          { sessionId: 'planned-0', version: 0 },
          { sessionId: 'planned-1', version: 0 },
        ],
        workerId: 'worker-1',
        replaceWorkerId: null,
        role: 'LEADER',
      },
      reason: 'Sunday leader cover confirmed',
    })
    expect(fixture.errors).toEqual([])
  })
}
test('changing a reviewed plan requires a fresh preview and clashes block applying', async ({
  page,
}) => {
  const fixture = await planningFixture(page)
  const dialog = await openPlan(page)
  await dialog.getByRole('button', { name: 'Preview team changes' }).click()
  await expect(
    dialog.getByRole('button', { name: 'Apply team plan', exact: true }),
  ).toBeEnabled()
  await dialog.getByLabel('Planned role *', { exact: true }).press('ArrowDown')
  await page.getByRole('option', { name: 'Assistant', exact: true }).click()
  await expect(
    dialog.getByRole('button', { name: 'Apply team plan', exact: true }),
  ).toBeDisabled()
  await expect(
    dialog.getByText('The plan has changed. Preview it again before applying.'),
  ).toBeVisible()
  fixture.clash()
  await dialog.getByRole('button', { name: 'Preview team changes' }).click()
  await expect(
    dialog.getByText('The selected worker has an overlapping assignment.'),
  ).toBeVisible()
  await expect(
    dialog.getByRole('button', { name: 'Apply team plan', exact: true }),
  ).toBeDisabled()
  expect(fixture.plans.some((request) => request.path.endsWith('/apply'))).toBe(
    false,
  )
  expect(fixture.errors).toEqual([])
})

test('changing filters prevents using the previous selection until the view is refreshed', async ({
  page,
}) => {
  const fixture = await planningFixture(page)
  await page.goto('/children/team-planning')
  await page
    .getByRole('checkbox', { name: /^Select Sunday Explorers/ })
    .first()
    .check()
  await expect(
    page.getByRole('button', { name: 'Plan selected (1)' }),
  ).toBeEnabled()
  await page
    .getByLabel('Class, room or team member', { exact: true })
    .fill('Sunday')
  await expect(
    page.getByRole('button', { name: 'Plan selected (1)' }),
  ).toBeDisabled()
  await page.getByRole('button', { name: 'Apply filters', exact: true }).click()
  await expect(
    page.getByRole('button', { name: 'Plan selected (0)' }),
  ).toBeDisabled()
  expect(fixture.plans.some((request) => request.path.endsWith('/apply'))).toBe(
    false,
  )
  expect(fixture.errors).toEqual([])
})
