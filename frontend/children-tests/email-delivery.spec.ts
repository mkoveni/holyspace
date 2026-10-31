import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'
const permissions = [
  'children.read',
  'children.manage',
  'children.safeguarding',
  'children.station',
  'children.supervise',
  'children.support',
  'people.read',
  'identity.manage',
  'children.communications.send',
  'children.incidents.manage',
]
async function emailFixture(page: Page) {
  const fixture = await ministryFixture(page, permissions)
  const requests: { path: string; method: string; body: any }[] = []
  let verified = false,
    queued = false
  const delivery = {
    id: 'delivery-1',
    communicationId: 'communication-1',
    status: 'SCHEDULED',
    scheduledAt: new Date(Date.now() + 3600000).toISOString(),
    attempts: 0,
    errorCode: '',
    captured: false,
    version: 0,
  }
  const communication = {
    id: 'communication-1',
    childId: 'child-1',
    guardianId: 'adult-1',
    programmeId: 'programme-1',
    sessionId: null,
    type: 'CONSENT_RENEWAL',
    dueOn: '2026-10-26',
    status: 'DRAFT',
    version: 0,
  }
  await page.route(
    '**/api/children/guardian-communications**',
    async (route) => {
      const req = route.request(),
        path = new URL(req.url()).pathname,
        body = req.postDataJSON()
      requests.push({ path, method: req.method(), body })
      if (path.endsWith('/verify-email')) {
        verified = true
        return route.fulfill({ status: 201, json: { id: 'verification-1' } })
      }
      if (path.endsWith('/queue-email')) {
        queued = true
        delivery.status = body.scheduledAt ? 'SCHEDULED' : 'QUEUED'
        delivery.scheduledAt = body.scheduledAt || new Date().toISOString()
        return route.fulfill({ status: 201, json: { id: 'delivery-1' } })
      }
      if (path.endsWith('/email-review'))
        return route.fulfill({
          json: {
            communicationId: 'communication-1',
            version: 0,
            message: 'Hello Solly, please review consent for Lindiwe.',
            recipients: [
              {
                contactId: 'contact-1',
                email: 'guardian@example.org',
                verificationId: verified ? 'verification-1' : null,
                validUntil: verified ? '2027-04-01T00:00:00Z' : null,
                reviewHash: verified ? 'reviewed-fingerprint' : null,
              },
            ],
            transport: {
              enabled: true,
              capture: true,
              mode: 'Local test inbox',
            },
          },
        })
      return route.fulfill({
        json: {
          communications: [
            {
              communication,
              childName: 'Lindiwe Mokoena',
              guardianName: 'Solly Mabasa',
              delivery: queued ? delivery : null,
            },
          ],
          total: 1,
          overdue: 0,
        },
      })
    },
  )
  await page.route('**/api/children/email-deliveries**', async (route) => {
    const req = route.request(),
      path = new URL(req.url()).pathname,
      body = req.postDataJSON()
    requests.push({ path, method: req.method(), body })
    if (path.endsWith('/cancel')) {
      delivery.status = 'CANCELLED'
      delivery.version++
      return route.fulfill({ status: 204 })
    }
    if (path.endsWith('/delivery-1'))
      return route.fulfill({
        json: {
          delivery: {
            ...delivery,
            recipient: 'guardian@example.org',
            subject: 'Consent renewal request',
            body: 'Hello Solly, please review consent for Lindiwe.',
            messageId: '<delivery-1@onechurch.delivery>',
            approvedAt: new Date().toISOString(),
          },
          events: [
            {
              id: 'event-1',
              type: delivery.status,
              occurredAt: new Date().toISOString(),
              reference: 'Reviewed recipient and message approved.',
            },
          ],
        },
      })
    return route.fulfill({
      json: {
        deliveries: queued ? [delivery] : [],
        total: queued ? 1 : 0,
        failed: 0,
        waiting: queued && delivery.status !== 'CANCELLED' ? 1 : 0,
      },
    })
  })
  return {
    ...fixture,
    emailRequests: requests,
    uncertain: () => {
      queued = true
      delivery.status = 'UNKNOWN'
      delivery.attempts = 1
      delivery.errorCode = 'SMTP_UNCERTAIN'
    },
  }
}
for (const width of [390, 1440])
  test(`verify, review and approve scheduled guardian email at ${width}px`, async ({
    page,
  }) => {
    const fixture = await emailFixture(page)
    await page.setViewportSize({ width, height: 1000 })
    await page.goto('/children/communications')
    await page
      .getByRole('button', { name: 'Review email delivery', exact: true })
      .click()
    const dialog = page.getByRole('dialog')
    await dialog
      .getByLabel('Guardian email *', { exact: true })
      .press('ArrowDown')
    await page
      .getByRole('option', { name: 'guardian@example.org', exact: true })
      .click()
    await expect(
      dialog.getByRole('button', { name: 'Approve email', exact: true }),
    ).toBeDisabled()
    await dialog
      .getByLabel('How was this address verified with the guardian? *', {
        exact: true,
      })
      .fill('Confirmed address in person with guardian')
    await dialog
      .getByRole('checkbox', {
        name: 'I confirmed this address directly with the guardian.',
      })
      .check()
    await dialog
      .getByRole('button', { name: 'Record email verification', exact: true })
      .click()
    await expect(dialog.getByText(/Verified through/)).toBeVisible()
    await dialog
      .getByRole('checkbox', { name: 'Schedule for later', exact: true })
      .check()
    await dialog
      .getByRole('checkbox', {
        name: 'I reviewed this recipient, message and send time and approve this email.',
      })
      .check()
    await expect(
      dialog.getByRole('button', {
        name: 'Approve scheduled email',
        exact: true,
      }),
    ).toBeEnabled()
    expect(await dialog.evaluate((e) => e.scrollWidth <= e.clientWidth)).toBe(
      true,
    )
    await dialog.screenshot({
      path: `test-results/email-approval-${width}.png`,
      animations: 'disabled',
    })
    await dialog
      .getByRole('button', { name: 'Approve scheduled email', exact: true })
      .click()
    await expect(page).toHaveURL(/children\/email-deliveries/)
    const queued = fixture.emailRequests.find((r) =>
      r.path.endsWith('/queue-email'),
    )?.body
    expect(queued.verificationId).toBe('verification-1')
    expect(queued.reviewHash).toBe('reviewed-fingerprint')
    expect(queued.scheduledAt).toMatch(/Z$/)
    await page
      .getByRole('button', { name: 'Review delivery', exact: true })
      .click()
    await page
      .getByRole('button', { name: 'Cancel delivery', exact: true })
      .click()
    const confirm = page.getByRole('dialog').last()
    await confirm
      .getByLabel('Reason *', { exact: true })
      .fill('Session plans changed')
    await confirm
      .getByRole('button', { name: 'Cancel delivery', exact: true })
      .click()
    await expect(
      page.getByRole('dialog').first().getByText('Cancelled', { exact: true }),
    ).toBeVisible()
    expect(fixture.errors).toEqual([])
  })
test('uncertain deliveries offer reconciliation rather than retry or cancellation', async ({
  page,
}) => {
  const fixture = await emailFixture(page)
  fixture.uncertain()
  await page.goto('/children/email-deliveries')
  await page.getByRole('button', { name: 'Review delivery' }).click()
  const dialog = page.getByRole('dialog')
  await expect(
    dialog.getByText(/Check the provider using this delivery/),
  ).toBeVisible()
  await expect(
    dialog.getByRole('button', { name: 'Review & retry' }),
  ).toHaveCount(0)
  await expect(
    dialog.getByRole('button', { name: 'Cancel delivery' }),
  ).toHaveCount(0)
  expect(fixture.errors).toEqual([])
})
