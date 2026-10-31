import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'
async function donorFixture(page: Page, linked = true, admin = false) {
  const { errors } = await ministryFixture(page, admin ? ['identity.manage', 'giving.manage', 'people.read'] : [])
  const requests: { path: string, method: string, body: any }[] = []
  const order = { id: 'own-order', fundId: 'general', amountMinor: 10000, provider: 'payfast', mode: 'test', recurring: true, status: 'PAID', subscriptionStatus: 'ACTIVE', version: 2, createdAt: '2026-10-28T10:00:00Z' }
  const fund = { id: 'general', name: 'General giving', description: 'Supporting the life of our church.', active: true, enabled: true, version: 1 }
  const detail = { order, charges: [{ id: 'own-charge', amountMinor: 10000, refundedMinor: 0, receivedOn: '2026-10-28', givingStatus: 'SIMULATION', confirmationStatus: 'SENT', captured: true, confirmation: { subject: 'Thank you for your gift', html: '<h2>Thank you, Solly.</h2><p>ZAR 100.00 received. This is not a tax certificate.</p>' } }], refunds: [] }
  const overview = { donor: linked ? { id: 'donor', name: 'Solly Mabasa' } : null, funds: linked ? [fund] : [], providers: linked ? [{ id: 'payfast', name: 'Payfast', available: true, mode: 'test', recurring: true }, { id: 'yoco', name: 'Yoco', available: true, mode: 'test', recurring: false }] : [], payments: { items: linked ? [order] : [], total: linked ? 1 : 0 }, gifts: linked ? [{ id: 'own-gift', amountMinor: 25000, currency: 'ZAR', receivedOn: '2026-10-27', method: 'BANK', reversed: false, refundedMinor: 0 }] : [], giftTotal: linked ? 1 : 0 }
  let ambiguous = false
  await page.route('**/api/my-giving**', async route => {
    const req = route.request(), path = new URL(req.url()).pathname, method = req.method(), body = req.postDataJSON()
    requests.push({ path, method, body })
    if (path === '/api/my-giving') return route.fulfill({ json: overview })
    if (path === '/api/my-giving/payments' && method === 'POST') {
      if (ambiguous) { ambiguous = false; return route.fulfill({ status: 503, json: { message: 'Request outcome uncertain.' } }) }
      order.status = 'PENDING'; order.amountMinor = body.amountMinor; order.recurring = body.recurring; order.subscriptionStatus = body.recurring ? 'PENDING' : 'NONE'
      return route.fulfill({ status: 201, json: { id: order.id } })
    }
    if (path.endsWith('/subscription')) { order.subscriptionStatus = body.cancel ? 'CANCEL_REQUESTED' : order.subscriptionStatus; order.version++; return route.fulfill({ status: 204 }) }
    if (path.endsWith('/checkout')) return route.fulfill({ status: 503, json: { message: 'Provider could not confirm this checkout. Retry the same payment.' } })
    if (path.endsWith('/own-order')) return route.fulfill({ json: detail })
    return route.fulfill({ status: 404, json: { message: 'Payment was not found.' } })
  })
  const link = { accountId: 'account', accountName: 'Solly’s account', personId: 'donor', personName: 'Solly Mabasa', active: true, version: 1 }
  await page.route('**/api/giving/donor-access**', async route => {
    const req = route.request(), path = new URL(req.url()).pathname, method = req.method(), body = req.postDataJSON()
    requests.push({ path, method, body })
    if (method === 'GET') return route.fulfill({ json: { links: [link], funds: [fund], hasMore: false } })
    link.active = body.active; link.version++; return route.fulfill({ status: 204 })
  })
  await page.route('**/api/giving/online-funds/**', async route => { requests.push({ path: new URL(route.request().url()).pathname, method: 'PUT', body: route.request().postDataJSON() }); fund.enabled = !fund.enabled; fund.version++; return route.fulfill({ status: 204 }) })
  await page.route('**/api/users?**', route => route.fulfill({ json: { items: [{ id: 'new-account', displayName: 'Jane Doe', username: 'jane', email: 'jane@example.org', status: 'active' }] } }))
  await page.route('**/api/people?**', route => route.fulfill({ json: [{ id: 'new-person', details: { firstName: 'Jane', lastName: 'Doe', lifeStatus: 'LIVING' }, archivedAt: null }] }))
  return { errors, requests, order, overview, uncertain: () => { ambiguous = true } }
}
async function fillGift(page: Page) {
  await page.getByRole('combobox', { name: 'Give towards', exact: true }).press('ArrowDown')
  await page.getByRole('option', { name: 'General giving', exact: true }).click()
  await page.getByLabel('Amount (ZAR)', { exact: true }).fill('150.50')
  await page.getByLabel('I have checked the amount and selected fund.', { exact: true }).check()
}
for (const width of [390, 1440]) {
  test(`Donor giving and history stay usable at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 1000 }); const { errors } = await donorFixture(page)
    await page.goto('/my-giving')
    await expect(page.getByRole('heading', { name: 'Thank you, Solly Mabasa.' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Giving history' })).toBeVisible()
    await expect(page.getByText('ZAR 250.00', { exact: true })).toBeVisible()
    await expect(page.getByText('Settlement', { exact: false })).toHaveCount(0)
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
    await page.evaluate(() => window.scrollTo(0, 0)); await page.screenshot({ path: `test-results/my-giving-${width}.png`, fullPage: true }); expect(errors).toEqual([])
  })
}
test('Unlinked donor has a clear verification path and no financial data', async ({ page }) => {
  const { errors } = await donorFixture(page, false); await page.goto('/my-giving')
  await expect(page.getByRole('heading', { name: 'Let’s connect your giving.' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Continue to payment', exact: true })).toHaveCount(0)
  await expect(page.getByText('ZAR 250.00', { exact: true })).toHaveCount(0); expect(errors).toEqual([])
})
test('Give online derives donor identity on the server and resumes the same checkout', async ({ page }) => {
  const { errors, requests } = await donorFixture(page); await page.goto('/my-giving'); await fillGift(page)
  await page.getByRole('button', { name: 'Continue to payment', exact: true }).click()
  const dialog = page.getByRole('dialog'); await expect(dialog.getByText('ZAR 150.50', { exact: true })).toBeVisible()
  const started = requests.find(r => r.method === 'POST' && r.path === '/api/my-giving/payments')!
  expect(started.body).toMatchObject({ fundId: 'general', amountMinor: 15050, provider: 'payfast', recurring: false }); expect(started.body.donorId).toBeUndefined()
  await dialog.getByRole('button', { name: 'Open secure checkout', exact: true }).click(); await expect(dialog.getByText('Provider could not confirm this checkout. Retry the same payment.')).toBeVisible()
  await dialog.getByRole('button', { name: 'Open secure checkout', exact: true }).click()
  expect(requests.filter(r => r.path.endsWith('/checkout'))).toHaveLength(2); expect(requests.filter(r => r.method === 'POST' && r.path === '/api/my-giving/payments')).toHaveLength(1); expect(errors).toEqual([])
})
test('Uncertain gift request freezes its intent and retries the same identifier', async ({ page }) => {
  const { requests, uncertain, errors } = await donorFixture(page); uncertain(); await page.goto('/my-giving'); await fillGift(page)
  await page.getByRole('button', { name: 'Continue to payment', exact: true }).click()
  await expect(page.getByLabel('Amount (ZAR)', { exact: true })).toBeDisabled()
  await page.getByRole('button', { name: 'Retry same gift request', exact: true }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  const starts = requests.filter(r => r.method === 'POST' && r.path === '/api/my-giving/payments'); expect(starts).toHaveLength(2); expect(starts[0]!.body).toEqual(starts[1]!.body); expect(errors).toEqual([])
})
test('Monthly cancellation requires confirmation and shows provider-pending state', async ({ page }) => {
  const { requests, errors } = await donorFixture(page); await page.goto('/my-giving?order=own-order')
  const dialog = page.getByRole('dialog'); await expect(dialog).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Request cancellation', exact: true })).toBeDisabled()
  await dialog.getByLabel('I want to cancel future monthly payments.', { exact: true }).check()
  await dialog.getByRole('button', { name: 'Request cancellation', exact: true }).click()
  await expect(dialog.getByText('Cancellation has been requested.', { exact: false })).toBeVisible()
  expect(requests.find(r => r.path.endsWith('/subscription'))?.body).toEqual({ expectedVersion: 2, cancel: true }); expect(errors).toEqual([])
})
test('Donor administration verifies identity, revokes access and controls online funds on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 1000 }); const { requests, errors } = await donorFixture(page, true, true)
  await page.goto('/administration/donor-access'); await page.getByRole('button', { name: 'Verify donor access', exact: true }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByRole('combobox', { name: 'User account', exact: true }).fill('Jane')
  await page.getByRole('option', { name: 'Jane Doe · jane@example.org', exact: true }).click()
  await dialog.getByRole('combobox', { name: 'People record', exact: true }).fill('Jane')
  await page.getByRole('option', { name: 'Jane Doe', exact: true }).click()
  await dialog.getByLabel('Identity verification evidence', { exact: true }).fill('Account holder and identity verified in person')
  await expect(dialog.getByRole('button', { name: 'Confirm verified access', exact: true })).toBeDisabled()
  await dialog.getByLabel('I verified this account holder’s identity and People record.', { exact: true }).check()
  await dialog.getByRole('button', { name: 'Confirm verified access', exact: true }).click(); await expect(dialog).toBeHidden()
  expect(requests.find(r => r.path.endsWith('/new-account'))?.body).toMatchObject({ personId: 'new-person', active: true, expectedVersion: 0 })
  await expect(page.locator('.v-dialog.v-overlay')).toBeHidden()
  await page.getByRole('button', { name: 'Revoke access', exact: true }).click()
  await dialog.getByLabel('Revocation reason', { exact: true }).fill('Access no longer required'); await dialog.getByLabel('I confirm this access should be revoked.', { exact: true }).check()
  await dialog.getByRole('button', { name: 'Revoke access', exact: true }).click(); await expect(dialog).toBeHidden()
  await page.getByRole('checkbox', { name: 'Offered online', exact: true }).uncheck()
  await expect(page.getByRole('checkbox', { name: 'Not offered', exact: true })).not.toBeChecked()
  expect(requests.find(r => r.path.endsWith('/online-funds/general'))?.body).toEqual({ enabled: false, expectedVersion: 1 })
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
  await page.evaluate(() => window.scrollTo(0,0)); await page.screenshot({ path: 'test-results/donor-access-390.png', fullPage: true }); expect(errors).toEqual([])
})
test('A donor without staff permissions lands in My giving after login', async ({ page }) => {
  const { errors } = await donorFixture(page); await page.goto('/')
  await expect(page).toHaveURL(/\/my-giving$/); await expect(page.getByRole('heading', { name: 'Generosity, made personal.' })).toBeVisible(); expect(errors).toEqual([])
})

test('Donor confirmation is viewable as a frozen, sandboxed original', async ({ page }) => {
  const { errors } = await donorFixture(page); await page.goto('/my-giving?order=own-order')
  const dialog = page.getByRole('dialog'); await dialog.getByRole('button', { name: 'View original payment confirmation', exact: true }).click()
  await expect(dialog.getByText('Thank you for your gift', { exact: true })).toBeVisible()
  const preview = page.frameLocator('iframe[title="Your payment confirmation"]')
  await expect(preview.getByText('This is not a tax certificate.', { exact: false })).toBeVisible()
  await expect(page.locator('iframe[title="Your payment confirmation"]')).toHaveAttribute('sandbox', '')
  await expect(dialog.getByText('Captured locally · not delivered', { exact: false })).toBeVisible(); expect(errors).toEqual([])
})
