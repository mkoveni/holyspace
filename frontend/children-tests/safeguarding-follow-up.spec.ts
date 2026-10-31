import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'
async function followUpFixture(page: Page, permissions?: string[]) {
  const fixture = await ministryFixture(page, permissions)
  const includeCare = !permissions || permissions.includes('children.support')
  const urls: string[] = []
  const issues = [
    {
      id: 'guardian',
      kind: 'GUARDIAN',
      state: 'EXPIRING',
      childId: 'child-1',
      childName: 'Lindiwe Mokoena',
      programmeId: 'programme-1',
      programme: 'Little Lights',
      expiresAt: '2026-11-01T00:00:00Z',
      reviewOn: null,
      action: 'guardian',
      message:
        'Verified guardian authority expires within the follow-up window.',
    },
    ...(includeCare
      ? [
          {
            id: 'care',
            kind: 'CARE',
            state: 'DUE',
            childId: 'child-1',
            childName: 'Lindiwe Mokoena',
            programmeId: null,
            programme: 'All programmes',
            expiresAt: null,
            reviewOn: '2026-10-26',
            action: 'care',
            message: 'The current care record is due for review.',
          },
        ]
      : []),
  ]
  await page.route('**/api/children/safeguarding-follow-up**', (route) => {
    urls.push(route.request().url())
    return route.fulfill({
      json: {
        issues,
        total: issues.length,
        summary: {
          children: 1,
          missing: 0,
          expired: 0,
          expiring: 1,
          careDue: includeCare ? 1 : 0,
        },
        careIncluded: includeCare,
        through: '2026-11-25',
        timeZone: 'Africa/Johannesburg',
        checkedAt: new Date().toISOString(),
      },
    })
  })
  return { ...fixture, urls }
}
for (const width of [390, 1440]) {
  test(`follow-up links open guardian verification at ${width}px`, async ({
    page,
  }) => {
    const fixture = await followUpFixture(page)
    await page.setViewportSize({ width, height: 1000 })
    await page.goto('/children/safeguarding-follow-up')
    await expect(
      page.getByRole('heading', { name: 'The details that keep care safe.' }),
    ).toBeVisible()
    await expect(
      page.getByText('The current care record is due for review.'),
    ).toBeVisible()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: `test-results/follow-up-${width}.png`,
      fullPage: true,
      animations: 'disabled',
    })
    await page.getByRole('link', { name: 'Verify guardian' }).click()
    await expect(page).toHaveURL(/participants\/child-1\?.*followup=guardian/)
    await expect(
      page.getByRole('dialog').getByLabel('Guardian *', { exact: true }),
    ).toBeVisible()
    expect(
      fixture.requests.filter((request) =>
        request.path.includes('/support/access'),
      ),
    ).toEqual([])
    expect(fixture.errors).toEqual([])
  })
}
test('care follow-up opens the profile without automatically reading private care details', async ({
  page,
}) => {
  const fixture = await followUpFixture(page)
  await page.goto('/children/safeguarding-follow-up')
  await page.getByRole('link', { name: 'Open care review' }).click()
  await expect(page).toHaveURL(/participants\/child-1#child-care/)
  await expect(
    page.getByRole('button', { name: 'Open care information', exact: true }),
  ).toBeVisible()
  expect(
    fixture.requests.filter((request) =>
      request.path.includes('/support/access'),
    ),
  ).toEqual([])
  expect(fixture.errors).toEqual([])
})
test('safeguarding-only access hides care metadata and does not load profile catalogues', async ({
  page,
}) => {
  const fixture = await followUpFixture(page, ['children.safeguarding'])
  await page.goto('/children/safeguarding-follow-up')
  await expect(
    page.getByText('Care-review dates require care-support access.', {
      exact: false,
    }),
  ).toBeVisible()
  await expect(
    page.getByRole('link', { name: 'Open care review' }),
  ).toHaveCount(0)
  await expect(page.getByRole('link', { name: 'Verify guardian' })).toHaveCount(
    0,
  )
  await expect(page.getByLabel('Programme', { exact: true })).toHaveCount(0)
  expect(
    fixture.requests.filter(
      (request) => request.path === '/api/children/programmes',
    ),
  ).toEqual([])
  expect(fixture.errors).toEqual([])
})

test('filters are applied explicitly and preserve the selected horizon in requests', async ({
  page,
}) => {
  const fixture = await followUpFixture(page)
  await page.goto('/children/safeguarding-follow-up')
  await expect(
    page.getByText('The current care record is due for review.'),
  ).toBeVisible()
  await page.getByLabel('Child name', { exact: true }).fill('Lindiwe')
  await page.getByLabel('Days ahead', { exact: true }).press('ArrowDown')
  await page.getByRole('option', { name: '14', exact: true }).click()
  expect(fixture.urls).toHaveLength(1)
  await page.getByRole('button', { name: 'Apply filters', exact: true }).click()
  await expect.poll(() => fixture.urls.length).toBe(2)
  const url = new URL(fixture.urls.at(-1)!)
  expect(url.searchParams.get('search')).toBe('Lindiwe')
  expect(url.searchParams.get('days')).toBe('14')
  expect(fixture.errors).toEqual([])
})
