import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'

for (const mobile of [false, true]) {
  test(`enrol a selected child directly from a programme at ${mobile ? 'mobile' : 'desktop'} size`, async ({
    page,
  }) => {
    const fixture = await ministryFixture(page)
    const attempts: Record<string, unknown>[] = []
    await page.route('**/api/children/enrolments', async route => {
      attempts.push(route.request().postDataJSON())
      await route.fulfill(
        attempts.length === 1
          ? {
              status: 500,
              json: { message: 'Unable to enrol right now. Please try again.' },
            }
          : { status: 201, json: { id: 'new-enrolment' } },
      )
    })
    await page.setViewportSize({ width: mobile ? 390 : 1440, height: 1000 })
    await page.goto('/children/programmes/programme-1')
    const enrol = page.getByRole('button', {
      name: 'Enrol in programme',
      exact: true,
    })
    await expect(enrol).toBeDisabled()
    await page.getByLabel('Find a child', { exact: true }).fill('Lindiwe')
    await page
      .getByRole('option', { name: 'Lindiwe Mabasa', exact: true })
      .click()
    await enrol.click()
    const dialog = page.getByRole('dialog')
    await expect(
      dialog.getByRole('heading', {
        name: 'Welcome to a programme',
        exact: true,
      }),
    ).toBeVisible()
    await expect(
      dialog.getByText('Lindiwe Mabasa', { exact: true }),
    ).toBeVisible()
    await expect(
      dialog.getByText('Little Lights', { exact: true }),
    ).toBeVisible()
    await dialog.getByLabel('First day *', { exact: true }).fill('2026-11-01')
    await dialog.getByLabel('Last day *', { exact: true }).fill('2027-10-31')
    await page.screenshot({
      path: `test-results/enrol-child-${mobile ? 'mobile' : 'desktop'}.png`,
      fullPage: true,
      animations: 'disabled',
    })
    await dialog
      .getByRole('button', { name: 'Save changes', exact: true })
      .click()
    await expect(
      dialog
        .getByRole('alert')
        .filter({ hasText: 'Unable to enrol right now' }),
    ).toBeVisible()
    await expect(dialog.getByLabel('First day *', { exact: true })).toHaveValue(
      '2026-11-01',
    )
    await dialog
      .getByRole('button', { name: 'Save changes', exact: true })
      .click()
    await expect(dialog).toHaveCount(0)
    await expect(
      page
        .getByRole('alert')
        .filter({ hasText: 'Child enrolled in Little Lights.' }),
    ).toBeVisible()
    expect(attempts).toEqual(
      Array.from({ length: 2 }, () => ({
        childId: 'child-1',
        programmeId: 'programme-1',
        validFrom: '2026-11-01',
        validUntil: '2027-11-01',
      })),
    )
    await expect(
      page.getByRole('link', { name: 'Open ministry profile', exact: true }),
    ).toHaveAttribute(
      'href',
      '/children/participants/child-1?programme=programme-1',
    )
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    expect(fixture.errors).toEqual([])
  })
}

test('a reader can open a child profile but cannot enrol them', async ({
  page,
}) => {
  await ministryFixture(page, ['children.read', 'people.read'])
  await page.goto('/children/programmes/programme-1')
  await page.getByLabel('Find a child', { exact: true }).fill('Lindiwe')
  await page
    .getByRole('option', { name: 'Lindiwe Mabasa', exact: true })
    .click()
  await expect(
    page.getByRole('button', { name: 'Enrol in programme', exact: true }),
  ).toHaveCount(0)
  await expect(
    page.getByRole('link', { name: 'Open ministry profile', exact: true }),
  ).toBeEnabled()
})
