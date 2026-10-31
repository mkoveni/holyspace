import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'

const permissions = [
  'people.read',
  'people.manage',
  'children.read',
  'children.manage',
  'children.station',
  'children.safeguarding',
  'children.incidents.manage',
]
for (const width of [390, 1440, 1920]) {
  test(`navigation fits and quick actions open real workflows at ${width}px`, async ({
    page,
  }) => {
    await page.setViewportSize({ width, height: 1000 })
    const { errors } = await ministryFixture(page, permissions)
    await page.route('**/api/children/team-planning**', (route) =>
      route.fulfill({
        json: {
          sessions: [],
          total: 0,
          summary: {
            sessions: 0,
            ready: 0,
            needingAttention: 0,
            staffGaps: 0,
            clashes: 0,
          },
        },
      }),
    )
    await page.route('**/api/children/guardian-communications**', (route) =>
      route.fulfill({ json: { communications: [], total: 0, overdue: 0 } }),
    )
    await page.goto('/children/sessions')
    await expect(
      page.getByRole('heading', { name: 'Good days start with good care.' }),
    ).toBeVisible()
    const content = await page.locator('.directory-workspace').boundingBox()
    const drawer = await page.locator('.app-drawer').boundingBox()
    if (width >= 960) {
      expect(content!.x - (drawer!.x + drawer!.width)).toBeLessThanOrEqual(26)
      expect(content!.width).toBeGreaterThan(width - 310)
      await expect(
        page.locator('.app-drawer .v-list-item[aria-current="page"]'),
      ).toHaveCount(1)
    }
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
      ),
    ).toBe(true)
    const nav = page.getByRole('navigation', {
      name: 'Children Ministry',
      exact: true,
    })
    if (width < 1200) {
      await nav
        .getByRole('button', { name: 'Choose Children Ministry section' })
        .click()
      await expect(
        page.getByRole('list', { name: 'Children Ministry sections' }),
      ).toBeVisible()
      await expect(
        page
          .getByRole('link', { name: 'Recurring schedules', exact: true })
          .last(),
      ).toBeVisible()
      await page.keyboard.press('Escape')
    } else {
      await nav.getByRole('button', { name: 'Planning navigation' }).click()
      await page.getByRole('list', { name: 'Planning', exact: true })
        .getByRole('link', { name: 'Team planning', exact: true })
        .click()
      await expect(page).toHaveURL(/children\/team-planning/)
    }
    await page
      .getByRole('button', { name: 'Quick actions', exact: true })
      .click()
    await page
      .getByRole('link', { name: /Plan session Prepare a children/ })
      .click()
    await expect(
      page.getByRole('heading', { name: 'Plan a little belonging' }),
    ).toBeVisible()
    await expect(page).not.toHaveURL(/action=create/)
    await page.getByRole('button', { name: 'Cancel', exact: true }).click()
    await page
      .getByRole('button', { name: 'Quick actions', exact: true })
      .click()
    await page.getByRole('link', { name: /Add person Welcome someone/ }).click()
    await expect(
      page.getByRole('heading', { name: 'A new connection' }),
    ).toBeVisible()
    await page.getByRole('button', { name: 'Close person form' }).click()
    await page
      .getByRole('button', { name: 'Quick actions', exact: true })
      .click()
    await page
      .getByRole('link', { name: /Prepare guardian message Start/ })
      .click()
    await expect(
      page.getByRole('heading', { name: 'A thoughtful message home' }),
    ).toBeVisible()
    await page.getByRole('button', { name: 'Cancel', exact: true }).click()
    await page.screenshot({
      path: `test-results/navigation-${width}.png`,
      fullPage: true,
      animations: 'disabled',
    })
    expect(errors).toEqual([])
  })
}

test('mobile sidebar closes after selection and only the exact destination is active', async ({
  page,
}) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await ministryFixture(page, permissions)
  await page.goto('/children/communications')
  await page.getByRole('button', { name: 'Toggle navigation' }).click()
  const drawer = page.locator('.app-drawer')
  await expect(drawer).toBeVisible()
  await expect(drawer.locator('[aria-current="page"]')).toHaveCount(1)
  await expect(drawer.locator('[aria-current="page"]')).toContainText(
    'Guardian communications',
  )
  await drawer.getByRole('link', { name: 'Programmes', exact: true }).click()
  await expect(page).toHaveURL(/\/children$/)
  await expect(drawer).not.toBeInViewport()
})

test('readers see no creation shortcuts and direct creation links do not open forms', async ({
  page,
}) => {
  await ministryFixture(page, ['children.read'])
  await page.goto('/children/sessions?action=create')
  await expect(
    page.getByRole('button', { name: 'Quick actions', exact: true }),
  ).toHaveCount(0)
  await expect(
    page.getByRole('heading', { name: 'Plan a little belonging' }),
  ).toHaveCount(0)
  await expect(page).not.toHaveURL(/action=create/)
  await page.getByRole('button', { name: 'Planning navigation' }).click()
  await expect(
    page.getByRole('link', { name: 'Team planning', exact: true }),
  ).toHaveCount(0)
})
