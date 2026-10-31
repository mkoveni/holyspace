import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'
for (const mobile of [false, true]) {
  test(`programme and session screens share the People design at ${mobile ? 'mobile' : 'desktop'} size`, async ({
    page,
  }) => {
    const fixture = await ministryFixture(page)
    await page.setViewportSize({ width: mobile ? 390 : 1440, height: 1000 })
    await page.goto('/children')
    await expect(
      page.getByRole('heading', { name: 'Little people. Big belonging.' }),
    ).toBeVisible()
    await expect(
      page.getByRole('heading', { name: 'Little Lights', exact: true }),
    ).toBeVisible()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: `test-results/children-programmes-${mobile ? 'mobile' : 'desktop'}.png`,
      fullPage: true,
      animations: 'disabled',
    })
    await page
      .getByRole('button', { name: 'Add programme', exact: true })
      .first()
      .click()
    await page
      .getByLabel('Programme name *', { exact: true })
      .fill('Growing Together')
    await page.getByLabel('Minimum staff present *', { exact: true }).fill('2')
    await page
      .getByLabel('Children per staff member *', { exact: true })
      .fill('5')
    await page.screenshot({
      path: `test-results/children-programme-form-${mobile ? 'mobile' : 'desktop'}.png`,
      fullPage: true,
      animations: 'disabled',
    })
    await page
      .getByRole('button', { name: 'Save & continue', exact: true })
      .click()
    await expect(
      page.getByRole('heading', { name: 'Growing Together', exact: true }),
    ).toBeVisible()
    expect(
      fixture.requests.find(
        r => r.method === 'POST' && r.path === '/api/children/programmes',
      )?.body,
    ).toEqual({ name: 'Growing Together', minStaff: 2, childrenPerStaff: 5 })
    await page.goto('/children/programmes/programme-1')
    await expect(
      page.getByRole('heading', { name: 'Classes to grow in' }),
    ).toBeVisible()
    await expect(
      page.getByRole('heading', { name: 'Family consent', exact: true }),
    ).toBeVisible()
    await page.screenshot({
      path: `test-results/children-programme-detail-${mobile ? 'mobile' : 'desktop'}.png`,
      fullPage: true,
      animations: 'disabled',
    })
    await page.goto('/children-station')
    await expect(
      page.getByRole('heading', { name: 'Ready for a warm welcome.' }),
    ).toBeVisible()
    await page.getByRole('link', { name: 'Open session', exact: true }).click()
    await expect(
      page.getByRole('heading', { name: 'Sunday Explorers', exact: true }),
    ).toBeVisible()
    await expect(
      page.getByText('Lindiwe Mabasa', { exact: true }),
    ).toBeVisible()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.getByRole('row').filter({ hasText: 'Lindiwe Mabasa' }).getByRole('button', { name: 'Check in', exact: true }).scrollIntoViewIfNeeded()
    await expect(page.getByRole('row').filter({ hasText: 'Lindiwe Mabasa' }).getByRole('button', { name: 'Check in', exact: true })).toBeInViewport()
    await page.screenshot({
      path: `test-results/children-station-${mobile ? 'mobile' : 'desktop'}.png`,
      fullPage: true,
      animations: 'disabled',
    })
    expect(
      fixture.requests.some(r => r.path === '/api/children/support/access'),
    ).toBe(false)
    expect(fixture.errors).toEqual([])
  })
}
test('station check-in, one-time label and identity-verified collection use real transport contracts', async ({
  page,
}) => {
  const fixture = await ministryFixture(page, [
    'children.station',
    'children.support',
  ])
  await page.goto('/children-station')
  await page.getByRole('link', { name: 'Open session', exact: true }).click()
  const child = page.getByRole('row').filter({ hasText: 'Lindiwe Mabasa' })
  await child.getByRole('button', { name: 'Check in', exact: true }).click()
  await expect(
    page.getByRole('tab', { name: 'Attending', exact: true }),
  ).toHaveAttribute('aria-selected', 'true')
  await expect(page.getByRole('heading', { name: 'Issue a pickup label', exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Create label', exact: true }).click()
  await expect(
    page.getByText('TEST-ONLY-PICKUP-CODE', { exact: true }),
  ).toBeVisible()
  await expect(page.getByAltText('Pickup code QR label')).toBeVisible()
  expect(await page.evaluate(() => JSON.stringify(localStorage))).not.toContain(
    'TEST-ONLY-PICKUP-CODE',
  )
  await page.getByRole('button', { name: 'Done', exact: true }).click()
  await expect(
    page.getByText('TEST-ONLY-PICKUP-CODE', { exact: true }),
  ).toHaveCount(0)
  await child
    .getByRole('button', { name: 'More actions for Lindiwe Mabasa' })
    .click()
  await page.getByText('Care information', { exact: true }).click()
  await expect(page.getByText('Peanut allergy', { exact: true })).toBeVisible()
  expect(
    fixture.requests.filter(r => r.path === '/api/children/support/access'),
  ).toHaveLength(1)
  await page.getByRole('button', { name: 'Close care information' }).click()
  await expect(page.getByText('Peanut allergy', { exact: true })).toHaveCount(0)
  await child.getByRole('button', { name: 'Collect', exact: true }).click()
  await page
    .getByLabel('Authorized collector *', { exact: true })
    .press('ArrowDown')
  await page.getByRole('option', { name: 'Solly Mabasa', exact: true }).click()
  await page.getByLabel('Pickup code *', { exact: true }).fill('wrong-code')
  await page
    .getByRole('button', { name: 'Confirm collection', exact: true })
    .click()
  expect(
    fixture.requests.filter(
      r => r.path === '/api/children/attendance/checkout',
    ),
  ).toHaveLength(0)
  await page
    .getByLabel('I have checked this collector’s identity in person')
    .check()
  await page
    .getByRole('button', { name: 'Confirm collection', exact: true })
    .click()
  await expect(
    page.getByRole('alert').filter({ hasText: 'Pickup code is invalid' }),
  ).toBeVisible()
  await page
    .getByLabel('Pickup code *', { exact: true })
    .fill('TEST-ONLY-PICKUP-CODE')
  await page
    .getByRole('button', { name: 'Confirm collection', exact: true })
    .click()
  await expect(
    page.getByRole('heading', { name: 'A safe handover', exact: true }),
  ).toHaveCount(0)
  const attempts = fixture.requests.filter(
    r => r.path === '/api/children/attendance/checkout',
  )
  expect(attempts.map(r => r.body.expectedVersion)).toEqual([1, 2])
  expect(attempts[0]!.body.requestId).toBe(attempts[1]!.body.requestId)
  expect(fixture.requests.some(r => r.path === '/api/people')).toBe(false)
  expect(fixture.errors).toEqual([])
})
test('readers see programme information but no write or care controls', async ({
  page,
}) => {
  const fixture = await ministryFixture(page, ['children.read'])
  await page.goto('/children')
  await expect(
    page.getByRole('heading', { name: 'Little people. Big belonging.' }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Add programme', exact: true }),
  ).toHaveCount(0)
  await page.goto('/children/programmes/programme-1')
  await expect(
    page.getByRole('heading', { name: 'Little Lights', exact: true }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Add class', exact: true }),
  ).toHaveCount(0)
  await expect(
    page.getByRole('button', { name: 'Publish a new version', exact: true }),
  ).toHaveCount(0)
  expect(
    fixture.requests.some(r => r.path === '/api/children/support/access'),
  ).toBe(false)
  expect(fixture.errors).toEqual([])
})
