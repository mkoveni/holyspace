import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'

for (const mobile of [false, true]) {
  test(`date and time selection preserves cancelled drafts at ${mobile ? 'mobile' : 'desktop'} size`, async ({
    page,
  }) => {
    await ministryFixture(page)
    await page.setViewportSize({ width: mobile ? 390 : 1440, height: 1000 })
    await page.goto('/children/settings')
    await page
      .getByRole('button', { name: 'Manage team clearance', exact: true })
      .click()
    const field = page.getByLabel('Screening valid until (local time) *', {
      exact: true,
    })
    const original = await field.inputValue()
    await field.press('Enter')
    const picker = page
      .getByRole('dialog')
      .filter({
        has: page.getByRole('heading', {
          name: 'Screening valid until (local time)',
          exact: true,
        }),
      })
    await expect(picker).toBeVisible()
    await picker.getByRole('button', { name: 'Tomorrow', exact: true }).click()
    await picker.getByLabel('Hour', { exact: true }).pressSequentially('14')
    await picker.getByLabel('Minute', { exact: true }).pressSequentially('37')
    await picker.getByRole('button', { name: 'Cancel', exact: true }).click()
    await expect(field).toHaveValue(original)
    await expect(field).toBeFocused()
    await field.press('Enter')
    await expect(picker).toBeVisible()
    await picker.getByRole('button', { name: 'Today', exact: true }).click()
    await picker.getByLabel('Hour', { exact: true }).pressSequentially('14')
    await picker.getByLabel('Minute', { exact: true }).pressSequentially('37')
    await page.screenshot({
      path: `test-results/date-time-${mobile ? 'mobile' : 'desktop'}.png`,
      fullPage: true,
      animations: 'disabled',
    })
    await picker
      .getByRole('button', { name: 'Use date & time', exact: true })
      .click()
    await expect(picker).toHaveCount(0)
    const expected = await page.evaluate(() =>
      new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
      }).format(new Date(new Date().setHours(14, 37, 0, 0))),
    )
    await expect(field).toHaveValue(expected)
    await field.press('Enter')
    await expect(picker.getByText('14', { exact: true }).first()).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(picker).toHaveCount(0)
    await expect(field).toHaveValue(expected)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
  })
}
