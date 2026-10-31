import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'
for (const mobile of [false, true]) {
  test(`programme roster supports search, status, paging and refresh at ${mobile ? 'mobile' : 'desktop'} size`, async ({
    page,
  }) => {
    await ministryFixture(page)
    await page.setViewportSize({ width: mobile ? 390 : 1440, height: 1000 })
    let enrolled = false
    const queries: URLSearchParams[] = []
    await page.route(
      '**/api/children/programmes/programme-1/children?**',
      async route => {
        const query = new URL(route.request().url()).searchParams
        queries.push(query)
        const total = enrolled ? 27 : 26
        const children = Array.from({ length: total }, (_, index) => ({
          enrolmentId: `enrolment-${index}`,
          childId: `child-${index}`,
          name:
            index === total - 1
              ? 'Lindiwe Mabasa'
              : `Child ${String(index).padStart(2, '0')}`,
          validFrom: '2026-01-01',
          validUntil: '2027-01-01',
          status: index === 0 ? 'ENDED' : 'ACTIVE',
          classes:
            index === 1 ? [{ id: 'class-1', name: 'Sunday Explorers' }] : [],
        }))
        const filtered = children.filter(
          child =>
            (!query.get('search')
              || child.name
                .toLowerCase()
                .includes(query.get('search')!.toLowerCase()))
              && (query.get('status') === 'ALL'
                || child.status === query.get('status')),
        )
        const offset = Number(query.get('offset'))
        await route.fulfill({
          json: {
            children: filtered.slice(offset, offset + 25),
            total: filtered.length,
          },
        })
      },
    )
    await page.route('**/api/children/enrolments', async route => {
      enrolled = true
      await route.fulfill({ status: 201, json: { id: 'fresh-enrolment' } })
    })
    await page.goto('/children/programmes/programme-1')
    const roster = page.locator('section').filter({
      has: page.getByRole('heading', {
        name: 'Enrolled children',
        exact: true,
      }),
    })
    await expect(
      roster.getByText('26 enrolments', { exact: true }),
    ).toBeVisible()
    await expect(
      roster.getByText('Sunday Explorers', { exact: true }),
    ).toBeVisible()
    await roster
      .getByRole('button', { name: 'Next children', exact: true })
      .click()
    await expect(
      roster.getByRole('link', { name: 'Lindiwe Mabasa', exact: true }),
    ).toBeVisible()
    await expect(
      roster.getByRole('button', { name: 'Next children', exact: true }),
    ).toBeDisabled()
    await roster
      .getByLabel('Search enrolled children', { exact: true })
      .fill('Lindiwe')
    await expect(roster.getByText('1 enrolment', { exact: true })).toBeVisible()
    expect(queries.at(-1)?.get('offset')).toBe('0')
    await expect(
      roster.getByRole('link', { name: 'Lindiwe Mabasa', exact: true }),
    ).toHaveAttribute(
      'href',
      '/children/participants/child-25?programme=programme-1',
    )
    await page.screenshot({
      path: `test-results/programme-roster-${mobile ? 'mobile' : 'desktop'}.png`,
      fullPage: true,
      animations: 'disabled',
    })
    await roster
      .getByLabel('Search enrolled children', { exact: true })
      .fill('missing')
    await expect(
      roster.getByRole('heading', { name: 'No matching enrolments' }),
    ).toBeVisible()
    await roster
      .getByRole('button', { name: 'Clear filters', exact: true })
      .click()
    await roster
      .getByLabel('Enrolment status', { exact: true })
      .press('ArrowDown')
    await page.getByRole('option', { name: 'Ended', exact: true }).click()
    await expect(roster.getByText('1 enrolment', { exact: true })).toBeVisible()
    await expect(
      roster.getByRole('link', { name: 'Child 00', exact: true }),
    ).toBeVisible()
    await page.getByLabel('Find a child', { exact: true }).fill('Lindiwe')
    await page
      .getByRole('option', { name: 'Lindiwe Mabasa', exact: true })
      .click()
    await page
      .getByRole('button', { name: 'Enrol in programme', exact: true })
      .click()
    const count = queries.length
    await page
      .getByRole('dialog')
      .getByRole('button', { name: 'Save changes', exact: true })
      .click()
    await expect(page.getByRole('dialog')).toHaveCount(0)
    await expect.poll(() => queries.length).toBeGreaterThan(count)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
  })
}
