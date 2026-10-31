import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'
async function fixture(page: Page, reader = false) {
 const { errors }=await ministryFixture(page,reader?['giving.read']:['giving.read','giving.manage','giving.acknowledge','giving.reconcile','giving.export','giving.reverse','people.read'])
 const requests: {method: string;path: string;body: any}[]=[]
 const today=new Date().toISOString().slice(0,10),year=today.slice(0,4)
 const fund={id:'fund-1',name:'General',description:'Church life',active:true,version:0}
 const gift={id:'gift-1',donorId:'person-1',donorName:'Solly Mabasa',amountMinor:10000,currency:'ZAR',receivedOn:today,method:'BANK',externalReference:'BANK-001',reversed:false,reconciled:false}
 const pledge={id:'pledge-1',donorId:'person-1',fundId:fund.id,target:{amountMinor:20000,currency:'ZAR'},startsOn:year+'-01-01',endsOn:year+'-12-31',status:'OPEN',version:0,cancellationReason:null}
 const summary={pledge,donorName:'Solly Mabasa',fundName:'General',creditedMinor:0,remainingMinor:20000,fulfilled:false}
 const assignments: any[]=[],history: any[]=[]
 let ack: any=null
 const batch={id:'batch-1',reference:'BANK-BATCH-001',expectedMinor:11000,actualMinor:10000,currency:'ZAR',reconciledOn:today,reason:'Bank correction awaiting review',giftIds:[gift.id]}
 const batchSummary={batch,reversedMinor:0,currentNetMinor:10000,version:0,review:null as any,needsReview:true}
 const reviews: any[]=[]
 const report={donorId:'person-1',donorName:'Solly Mabasa',from:year+'-01-01',to:today,generatedAt:new Date().toISOString(),lines:[{giftId:gift.id,kind:'GIFT',date:today,donorId:'person-1',donorName:'Solly Mabasa',fundId:fund.id,fundName:'General',currency:'ZAR',amountMinor:10000,method:'BANK',reference:'BANK-001',reason:''}],totals:[{currency:'ZAR',grossMinor:'10000',reversedMinor:'0',netMinor:'10000'}]}
 await page.route('**/api/people**',route=>route.fulfill({json:[{id:'person-1',details:{firstName:'Solly',lastName:'Mabasa'},membershipType:'ACTIVE_MEMBER',ageCategory:'ADULT',archivedAt:null,version:0}]}))
 await page.route('**/api/giving/**',async route=>{
  const request=route.request(),url=new URL(request.url()),path=url.pathname,method=request.method(),body=request.postDataJSON();requests.push({method,path,body})
  if(method==='GET' && path.endsWith('/funds'))return route.fulfill({json:{items:[fund],total:1}})
  if(method==='GET' && path.endsWith('/pledges'))return route.fulfill({json:{items:[summary],total:1}})
  if(method==='POST' && path.endsWith('/pledges'))return route.fulfill({status:201,json:{id:'pledge-2'}})
  if(method==='GET' && path.endsWith('/pledges/pledge-1'))return route.fulfill({json:{summary,assignments,history}})
  if(method==='GET' && path.endsWith('/candidates'))return route.fulfill({json:[{giftId:gift.id,receivedOn:today,availableMinor:10000,reference:gift.externalReference,method:gift.method}]})
  if(method==='POST' && path.endsWith('/assignments')){assignments.push({assignment:{id:'assignment-1',giftId:gift.id,amountMinor:body.amountMinor,status:'ACTIVE',version:0,cancellationReason:null},receivedOn:today,giftReversed:false});summary.creditedMinor=body.amountMinor;summary.remainingMinor=20000-body.amountMinor;pledge.version++;return route.fulfill({status:201,json:{id:'assignment-1'}})}
  if(method==='POST' && path.includes('/pledge-assignments/')){assignments[0].assignment.status='CANCELLED';assignments[0].assignment.cancellationReason=body.reason;summary.creditedMinor=0;summary.remainingMinor=20000;pledge.version++;return route.fulfill({status:204})}
  if(method==='POST' && path.endsWith('/pledge-1/cancel')){pledge.status='CANCELLED';pledge.cancellationReason=body.reason;return route.fulfill({status:204})}
  if(method==='GET' && path.includes('/statements/'))return route.fulfill({json:report})
  if(method==='GET' && path.endsWith('/ledger.csv'))return route.fulfill({contentType:'text/csv',body:'date,kind,amount_minor\n'+today+',GIFT,10000\n'})
  if(method==='GET' && path.endsWith('/gifts'))return route.fulfill({json:{items:[gift],total:1}})
  if(method==='GET' && path.endsWith('/totals'))return route.fulfill({json:report.totals})
  if(method==='GET' && path.endsWith('/gift-1/acknowledgement'))return route.fulfill({json:{acknowledgement:ack}})
  if(method==='POST' && path.endsWith('/gift-1/acknowledgements')){ack={id:'ack-1',giftId:gift.id,number:'G-00000001',donorName:gift.donorName,issuedOn:today,receivedOn:today,amountMinor:10000,currency:'ZAR',method:'BANK',reference:'BANK-001',voided:false,reversedOn:null,reversalReason:null,lines:[{fundId:fund.id,fundName:fund.name,amountMinor:10000}]};return route.fulfill({status:201,json:{id:ack.id}})}
  if(method==='GET' && path.includes('/acknowledgements/'))return route.fulfill({json:ack || {id:'ack-void',giftId:gift.id,number:'G-00000002',donorName:gift.donorName,issuedOn:today,receivedOn:today,amountMinor:10000,currency:'ZAR',method:'BANK',reference:'BANK-001',voided:true,reversedOn:today,reversalReason:'Duplicate bank entry',lines:[{fundId:fund.id,fundName:fund.name,amountMinor:10000}]}})
  if(method==='GET' && path.endsWith('/gifts/gift-1'))return route.fulfill({json:{gift:{...gift,money:{amountMinor:10000,currency:'ZAR'},allocations:[{fundId:fund.id,amountMinor:10000}],createdBy:'account-1',createdAt:new Date().toISOString()},donorName:gift.donorName,fundNames:{[fund.id]:fund.name},reversal:null,history:[]}})
  if(method==='GET' && path.endsWith('/reconciliations'))return route.fulfill({json:{items:[batchSummary],total:1}})
  if(method==='POST' && path.endsWith('/reconciliations')){gift.reconciled=true;return route.fulfill({status:201,json:{id:'batch-2'}})}
  if(method==='GET' && path.endsWith('/reconciliations/batch-1'))return route.fulfill({json:{summary:batchSummary,items:[{giftId:gift.id,donorName:gift.donorName,receivedOn:today,amountMinor:10000,reversed:false}],reviews}})
  if(method==='POST' && path.endsWith('/reviews')){const review={id:'review-1',version:1,outcome:body.outcome,note:body.note,reversedMinor:0,createdBy:'account-1',createdAt:new Date().toISOString()};reviews.push(review);batchSummary.review=review;batchSummary.version=1;batchSummary.needsReview=body.outcome!=='EXPLAINED';return route.fulfill({status:201,json:{id:review.id}})}
  errors.push(`Unexpected Giving API ${method} ${path}`);return route.fulfill({status:500,json:{message:'Unexpected endpoint'}})
 })
 return {requests,errors,summary,assignments,batchSummary,report,gift}
}
for(const width of [390,1440]){
 test(`Create and explicitly fulfil a pledge at ${width}px`,async({page})=>{
  await page.setViewportSize({width,height:1000});const {requests,errors}=await fixture(page);await page.goto('/giving/pledges')
  await page.getByRole('button',{name:'Create pledge',exact:true}).click();let dialog=page.getByRole('dialog').filter({has:page.getByRole('heading',{name:'Create a pledge',exact:true})})
  await dialog.getByLabel('Pledge donor',{exact:true}).fill('Solly');await page.getByRole('option',{name:'Solly Mabasa',exact:true}).click()
  await dialog.getByLabel('Pledge fund',{exact:true}).fill('General');await page.getByRole('option',{name:'General',exact:true}).click()
  await dialog.getByLabel('Target amount',{exact:true}).fill('200.00');await dialog.getByRole('button',{name:'Create pledge',exact:true}).click();await expect(dialog).toBeHidden()
  expect(requests.find(r=>r.method==='POST' && r.path.endsWith('/pledges'))?.body).toMatchObject({donorId:'person-1',fundId:'fund-1',amountMinor:20000,currency:'ZAR'})
  await page.getByRole('button',{name:'Open pledge',exact:true}).click();await page.getByRole('button',{name:'Assign a gift',exact:true}).click()
  dialog=page.getByRole('dialog').filter({has:page.getByRole('heading',{name:'Assign a gift',exact:true})})
  await dialog.getByLabel('Eligible gift',{exact:true}).press('ArrowDown');await page.getByRole('option',{name:/BANK-001/}).click();await dialog.getByLabel('Amount to assign',{exact:true}).fill('100.00')
  await dialog.getByRole('button',{name:'Assign gift',exact:true}).click();await expect(dialog).toBeHidden()
  expect(requests.find(r=>r.path.endsWith('/assignments'))?.body).toMatchObject({giftId:'gift-1',amountMinor:10000,expectedVersion:0})
  await expect(page.getByText('Remaining ZAR 100.00',{exact:true})).toBeVisible();await page.getByRole('button',{name:'Close pledge details',exact:true}).click();await expect(page.locator('.v-dialog.v-overlay')).toBeHidden()
  await expect(page.locator('.giving-pledge-card [role=progressbar]')).toHaveAttribute('aria-valuenow','50')
  await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);await page.screenshot({path:`test-results/giving-pledges-${width}.png`,fullPage:true});expect(errors).toEqual([])
 })
 test(`Statements and their print surface work at ${width}px`,async({page})=>{
  await page.setViewportSize({width,height:1000});const {errors}=await fixture(page);await page.goto('/giving/reports?donorId=person-1');await page.getByRole('button',{name:'Generate statement',exact:true}).click()
  await expect(page.locator('.giving-print').getByRole('heading',{name:'Solly Mabasa',exact:true})).toBeVisible();await expect(page.getByRole('button',{name:'Print statement',exact:true})).toBeEnabled()
  await page.emulateMedia({media:'print'});await expect(page.locator('.giving-print')).toBeVisible();await expect(page.getByRole('button',{name:'Generate statement',exact:true})).toBeHidden();await page.screenshot({path:`test-results/giving-statement-print-${width}.png`,fullPage:true});await page.emulateMedia({media:'screen'})
  await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);expect(errors).toEqual([])
 })
}
test('Assignment corrections keep their reason and release pledged credit',async({page})=>{
 const {requests,errors}=await fixture(page);await page.goto('/giving/pledges');await page.getByRole('button',{name:'Open pledge',exact:true}).click();await page.getByRole('button',{name:'Assign a gift',exact:true}).click()
 let dialog=page.getByRole('dialog').filter({has:page.getByRole('heading',{name:'Assign a gift',exact:true})});await dialog.getByLabel('Eligible gift',{exact:true}).press('ArrowDown');await page.getByRole('option',{name:/BANK-001/}).click();await dialog.getByRole('button',{name:'Assign gift',exact:true}).click();await expect(dialog).toBeHidden()
 await page.getByRole('button',{name:'Cancel assignment',exact:true}).click();dialog=page.getByRole('dialog').filter({has:page.getByRole('heading',{name:'Cancel assignment',exact:true})});await dialog.getByLabel('Cancellation reason',{exact:true}).fill('Wrong commitment selected');await dialog.getByRole('button',{name:'Save changes',exact:true}).click();await expect(dialog).toBeHidden();await expect(page.getByText('Wrong commitment selected',{exact:true})).toBeVisible();expect(requests.find(r=>r.path.includes('/pledge-assignments/'))?.body).toMatchObject({expectedVersion:0,reason:'Wrong commitment selected'});expect(errors).toEqual([])
})
test('Issue once, reopen and print the numbered acknowledgement',async({page})=>{
 const {requests,errors}=await fixture(page);await page.goto('/giving');await page.getByRole('button',{name:'View gift from Solly Mabasa',exact:true}).click();await page.getByRole('button',{name:'Issue acknowledgement',exact:true}).click();await page.getByRole('link',{name:'Open acknowledgement',exact:true}).click();await expect(page.getByRole('heading',{name:'G-00000001',exact:true})).toBeVisible();await expect(page.getByRole('button',{name:'Print acknowledgement',exact:true})).toBeEnabled();await page.reload();await expect(page.getByRole('heading',{name:'G-00000001',exact:true})).toBeVisible();expect(requests.filter(r=>r.method==='POST' && r.path.endsWith('/acknowledgements'))).toHaveLength(1);expect(errors).toEqual([])
})
test('Voided acknowledgement retains its number and visibly states the reversal',async({page})=>{
 const {errors}=await fixture(page);await page.goto('/giving/receipts/ack-void');await expect(page.getByRole('heading',{name:'G-00000002',exact:true})).toBeVisible();await expect(page.getByRole('heading',{name:'VOID · Gift fully reversed',exact:true})).toBeVisible();await expect(page.getByText(/Duplicate bank entry/)).toBeVisible();expect(errors).toEqual([])
})
test('Export downloads the complete filtered ledger as CSV',async({page})=>{
 const {requests,errors}=await fixture(page);await page.goto('/giving/reports');const download=page.waitForEvent('download');await page.getByRole('button',{name:'Download ledger CSV',exact:true}).click();expect((await download).suggestedFilename()).toMatch(/^giving-ledger-.*\.csv$/);expect(requests.some(r=>r.path.endsWith('/ledger.csv'))).toBe(true);expect(errors).toEqual([])
})
test('Reconciliation records discrepancies and versioned review evidence',async({page})=>{
 const {requests,errors}=await fixture(page);await page.goto('/giving/reconciliations');await page.getByRole('button',{name:'Create batch',exact:true}).click();let dialog=page.getByRole('dialog').filter({has:page.getByRole('heading',{name:'Create reconciliation batch',exact:true})});await dialog.getByLabel('Bank or cash reference',{exact:true}).fill('CASH-NEW');await dialog.getByRole('checkbox',{name:'Solly Mabasa · ZAR 100.00'}).check();await dialog.getByLabel('Expected amount from evidence',{exact:true}).fill('110.00');await expect(dialog.getByText(/discrepancy of ZAR 10.00/)).toBeVisible();await dialog.getByLabel('Batch evidence or discrepancy explanation',{exact:true}).fill('Cash count needs a second check');await dialog.getByRole('button',{name:'Record batch',exact:true}).click();await expect(dialog).toBeHidden();expect(requests.find(r=>r.method==='POST' && r.path.endsWith('/reconciliations'))?.body).toMatchObject({expectedMinor:11000,giftIds:['gift-1'],reason:'Cash count needs a second check'})
 await page.getByRole('button',{name:'Open batch',exact:true}).click();await page.getByRole('button',{name:'Record review',exact:true}).click();dialog=page.getByRole('dialog').filter({has:page.getByRole('heading',{name:'Record reconciliation review',exact:true})});await dialog.getByLabel('Review outcome',{exact:true}).press('ArrowDown');await page.getByRole('option',{name:'Explained with evidence',exact:true}).click();await dialog.getByLabel('Review evidence',{exact:true}).fill('Bank correction verified');await dialog.getByRole('button',{name:'Save review',exact:true}).click();await expect(dialog).toBeHidden();await expect(page.getByText('Bank correction verified',{exact:true})).toBeVisible();expect(requests.find(r=>r.path.endsWith('/reviews'))?.body).toMatchObject({expectedVersion:0,outcome:'EXPLAINED',note:'Bank correction verified'});expect(errors).toEqual([])
})
test('Read-only finance users can inspect reports without write or export controls',async({page})=>{
 const {requests,errors}=await fixture(page,true);await page.goto('/giving/pledges');await expect(page.getByRole('button',{name:'Create pledge',exact:true})).toHaveCount(0);await page.goto('/giving/reports?donorId=person-1');await page.getByRole('button',{name:'Generate statement',exact:true}).click();await expect(page.locator('.giving-print')).toBeVisible();await expect(page.getByRole('button',{name:'Download ledger CSV',exact:true})).toHaveCount(0);await page.goto('/giving/reconciliations');await expect(page.getByRole('button',{name:'Create batch',exact:true})).toHaveCount(0);await page.getByRole('button',{name:'Open batch',exact:true}).click();await expect(page.getByRole('button',{name:'Record review',exact:true})).toHaveCount(0);expect(requests.every(r=>r.method==='GET')).toBe(true);expect(errors).toEqual([])
})
