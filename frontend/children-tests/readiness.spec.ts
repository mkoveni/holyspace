import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'
for (const mobile of [false, true]) {
  test(`guided preparation completes every essential at ${mobile ? 'mobile' : 'desktop'} size`, async ({
    page,
  }) => {
    const fixture = await ministryFixture(page)
    await page.setViewportSize({ width: mobile ? 390 : 1440, height: 1000 })
    const complete = new Set(['IDENTITY', 'POLICY'])
    const enrolment = {
      id: 'enrolment-1',
      childId: 'child-1',
      programmeId: 'programme-1',
      validFrom: '2026-01-01',
      validUntil: '2027-12-31',
      endedAt: null,
      version: 0,
      createdAt: new Date().toISOString(),
    }
    let authority: Record<string, unknown> | null = null
    const adult = {
      id: 'adult-1',
      details: {
        firstName: 'Solly',
        lastName: 'Mabasa',
        dateOfBirth: '1980-02-12',
        gender: 'MALE',
        lifeStatus: 'LIVING',
      },
      ageCategory: 'ADULT',
      membershipType: 'ACTIVE_MEMBER',
      version: 0,
      archivedAt: null,
      contacts: [],
      addresses: [],
    }
    await page.route('**/api/people?**', route =>
      route.fulfill({ json: [adult] }),
    )
    await page.route('**/api/people/adult-1', route =>
      route.fulfill({ json: adult }),
    )
    await page.route(
      '**/api/children/programmes/programme-1/children/child-1/readiness',
      route =>
        route.fulfill({
          json: {
            ready: complete.size === 7,
            completed: complete.size,
            total: 7,
            on: '2026-10-26',
            checkedAt: new Date().toISOString(),
            timeZone: 'Africa/Johannesburg',
            steps: [
              'IDENTITY',
              'ENROLMENT',
              'CLASS',
              'POLICY',
              'GUARDIAN',
              'CONSENT',
              'COLLECTOR',
            ].map(code => ({ code, complete: complete.has(code) })),
            enrolment: complete.has('ENROLMENT') ? enrolment : null,
          },
        }),
    )
    await page.route('**/api/children/enrolments**', async route => {
      if (route.request().method() === 'POST') {
        complete.add('ENROLMENT')
        await route.fulfill({ status: 201, json: { id: 'enrolment-1' } })
      } else {
        await route.fulfill({
          json: complete.has('ENROLMENT') ? [enrolment] : [],
        })
      }
    })
    await page.route('**/api/children/class-assignments**', async route => {
      if (route.request().method() === 'POST') {
        complete.add('CLASS')
        await route.fulfill({ status: 201, json: { id: 'assignment-1' } })
      } else {
        await route.fulfill({ json: [] })
      }
    })
    await page.route('**/api/children/safeguarding/child-1', route =>
      route.fulfill({
        json: {
          authorities: authority ? [authority] : [],
          consents: [],
          pickupAuthorizations: [],
        },
      }),
    )
    await page.route('**/api/children/guardian-authorities', async route => {
      authority = {
        ...route.request().postDataJSON(),
        id: 'authority-1',
        version: 0,
        revokedAt: null,
      }
      complete.add('GUARDIAN')
      await route.fulfill({ status: 201, json: { id: 'authority-1' } })
    })
    await page.route('**/api/children/consents', async route => {
      complete.add('CONSENT')
      await route.fulfill({ status: 201, json: { id: 'consent-1' } })
    })
    await page.route('**/api/children/pickup-authorizations', async route => {
      complete.add('COLLECTOR')
      await route.fulfill({ status: 201, json: { id: 'grant-1' } })
    })
    await page.route(
      '**/api/children/programmes/programme-1/children?**',
      route =>
        route.fulfill({
          json: {
            children: [
              {
                enrolmentId: enrolment.id,
                childId: 'child-1',
                name: 'Lindiwe Mabasa',
                validFrom: enrolment.validFrom,
                validUntil: enrolment.validUntil,
                status: 'ACTIVE',
                classes: [],
                readiness: {
                  ready: complete.size === 7,
                  completed: complete.size,
                  total: 7,
                },
              },
            ],
            total: 1,
          },
        }),
    )
    await page.goto('/children/participants/child-1?programme=programme-1')
    const panel = page.locator('#child-readiness')
    await expect(panel.getByText(/2 of 7 essentials complete/)).toBeVisible()
    await expect(
      panel.getByRole('button', { name: 'Place in class', exact: true }),
    ).toHaveCount(0)
    await panel
      .getByRole('button', { name: 'Enrol in programme', exact: true })
      .click()
    const save = () =>
      page
        .getByRole('dialog')
        .getByRole('button', { name: 'Save changes', exact: true })
        .click()
    await save()
    await expect(panel.getByText(/3 of 7 essentials complete/)).toBeVisible()
    await panel
      .getByRole('button', { name: 'Place in class', exact: true })
      .click()
    await page
      .getByRole('dialog')
      .getByLabel('Class *', { exact: true })
      .press('ArrowDown')
    await page
      .getByRole('option', { name: 'Sunday Explorers', exact: true })
      .click()
    await save()
    await expect(panel.getByText(/4 of 7 essentials complete/)).toBeVisible()
    await panel
      .getByRole('button', { name: 'Verify guardian', exact: true })
      .click()
    await page.getByLabel('Guardian *', { exact: true }).fill('Solly')
    await page
      .getByRole('option', { name: 'Solly Mabasa', exact: true })
      .click()
    await page
      .getByLabel('Evidence link or reference *', { exact: true })
      .fill('Verified guardian evidence')
    await save()
    await expect(panel.getByText(/5 of 7 essentials complete/)).toBeVisible()
    await panel
      .getByRole('button', { name: 'Record consent', exact: true })
      .click()
    await page
      .getByLabel('Evidence link or reference *', { exact: true })
      .fill('Family signed current policy')
    await save()
    await expect(panel.getByText(/6 of 7 essentials complete/)).toBeVisible()
    await panel
      .getByRole('button', { name: 'Authorize collector', exact: true })
      .click()
    await page
      .getByLabel('Authorized collector *', { exact: true })
      .fill('Solly')
    await page
      .getByRole('option', { name: 'Solly Mabasa', exact: true })
      .click()
    await page
      .getByLabel('Evidence link or reference *', { exact: true })
      .fill('Signed pickup authorization')
    await save()
    await expect(
      panel.getByText('Ready to attend', { exact: true }),
    ).toBeVisible()
    await expect(panel.getByText(/7 of 7 essentials complete/)).toBeVisible()
    await expect(
      panel.getByText(/Session check-in still verifies/),
    ).toBeVisible()
    expect(
      fixture.requests.some(
        request => request.path === '/api/children/support/access',
      ),
    ).toBe(false)
    await page.screenshot({
      path: `test-results/child-readiness-${mobile ? 'mobile' : 'desktop'}.png`,
      fullPage: true,
      animations: 'disabled',
    })
    await page.goto('/children/programmes/programme-1')
    await expect(
      page.getByRole('link', {
        name: 'Ready to attend: Lindiwe Mabasa',
        exact: true,
      }),
    ).toBeVisible()
    await expect(
      page.getByRole('link', {
        name: 'Ready to attend: Lindiwe Mabasa',
        exact: true,
      }),
    ).toHaveAttribute(
      'href',
      '/children/participants/child-1?programme=programme-1',
    )
    expect(fixture.errors).toEqual([])
  })
}

test('readers can see preparation flags without fetching safeguarding or care payloads', async ({
  page,
}) => {
  const fixture = await ministryFixture(page, ['children.read', 'people.read'])
  await page.goto('/children/participants/child-1?programme=programme-1')
  const panel = page.locator('#child-readiness')
  await expect(
    panel.getByText('Needs attention', { exact: true }),
  ).toBeVisible()
  await expect(
    panel.getByRole('button', { name: 'Verify guardian', exact: true }),
  ).toHaveCount(0)
  await expect(
    panel.getByText(
      'A safeguarding coordinator can verify guardian authority.',
    ),
  ).toBeVisible()
  expect(
    fixture.requests.some(
      request =>
        request.path.includes('/safeguarding/')
        || request.path === '/api/children/support/access',
    ),
  ).toBe(false)
})
