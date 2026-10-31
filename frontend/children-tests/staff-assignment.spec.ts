import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'
for (const mobile of [false, true]) {
  test(`register a selected team member without losing their assignment at ${mobile ? 'mobile' : 'desktop'} size`, async ({
    page,
  }) => {
    const fixture = await ministryFixture(page)
    await page.setViewportSize({ width: mobile ? 390 : 1440, height: 1000 })
    const person = {
      id: 'person-new',
      details: {
        firstName: 'Solly',
        lastName: 'Mabasa',
        dateOfBirth: '1980-02-12',
        gender: 'MALE',
        lifeStatus: 'LIVING',
        titleCode: null,
        dateOfDeath: null,
      },
      ageCategory: 'ADULT',
      membershipType: 'ACTIVE_MEMBER',
      version: 0,
      archivedAt: null,
    }
    let clearance: Record<string, unknown> | null = null
    let staffBody: Record<string, unknown> | null = null
    await page.route('**/api/people?**', route =>
      route.fulfill({
        json: new URL(route.request().url()).searchParams.get('search')?.includes('Solly')
          ? [person]
          : [],
      }),
    )
    await page.route('**/api/users?**', route =>
      route.fulfill({
        json: {
          items: [
            {
              id: 'account-new',
              username: 'solly',
              displayName: 'Solly Mabasa',
              status: 'active',
              personId: null,
            },
          ],
        },
      }),
    )
    await page.route('**/api/children/workers/person-new', route =>
      route.fulfill(
        clearance
          ? { json: clearance }
          : { status: 404, json: { message: 'Worker was not found.' } },
      ),
    )
    await page.route('**/api/children/workers', async route => {
      clearance = {
        ...route.request().postDataJSON(),
        verifiedFrom: new Date().toISOString(),
        verifiedBy: 'admin',
        revokedAt: null,
        version: 0,
      }
      await route.fulfill({ status: 204 })
    })
    await page.route('**/api/children/sessions/staff', async route => {
      staffBody = route.request().postDataJSON()
      await route.fulfill({ status: 204 })
    })
    await page.goto('/children/sessions/session-1')
    await page
      .getByRole('button', { name: 'Assign staff', exact: true })
      .click()
    const assignment = page
      .getByRole('dialog')
      .filter({
        has: page.getByRole('heading', {
          name: 'A team prepared to care',
          exact: true,
        }),
      })
    await assignment.getByLabel('Team member *', { exact: true }).fill('Solly')
    await page
      .getByRole('option', { name: 'Solly Mabasa', exact: true })
      .click()
    await expect(
      assignment
        .getByRole('alert')
        .filter({ hasText: 'not registered on the Children Ministry team' }),
    ).toBeVisible()
    await expect(
      assignment.getByRole('button', { name: 'Save changes', exact: true }),
    ).toBeDisabled()
    await assignment.getByLabel('Role *', { exact: true }).press('ArrowDown')
    await page.getByRole('option', { name: 'Leader', exact: true }).click()
    await assignment
      .getByLabel('This team member is present', { exact: true })
      .check()
    await assignment
      .getByRole('button', { name: 'Record team clearance', exact: true })
      .click()
    const setup = page
      .getByRole('dialog')
      .filter({
        has: page.getByRole('heading', { name: 'A trusted team', exact: true }),
      })
    await expect(setup.getByText('Solly Mabasa', { exact: true })).toBeVisible()
    await setup
      .getByLabel('Linked user account *', { exact: true })
      .fill('solly')
    await page
      .getByRole('option', { name: 'Solly Mabasa · solly', exact: true })
      .click()
    await setup
      .getByLabel('Assessment evidence reference *', { exact: true })
      .fill('Verified screening and training evidence')
    await setup
      .getByRole('button', { name: 'Save changes', exact: true })
      .click()
    await expect(setup).toHaveCount(0)
    await expect(
      assignment
        .getByRole('alert')
        .filter({ hasText: 'registration and clearance are recorded' }),
    ).toBeVisible()
    await expect(
      assignment.getByLabel('This team member is present', { exact: true }),
    ).toBeChecked()
    await assignment
      .getByRole('button', { name: 'Save changes', exact: true })
      .click()
    await expect(assignment).toHaveCount(0)
    expect(staffBody).toEqual({
      sessionId: 'session-1',
      workerId: 'person-new',
      role: 'LEADER',
      present: true,
      expectedVersion: 2,
    })
    expect(fixture.errors).toEqual([])
  })
}

test('coordinators without safeguarding access get a clear registration message without a protected clearance lookup', async ({
  page,
}) => {
  const fixture = await ministryFixture(page, [
    'children.manage',
    'children.read',
    'people.read',
    'children.station',
    'children.supervise',
  ])
  await page.route('**/api/children/sessions/staff', route =>
    route.fulfill({ status: 404, json: { message: 'Worker was not found.' } }),
  )
  await page.goto('/children/sessions/session-1')
  await page.getByRole('button', { name: 'Assign staff', exact: true }).click()
  const assignment = page.getByRole('dialog')
  await assignment.getByLabel('Team member *', { exact: true }).fill('Lindiwe')
  await page
    .getByRole('option', { name: 'Lindiwe Mabasa', exact: true })
    .click()
  await assignment
    .getByRole('button', { name: 'Save changes', exact: true })
    .click()
  await expect(
    assignment.getByText(
      /Ask a safeguarding coordinator to record team clearance/,
    ),
  ).toBeVisible()
  await expect(
    assignment.getByRole('button', {
      name: 'Record team clearance',
      exact: true,
    }),
  ).toHaveCount(0)
  expect(
    fixture.requests.some(request =>
      request.path.startsWith('/api/children/workers/'),
    ),
  ).toBe(false)
  expect(fixture.errors).toEqual([])
})
