import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'
for (const width of [768, 1440]) {
  test(`session day searches the full roster and explains blocked arrivals at ${width}px`, async ({
    page,
  }) => {
    const fixture = await ministryFixture(page)
    await page.setViewportSize({ width, height: 1024 })
    const searches: string[] = []
    await page.route(
      '**/api/children/station/sessions/session-1/day?**',
      async route => {
        const query = new URL(route.request().url()).searchParams
        searches.push(query.get('search') || '')
        const match = query.get('search')
        const children = match
          ? [
              {
                childId: 'child-150',
                name: 'Zanele Mabasa',
                attendance: null,
                ready: false,
                issues: [
                  'Guardian consent for the current policy must cover this session through its end.',
                ],
              },
            ]
          : [
              {
                childId: 'child-1',
                name: 'Lindiwe Mabasa',
                attendance: null,
                ready: true,
                issues: [],
              },
            ]
        await route.fulfill({
          json: {
            children,
            total: match ? 1 : 150,
            checkedAt: new Date().toISOString(),
          },
        })
      },
    )
    await page.goto('/children/sessions/session-1?station=1')
    const overview = page.getByRole('region', { name: 'Session day overview' })
    await expect(
      overview.getByText('Places available', { exact: true }),
    ).toBeVisible()
    await expect(
      overview.getByText('Qualified leader present', { exact: true }),
    ).toBeVisible()
    await page
      .getByLabel('Search session children', { exact: true })
      .fill('Zanele')
    await expect(
      page.getByRole('row').filter({ hasText: 'Zanele Mabasa' }),
    ).toBeVisible()
    const blocked = page.getByRole('row').filter({ hasText: 'Zanele Mabasa' })
    await expect(
      blocked.getByRole('button', { name: 'Check in', exact: true }),
    ).toBeDisabled()
    await expect(
      blocked
        .getByText(
          'Guardian consent for the current policy must cover this session through its end.',
          { exact: true },
        )
        .last(),
    ).toBeVisible()
    await expect(
      blocked.getByRole('link', { name: 'Review readiness', exact: true }),
    ).toHaveAttribute('href', '/children/participants/child-150')
    expect(await page.locator('.children-roster').evaluate(element => element.scrollWidth <= element.clientWidth)).toBe(true)
    expect(searches).toContain('Zanele')
    await expect(
      page.getByRole('button', { name: 'Next', exact: true }),
    ).toBeDisabled()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: `test-results/session-day-${width}.png`,
      fullPage: true,
      animations: 'disabled',
    })
    expect(
      fixture.requests.some(request =>
        request.path.includes('/support/access'),
      ),
    ).toBe(false)
    expect(fixture.errors).toEqual([])
  })
}

test('capacity and session windows disable arrivals while collection remains available', async ({
  page,
}) => {
  const fixture = await ministryFixture(page, [
    'children.station',
    'children.support',
  ])
  fixture.session.effectiveCapacity = 0
  await page.goto('/children/sessions/session-1?station=1')
  await expect(
    page.getByRole('alert').filter({ hasText: 'Safe capacity has been reached.' }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Check in', exact: true }).first(),
  ).toBeDisabled()
  fixture.session.effectiveCapacity = 12
  fixture.session.session.endsAt = new Date(Date.now() - 1000).toISOString()
  await page
    .getByRole('button', { name: 'Refresh session', exact: true })
    .click()
  await expect(
    page.getByRole('alert').filter({ hasText: 'This session has ended.' }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Check in', exact: true }).first(),
  ).toBeDisabled()
  fixture.attendance.push({ id: 'attendance-ended', childId: 'child-1', arrivedAt: new Date().toISOString(), checkedOutAt: null, labelIssued: true, version: 0 })
  await page.getByRole('tab', { name: 'Attending', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Collect', exact: true })).toBeEnabled()
  expect(fixture.errors).toEqual([])
})
