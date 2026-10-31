import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'
async function fixture(page: Page, reader = false) {
  const { errors } = await ministryFixture(page, reader ? ['giving.read'] : ['giving.read', 'giving.manage', 'giving.reverse', 'people.read'])
  const funds = [
    { id: 'fund-1', name: 'General', description: 'Supporting everyday church life.', active: true, version: 0 },
    { id: 'fund-2', name: 'Missions', description: 'Serving our wider community.', active: true, version: 0 },
  ]
  const requests: { method: string; path: string; body: any }[] = []
  const gifts: any[] = [{ id: 'gift-1', donorId: 'person-1', donorName: 'Solly Mabasa', amountMinor: 12345, currency: 'ZAR', receivedOn: new Date().toISOString().slice(0, 10), method: 'BANK', externalReference: 'BANK-001', reversed: false }]
  let reversal: any = null, failNext = false
  await page.route('**/api/people**', route => route.fulfill({ json: [{ id: 'person-1', details: { firstName: 'Solly', lastName: 'Mabasa' }, membershipType: 'ACTIVE_MEMBER', ageCategory: 'ADULT', archivedAt: null }] }))
  await page.route('**/api/giving/**', async route => {
    const request = route.request(), url = new URL(request.url()), path = url.pathname, method = request.method(), body = request.postDataJSON()
    requests.push({ method, path, body })
    if (method === 'GET' && path.endsWith('/funds')) return route.fulfill({ json: { items: funds.filter(f => (url.searchParams.get('archived') === 'true' || f.active) && f.name.toLowerCase().includes((url.searchParams.get('search') || '').toLowerCase())), total: funds.length } })
    if (method === 'POST' && path.endsWith('/funds')) { funds.push({ id: body.requestId, name: body.name, description: body.description, active: true, version: 0 }); return route.fulfill({ status: 201, json: { id: body.requestId } }) }
    if (method === 'PUT' && path.includes('/funds/')) { Object.assign(funds.find(f => path.endsWith(f.id))!, body, { version: 1 }); return route.fulfill({ status: 204 }) }
    if (method === 'GET' && path.endsWith('/gifts')) return route.fulfill({ json: { items: gifts, total: gifts.length } })
    if (method === 'GET' && path.endsWith('/totals')) return route.fulfill({ json: [{ currency: 'ZAR', grossMinor: '12345', reversedMinor: reversal ? '12345' : '0', netMinor: reversal ? '0' : '12345' }] })
    if (method === 'POST' && path.endsWith('/gifts')) {
      if (failNext) { failNext = false; return route.fulfill({ status: 503, json: { message: 'Unable to confirm response. Retry the same request.' } }) }
      gifts.push({ ...body, id: body.requestId, donorName: body.donorId ? 'Solly Mabasa' : 'Anonymous', reversed: false })
      return route.fulfill({ status: 201, json: { id: body.requestId } })
    }
    if (method === 'POST' && path.endsWith('/reversals')) { reversal = { ...body, id: body.requestId, createdBy: 'account-1', createdAt: new Date().toISOString() }; gifts[0].reversed = true; return route.fulfill({ status: 201, json: { id: body.requestId } }) }
    if (method === 'GET' && path.endsWith('/acknowledgement')) return route.fulfill({ json: { acknowledgement: null } })
    if (method === 'GET' && path.includes('/gifts/')) return route.fulfill({ json: { gift: { ...gifts[0], money: { amountMinor: 12345, currency: 'ZAR' }, allocations: [{ fundId: 'fund-1', amountMinor: 12345 }], createdBy: 'account-1', createdAt: new Date().toISOString() }, donorName: 'Solly Mabasa', fundNames: { 'fund-1': 'General' }, reversal, history: [{ action: 'GIFT_POSTED', reason: '', actorId: 'account-1', occurredAt: new Date().toISOString() }] } })
    errors.push(`Unexpected giving API ${method} ${path}`)
    return route.fulfill({ status: 500, json: { message: 'Unexpected endpoint' } })
  })
  return { errors, requests, funds, fail: () => { failNext = true } }
}
async function chooseFund(page: Page, label = 'Fund 1', name = 'General') {
  await page.getByRole('dialog').getByLabel(label, { exact: true }).fill(name)
  await page.getByRole('option', { name, exact: true }).click()
}
for (const width of [390, 1440]) {
  test(`Giving ledger and evidence stay usable at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 1000 }); const { errors } = await fixture(page)
    await page.goto('/giving'); await expect(page.getByRole('heading', { name: 'Every gift, accounted for.' })).toBeVisible()
    await expect(page.getByText('Solly Mabasa', { exact: true })).toBeVisible()
    await page.getByRole('button', { name: 'View gift from Solly Mabasa' }).click()
    await expect(page.getByRole('dialog').getByRole('heading', { name: 'Fund allocations' })).toBeVisible()
    await expect(page.getByRole('dialog').getByText('General', { exact: true })).toBeVisible()
    await page.getByRole('button', { name: 'Close gift details' }).click()
    await expect(page.locator('.v-dialog.v-overlay')).toBeHidden()
    await page.evaluate(() => window.scrollTo(0,0))
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
    await page.screenshot({ path: `test-results/giving-${width}.png`, fullPage: true }); expect(errors).toEqual([])
  })
  test(`Record an exact gift with a named donor at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 1000 }); const { requests, errors } = await fixture(page)
    await page.goto('/giving'); await page.getByRole('button', { name: 'Record gift', exact: true }).click()
    const dialog = page.getByRole('dialog').filter({ has: page.getByRole('heading', { name: 'Record a gift', exact: true }) })
    await expect(dialog.getByRole('button', { name: 'Post gift', exact: true })).toBeDisabled()
    await dialog.getByLabel('Gift amount', { exact: true }).fill('123.45'); await chooseFund(page)
    await dialog.getByLabel('Anonymous gift', { exact: true }).uncheck()
    await dialog.getByLabel('Donor', { exact: true }).fill('Solly'); await page.getByRole('option', { name: 'Solly Mabasa', exact: true }).click()
    await dialog.getByRole('button', { name: 'Post gift', exact: true }).click(); await expect(dialog).toBeHidden()
    expect(requests.find(r => r.path.endsWith('/gifts') && r.method === 'POST')?.body).toMatchObject({ amountMinor: 12345, donorId: 'person-1', currency: 'ZAR', allocations: [{ fundId: 'fund-1', amountMinor: 12345 }] }); expect(errors).toEqual([])
  })
}
test('Fund creation and versioned archival keep a reviewable reason', async ({ page }) => {
  const { requests, errors } = await fixture(page); await page.goto('/giving'); await page.getByRole('tab', { name: 'Funds', exact: true }).click()
  await page.getByRole('button', { name: 'Create fund', exact: true }).click(); let dialog = page.getByRole('dialog')
  await dialog.getByLabel('Fund name', { exact: true }).fill('Building'); await dialog.getByLabel('Description', { exact: true }).fill('Future facilities')
  await dialog.getByRole('button', { name: 'Save changes', exact: true }).click(); await expect(dialog).toBeHidden()
  await page.getByRole('button', { name: 'Manage Building', exact: true }).click(); dialog = page.getByRole('dialog')
  await dialog.getByLabel('Accept new gifts', { exact: true }).uncheck(); await dialog.getByLabel('Change reason', { exact: true }).fill('Fund completed')
  await dialog.getByRole('button', { name: 'Save changes', exact: true }).click(); await expect(dialog).toBeHidden()
  expect(requests.find(r => r.method === 'PUT')?.body).toMatchObject({ expectedVersion: 0, active: false, reason: 'Fund completed' }); expect(errors).toEqual([])
})
test('Split allocations must balance and use distinct funds', async ({ page }) => {
  const { requests, errors } = await fixture(page); await page.goto('/giving'); await page.getByRole('button', { name: 'Record gift', exact: true }).click()
  const dialog = page.getByRole('dialog'); await dialog.getByLabel('Gift amount', { exact: true }).fill('100.00'); await chooseFund(page)
  await dialog.getByRole('button', { name: 'Add allocation', exact: true }).click(); await dialog.getByLabel('Allocation 1 amount', { exact: true }).fill('70.00')
  await chooseFund(page, 'Fund 2', 'Missions'); await dialog.getByLabel('Allocation 2 amount', { exact: true }).fill('29.99')
  await expect(dialog.getByRole('button', { name: 'Post gift', exact: true })).toBeDisabled()
  await dialog.getByLabel('Allocation 2 amount', { exact: true }).fill('30.00'); await dialog.getByRole('button', { name: 'Post gift', exact: true }).click(); await expect(dialog).toBeHidden()
  expect(requests.find(r => r.method === 'POST')?.body.allocations).toEqual([{ fundId: 'fund-1', amountMinor: 7000 }, { fundId: 'fund-2', amountMinor: 3000 }]); expect(errors).toEqual([])
})
test('A failed response reuses the gift request identifier on retry', async ({ page }) => {
  const { requests, errors, fail } = await fixture(page); await page.goto('/giving'); await page.getByRole('button', { name: 'Record gift', exact: true }).click()
  const dialog = page.getByRole('dialog'); await dialog.getByLabel('Gift amount', { exact: true }).fill('1.01'); await chooseFund(page); fail()
  await dialog.getByRole('button', { name: 'Post gift', exact: true }).click(); await expect(dialog.getByText('Unable to confirm response. Retry the same request.')).toBeVisible()
  await dialog.getByRole('button', { name: 'Post gift', exact: true }).click(); await expect(dialog).toBeHidden()
  const posts = requests.filter(r => r.method === 'POST'); expect(posts).toHaveLength(2); expect(posts[0]!.body.requestId).toBe(posts[1]!.body.requestId); expect(errors).toEqual([])
})
test('A full reversal requires a reason and retains original evidence', async ({ page }) => {
  const { requests, errors } = await fixture(page); await page.goto('/giving'); await page.getByRole('button', { name: 'View gift from Solly Mabasa' }).click()
  await page.getByRole('button', { name: 'Reverse gift', exact: true }).click()
  const dialog = page.getByRole('dialog').filter({ has: page.getByRole('heading', { name: 'Reverse this gift', exact: true }) })
  await dialog.getByLabel('Reversal reason', { exact: true }).fill('Duplicate bank entry'); await dialog.getByRole('button', { name: 'Confirm full reversal', exact: true }).click(); await expect(dialog).toBeHidden()
  await expect(page.getByText('The original gift remains in the ledger.', { exact: true })).toBeVisible()
  expect(requests.find(r => r.path.endsWith('/reversals'))?.body.reason).toBe('Duplicate bank entry'); expect(errors).toEqual([])
})
test('Giving readers cannot post gifts, manage funds or reverse entries', async ({ page }) => {
  const { requests, errors } = await fixture(page, true); await page.goto('/giving')
  await expect(page.getByRole('button', { name: 'Record gift', exact: true })).toHaveCount(0)
  await page.getByRole('button', { name: 'View gift from Solly Mabasa' }).click(); await expect(page.getByRole('button', { name: 'Reverse gift', exact: true })).toHaveCount(0)
  await page.getByRole('button', { name: 'Close gift details' }).click(); await page.getByRole('tab', { name: 'Funds', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Create fund', exact: true })).toHaveCount(0)
  expect(requests.every(r => r.method === 'GET')).toBe(true); expect(errors).toEqual([])
})

test('Giving quick action opens the form and consumes the action once', async ({ page }) => {
 const { errors }=await fixture(page);await page.goto('/giving?action=create')
 await expect(page.getByRole('dialog').getByRole('heading',{name:'Record a gift',exact:true})).toBeVisible()
 await expect(page).toHaveURL(/\/giving$/)
 await page.getByRole('dialog').getByRole('button',{name:'Cancel',exact:true}).click()
 expect(errors).toEqual([])
})
