import type { Page } from '@playwright/test'
import { expect,test } from '@playwright/test'
import { ministryFixture } from './fixture'
async function fixture(page:Page,write=true,configured=true){
 const {errors}=await ministryFixture(page,write?['payments.manage','payments.refund','payments.reconcile','payments.receipts.send','people.read','giving.read']:['payments.manage'])
 const requests:{method:string,path:string,body:any}[]=[]
 const payment={id:'payment-1',donorId:'person-1',donorName:'Solly Mabasa',fundId:'fund-1',amountMinor:10000,provider:'payfast',mode:'test',recurring:true,status:'PAID',subscriptionStatus:'ACTIVE',reviewReason:null as string|null,version:2,createdAt:new Date().toISOString()}
 const charge={id:'charge-1',reference:'pf_12345',amountMinor:10000,receivedOn:new Date().toISOString().slice(0,10),giftId:null as string|null}
 const row={charge,refundedMinor:0,settlement:null as any,needsReview:true}
 const detail={order:payment,charges:[row],refunds:[] as any[],history:[] as any[]}
 await page.route('**/api/people**',route=>route.fulfill({json:[{id:'person-1',details:{firstName:'Solly',lastName:'Mabasa'},membershipType:'ACTIVE_MEMBER',ageCategory:'ADULT',archivedAt:null,version:0}]}))
 await page.route('**/api/giving/funds**',route=>route.fulfill({json:{items:[{id:'fund-1',name:'General',active:true,version:0}],total:1}}))
 await page.route('**/api/payments**',async route=>{
  const request=route.request(),path=new URL(request.url()).pathname,method=request.method(),body=request.postDataJSON();requests.push({method,path,body})
  if(method==='GET'&&path.endsWith('/providers'))return route.fulfill({json:[{id:'payfast',name:'Payfast',available:configured,mode:'test',recurring:true},{id:'yoco',name:'Yoco',available:configured,mode:'test',recurring:false}]})
  if(path==='/api/payments'&&method==='GET')return route.fulfill({json:{items:[payment],total:1}})
  if(path==='/api/payments'&&method==='POST'){payment.status='PENDING';payment.recurring=body.recurring;return route.fulfill({status:201,json:{id:'payment-1'}})}
  if(method==='GET'&&path==='/api/payments/payment-1')return route.fulfill({json:detail})
  if(method==='POST'&&path.endsWith('/checkout'))return route.fulfill({status:503,json:{message:'Checkout creation is unconfirmed. Retry this same request.'}})
  if(method==='POST'&&path.endsWith('/refunds')){row.refundedMinor+=body.amountMinor;detail.refunds.push({id:'refund-1',reference:body.providerReference,amountMinor:body.amountMinor,refundedOn:body.refundedOn,applied:true,reason:body.reason});row.needsReview=true;return route.fulfill({status:204})}
  if(method==='POST'&&path.endsWith('/reviews')){row.settlement={...body,version:1,refundedMinor:row.refundedMinor};row.needsReview=false;return route.fulfill({status:201,json:{id:'review-1'}})}
  if(method==='POST'&&path.endsWith('/subscription')){payment.subscriptionStatus=body.cancel?'CANCELLED':'ACTIVE';payment.version++;return route.fulfill({status:204})}
  errors.push(`Unexpected payment API ${method} ${path}`);return route.fulfill({status:500,json:{message:'Unexpected endpoint'}})
 })
 return {requests,errors,payment,row}
}
for(const width of [390,1440]){
 test(`Payment register and evidence work at ${width}px`,async({page})=>{
  await page.setViewportSize({width,height:1000});const {errors}=await fixture(page);await page.goto('/payments')
  await expect(page.getByRole('heading',{name:'Every payment, accounted for.'})).toBeVisible();await page.getByRole('button',{name:'View payment',exact:true}).click()
  await expect(page.getByText('pf_12345',{exact:false})).toBeVisible();await expect(page.getByText('Sandbox evidence · no real gift posted.')).toBeVisible()
  await page.getByRole('button',{name:'Close payment',exact:true}).click();await expect(page.locator('.v-dialog.v-overlay')).toBeHidden()
  await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);await page.evaluate(()=>window.scrollTo(0,0));await page.screenshot({path:`test-results/payments-${width}.png`,fullPage:true});expect(errors).toEqual([])
 })
 test(`Create a checkout and safely retry an uncertain provider at ${width}px`,async({page})=>{
  await page.setViewportSize({width,height:1000});const {requests,errors}=await fixture(page);await page.goto('/payments');await page.getByRole('button',{name:'New online gift',exact:true}).click()
  const dialog=page.getByRole('dialog').filter({has:page.getByRole('heading',{name:'Start an online gift',exact:true})})
  await dialog.getByLabel('Donor',{exact:true}).fill('Solly');await page.getByRole('option',{name:'Solly Mabasa',exact:true}).click()
  await dialog.getByLabel('Fund',{exact:true}).fill('General');await page.getByRole('option',{name:'General',exact:true}).click();await dialog.getByLabel('Amount (ZAR)',{exact:true}).fill('100.00')
  await dialog.getByRole('button',{name:'Create checkout request',exact:true}).click();await expect(dialog).toBeHidden()
  expect(requests.find(r=>r.method==='POST'&&r.path==='/api/payments')?.body).toMatchObject({donorId:'person-1',fundId:'fund-1',amountMinor:10000,provider:'payfast',recurring:false})
  await page.getByRole('button',{name:'Continue to secure checkout',exact:true}).click();await expect(page.getByText('Checkout creation is unconfirmed. Retry this same request.',{exact:true})).toBeVisible()
  await page.getByRole('button',{name:'Continue to secure checkout',exact:true}).click();expect(requests.filter(r=>r.method==='POST'&&r.path==='/api/payments')).toHaveLength(1);expect(errors).toEqual([])
 })
}
test('Completed refunds and settlement reviews keep explicit evidence',async({page})=>{
 const {requests,errors,payment}=await fixture(page);payment.mode='live';await page.goto('/payments');await page.getByRole('button',{name:'View payment',exact:true}).click();await page.getByRole('button',{name:'Record completed refund',exact:true}).click()
 let dialog=page.getByRole('dialog').filter({has:page.getByRole('heading',{name:'Record a completed refund',exact:true})})
 await dialog.getByLabel('Provider refund reference',{exact:true}).fill('refund-123');await dialog.getByLabel('Refund amount (ZAR)',{exact:true}).fill('25.00');await dialog.getByLabel('Completion evidence',{exact:true}).fill('Completed in provider dashboard')
 await dialog.getByRole('button',{name:'Record completed refund',exact:true}).click();await expect(dialog).toBeHidden();await expect(page.getByText('Refunded ZAR 25.00',{exact:true})).toBeVisible()
 await page.getByRole('button',{name:'Review settlement',exact:true}).click();dialog=page.getByRole('dialog').filter({has:page.getByRole('heading',{name:'Match a provider settlement',exact:true})})
 await dialog.getByLabel('Settlement or bank reference',{exact:true}).fill('BANK-001');await dialog.getByLabel('Provider fees (ZAR)',{exact:true}).fill('3.00');await dialog.getByLabel('Bank amount (ZAR)',{exact:true}).fill('72.00');await dialog.getByLabel('Review evidence',{exact:true}).fill('Matched provider payout and bank statement')
 await dialog.getByRole('button',{name:'Save settlement review',exact:true}).click();await expect(dialog).toBeHidden();await expect(page.getByText('Settlement matched',{exact:true})).toBeVisible()
 expect(requests.find(r=>r.path.endsWith('/refunds'))?.body).toMatchObject({chargeId:'charge-1',providerReference:'refund-123',amountMinor:2500});expect(requests.find(r=>r.path.endsWith('/reviews'))?.body).toMatchObject({expectedVersion:0,feeMinor:300,bankMinor:7200});expect(errors).toEqual([])
})
test('Monthly cancellation is an explicit confirmed action',async({page})=>{
 const {requests,errors}=await fixture(page);await page.goto('/payments?order=payment-1');await page.getByRole('button',{name:'Cancel monthly giving',exact:true}).click()
 const dialog=page.getByRole('dialog').filter({has:page.getByRole('heading',{name:'Cancel monthly giving',exact:true})});await dialog.getByRole('button',{name:'Request cancellation',exact:true}).click();await expect(dialog).toBeHidden();await expect(page.getByText('Subscription: Cancelled',{exact:true})).toBeVisible();expect(requests.find(r=>r.path.endsWith('/subscription'))?.body).toMatchObject({expectedVersion:2,cancel:true});expect(errors).toEqual([])
})
test('Provider setup and separate permissions remain clear',async({page})=>{
 const {errors}=await fixture(page,false,false);await page.goto('/payments');await expect(page.getByText('Not configured',{exact:true})).toHaveCount(2);await expect(page.getByRole('button',{name:'New online gift',exact:true})).toHaveCount(0);await page.getByRole('button',{name:'View payment',exact:true}).click();await expect(page.getByRole('button',{name:'Record completed refund',exact:true})).toHaveCount(0);await expect(page.getByRole('button',{name:'Review settlement',exact:true})).toHaveCount(0);expect(errors).toEqual([])
})

