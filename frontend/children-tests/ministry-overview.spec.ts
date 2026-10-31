import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'
for (const width of [390, 1440])
  test(`ministry overview offers operational next steps at ${width}px`, async ({
    page,
  }) => {
    const fixture = await ministryFixture(page, [
      'children.read',
      'children.manage',
      'children.safeguarding',
      'children.incidents.manage',
      'people.read',
    ])
    await page.route('**/api/children/overview', (route) =>
      route.fulfill({
        json: {
          checkedAt: new Date().toISOString(),
          timeZone: 'Africa/Johannesburg',
          upcoming: {
            total: 4,
            sessions: [
              {
                id: 'session-1',
                programme: 'Little Lights',
                ministryClass: 'Sunday Explorers',
                room: 'Garden Room',
                startsAt: new Date(Date.now() + 86400000).toISOString(),
                status: 'PLANNED',
              },
            ],
          },
          teams: {
            sessions: [],
            total: 2,
            summary: {
              sessions: 4,
              ready: 2,
              attention: 2,
              missingLeaders: 1,
              shortCapacity: 2,
              clashes: 0,
            },
          },
          teamNotice: '',
          safeguarding: {
            issues: [],
            total: 3,
            summary: {
              children: 2,
              missing: 1,
              expired: 0,
              expiring: 2,
              careDue: 0,
            },
          },
          safeguardingNotice: '',
          incidents: { incidents: [], total: 2, overdue: 1, unassigned: 1 },
          communications: { deliveries: [], total: 5, failed: 1, waiting: 2 },
        },
      }),
    )
    await page.setViewportSize({ width, height: 1000 })
    await page.goto('/children/overview')
    await expect(
      page.getByRole('heading', { name: '4 upcoming sessions' }),
    ).toBeVisible()
    await expect(
      page.getByRole('heading', { name: '2 unresolved incidents' }),
    ).toBeVisible()
    await expect(
      page.getByRole('link', { name: 'Review deliveries', exact: true }),
    ).toBeVisible()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: `test-results/ministry-overview-${width}.png`,
      fullPage: true,
      animations: 'disabled',
    })
    expect(fixture.requests.filter((r) => r.path.endsWith('/access'))).toEqual(
      [],
    )
    expect(fixture.errors).toEqual([])
  })
test('ordinary readers do not see restricted incident or care sections', async ({
  page,
}) => {
  const fixture = await ministryFixture(page, ['children.read'])
  await page.route('**/api/children/overview', (route) =>
    route.fulfill({
      json: {
        checkedAt: new Date().toISOString(),
        timeZone: 'Africa/Johannesburg',
        upcoming: { total: 0, sessions: [] },
        teams: null,
        teamNotice: '',
        safeguarding: null,
        safeguardingNotice: '',
        incidents: null,
        communications: null,
      },
    }),
  )
  await page.goto('/children/overview')
  await expect(
    page.getByRole('heading', { name: '0 upcoming sessions' }),
  ).toBeVisible()
  await expect(
    page.getByRole('link', { name: 'Review incidents', exact: true }),
  ).toHaveCount(0)
  await expect(
    page.getByRole('link', { name: 'Review safeguarding', exact: true }),
  ).toHaveCount(0)
  expect(fixture.errors).toEqual([])
})
