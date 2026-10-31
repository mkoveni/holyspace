import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'
async function communicationFixture(page: Page) {
  const fixture = await ministryFixture(page)
  const requests: { path: string; body: any; method: string }[] = []
  let prepared = false
  const communication = {
    id: 'message-1',
    childId: 'child-1',
    authorityId: 'authority-1',
    guardianId: 'adult-1',
    programmeId: 'programme-1',
    policyId: 'policy-1',
    sessionId: null,
    type: 'CONSENT_RENEWAL',
    message:
      'Hello Solly, please contact the ministry team to renew consent for Lindiwe.',
    dueOn: '2026-10-26',
    status: 'DRAFT',
    createdBy: 'account-1',
    createdAt: new Date().toISOString(),
    updatedBy: 'account-1',
    updatedAt: new Date().toISOString(),
    contactReference: '',
    version: 0,
  }
  await page.route('**/api/children/safeguarding/child-1', (route) =>
    route.fulfill({
      json: {
        authorities: [
          {
            id: 'authority-1',
            childId: 'child-1',
            guardianId: 'adult-1',
            validFrom: '2026-01-01T00:00:00Z',
            validUntil: '2030-01-01T00:00:00Z',
            evidenceReference: 'Verified',
            revokedAt: null,
            version: 0,
          },
        ],
        consents: [],
        pickupAuthorizations: [],
      },
    }),
  )
  await page.route('**/api/people/adult-1', (route) =>
    route.fulfill({
      json: {
        id: 'adult-1',
        details: {
          firstName: 'Solly',
          lastName: 'Mabasa',
          dateOfBirth: '1980-01-01',
          lifeStatus: 'LIVING',
          gender: 'MALE',
        },
        ageCategory: 'ADULT',
        membershipType: 'ACTIVE_MEMBER',
        version: 0,
      },
    }),
  )
  await page.route(
    '**/api/children/guardian-communications**',
    async (route) => {
      const req = route.request(),
        path = new URL(req.url()).pathname,
        body = req.postDataJSON()
      requests.push({ path, body, method: req.method() })
      if (path.endsWith('/contact')) {
        communication.status = body.status
        communication.contactReference = body.reference
        communication.version++
        return route.fulfill({ status: 204 })
      }
      if (req.method() === 'POST') {
        prepared = true
        return route.fulfill({ status: 201, json: { id: communication.id } })
      }
      if (path.endsWith('/message-1'))
        return route.fulfill({ json: communication })
      return route.fulfill({
        json: {
          communications:
            prepared && communication.status === 'DRAFT'
              ? [
                  {
                    communication,
                    childName: 'Lindiwe Mabasa',
                    guardianName: 'Solly Mabasa',
                  },
                ]
              : [],
          total: prepared && communication.status === 'DRAFT' ? 1 : 0,
          overdue: prepared ? 1 : 0,
        },
      })
    },
  )
  return { ...fixture, communicationRequests: requests }
}
for (const width of [390, 1440])
  test(`prepare consent wording and record manual contact at ${width}px`, async ({
    page,
  }) => {
    const fixture = await communicationFixture(page)
    await page.setViewportSize({ width, height: 1000 })
    await page.goto(
      '/children/communications?child=child-1&programme=programme-1',
    )
    await page
      .getByRole('button', { name: 'Prepare message', exact: true })
      .click()
    const dialog = page.getByRole('dialog')
    await dialog
      .getByLabel('Verified guardian *', { exact: true })
      .press('ArrowDown')
    await page
      .getByRole('option', { name: 'Solly Mabasa', exact: true })
      .click()
    await dialog
      .getByRole('button', { name: 'Prepare message', exact: true })
      .click()
    await expect(dialog).toHaveCount(0)
    await expect(
      page.getByText('Lindiwe Mabasa', { exact: true }),
    ).toBeVisible()
    await page
      .getByRole('button', { name: 'Review message', exact: true })
      .click()
    await expect(
      page.getByRole('dialog').getByText(/please contact the ministry team/),
    ).toBeVisible()
    await expect(
      page.getByText('This message has not been sent by this application.'),
    ).toBeVisible()
    await page
      .getByRole('dialog')
      .screenshot({
        path: `test-results/guardian-message-${width}.png`,
        animations: 'disabled',
      })
    await page
      .getByRole('button', { name: 'Record guardian contact', exact: true })
      .click()
    const contact = page.getByRole('dialog')
    await contact
      .getByLabel('Contact method and reference *', { exact: true })
      .fill('Phone conversation with guardian')
    await contact
      .getByRole('checkbox', {
        name: 'I confirm that contact was made with this guardian.',
      })
      .check()
    await contact
      .getByRole('button', { name: 'Record contact', exact: true })
      .click()
    await expect(contact).toHaveCount(0)
    expect(
      fixture.communicationRequests.find((r) => r.path.endsWith('/contact'))
        ?.body,
    ).toEqual({
      expectedVersion: 0,
      status: 'CONTACTED',
      reference: 'Phone conversation with guardian',
    })
    expect(
      fixture.requests.filter(
        (r) => r.path === '/api/children/consents' && r.method === 'POST',
      ),
    ).toEqual([])
    expect(fixture.errors).toEqual([])
  })