test('Live posting recovery records Giving without a settlement form or another checkout', async ({ page }) => {
  const { payment, row, requests, errors } = await fixture(page)
  payment.mode = 'live'
  await page.route('**/api/payments/payment-1/posting-retries', async route => {
    requests.push({ method: 'POST', path: '/api/payments/payment-1/posting-retries', body: route.request().postDataJSON() })
    row.charge.giftId = 'gift-1'
    await route.fulfill({ json: { complete: true, message: 'Contribution recorded in Giving.' } })
  })
  await page.goto('/payments?order=payment-1')
  await expect(page.getByText(/Giving posting needs attention/)).toBeVisible()
  await page.getByRole('button', { name: 'Retry Giving posting', exact: true }).click()
  await expect(page.getByText('Contribution recorded in Giving.', { exact: true })).toBeVisible()
  expect(requests.some(r => r.path.endsWith('/reviews') || r.path.endsWith('/checkout'))).toBe(false)
  expect(errors).toEqual([])
})
test('Simulation evidence never asks for a bank settlement or a real donor confirmation', async ({ page }) => {
  await fixture(page)
  await page.goto('/payments?order=payment-1')
  await expect(page.getByText('Simulation · no bank settlement', { exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Review settlement', exact: true })).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Retry Giving posting', exact: true })).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Donor confirmation', exact: true })).toHaveCount(0)
})
test('A reviewed donor confirmation is queued once and capture is distinguished from delivery', async ({ page }) => {
  const { payment, row, errors } = await fixture(page)
  payment.mode = 'live'; row.charge.giftId = 'gift-1'
  let approved = 0, captured = false
  const review = { hash: 'review-hash', recipient: 'receipt@example.org', subject: 'Thank you for your contribution', html: '<h2>Thank you</h2><p>ZAR 100.00</p>', templateVersion: 0 }
  await page.route('**/api/payments/payment-1/receipts**', async route => {
    const request = route.request()
    if (request.method() === 'POST') { approved++; return route.fulfill({ status: 200, json: { id: 'delivery-1' } }) }
    return route.fulfill({ json: { contacts: [{ id: 'contact-1', value: 'receipt@example.org' }], preview: approved || new URL(request.url()).searchParams.has('contactId') ? review : null, receiptId: approved ? 'receipt-1' : null, delivery: approved ? { id: 'delivery-1', status: captured ? 'SENT' : 'QUEUED', captured, version: 1, errorCode: '' } : null } })
  })
  await page.setViewportSize({ width: 390, height: 1000 })
  await page.goto('/payments?order=payment-1')
  await page.getByRole('button', { name: 'Donor confirmation', exact: true }).click()
  const dialog = page.getByRole('dialog').filter({ hasText: 'Donor payment confirmation' }).last()
  await dialog.getByRole('combobox', { name: 'Donor email address', exact: true }).press('ArrowDown')
  await page.getByRole('option', { name: 'receipt@example.org', exact: true }).click()
  await expect(dialog.getByText('Subject: Thank you for your contribution', { exact: true })).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Approve confirmation', exact: true })).toBeDisabled()
  await dialog.getByLabel('I verified this email address belongs to the donor', { exact: true }).check()
  await dialog.getByLabel('Recipient verification evidence', { exact: true }).fill('Donor confirmed their own email address')
  await dialog.getByRole('button', { name: 'Approve confirmation', exact: true }).click()
  await expect(dialog.getByText('Approved · waiting for delivery.', { exact: true })).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Approve confirmation', exact: true })).toHaveCount(0)
  captured = true
  await dialog.getByRole('button', { name: 'Refresh delivery', exact: true }).click()
  await expect(dialog.getByText('Captured in the test inbox · not delivered to the donor.', { exact: true })).toBeVisible()
  expect(approved).toBe(1)
  await dialog.screenshot({ path: 'test-results/payment-receipt-390.png' })
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
  await dialog.getByRole('button', { name: 'Close', exact: true }).click()
  await expect(dialog).toBeHidden()
  expect(errors).toEqual([])
})
