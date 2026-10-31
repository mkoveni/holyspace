import type { Page } from '@playwright/test'
import { expect,test } from '@playwright/test'
import { ministryFixture } from './fixture'
async function fixture(page:Page,configure=true,encryptionReady=true){
 const {errors}=await ministryFixture(page,configure?['payments.configure','payments.manage']:['payments.manage'])
 const empty=(mode:string)=>({mode,revisionId:null as string|null,publicUrl:'',backendUrl:'',ipRanges:'',merchantIdSet:false,merchantKeySet:false,passphraseSet:false,yocoKeySet:false,webhookSecretSet:false,complete:false,connection:null as any})
 const settings={encryptionReady,providers:['payfast','yoco'].map(provider=>({settings:{provider,mode:'test',enabled:false,monthly:false,managed:false,version:0},environments:[empty('test'),empty('live')]})),history:[] as any[]}
 const requests:{method:string,path:string,body:any}[]=[]
 let conflict=false
 await page.route('**/api/payments/settings**',async route=>{
  const request=route.request(),path=new URL(request.url()).pathname,method=request.method(),body=request.postDataJSON();requests.push({method,path,body})
  if(method==='GET')return route.fulfill({json:settings})
  const provider=settings.providers.find(p=>path.includes('/'+p.settings.provider))!
  const environment=provider.environments.find(e=>e.mode===body.mode)!
  if(method==='PUT'){
   if(conflict)return route.fulfill({status:409,json:{message:'Payment settings changed. Reload before saving or checking the connection.'}})
   const hasSecrets=Object.values(body.credentials).some(v=>Boolean(v))
   if(hasSecrets&&body.enabled)return route.fulfill({status:409,json:{message:'Save changed connection details with new checkouts disabled, test the saved connection, then enable the provider.'}})
   if(hasSecrets||!environment.revisionId){environment.revisionId='revision-'+(provider.settings.version+1);environment.connection=null}
   environment.publicUrl=body.publicUrl;environment.backendUrl=body.backendUrl;environment.ipRanges=body.ipRanges
   environment.merchantIdSet ||= Boolean(body.credentials.merchantId);environment.merchantKeySet ||= Boolean(body.credentials.merchantKey);environment.passphraseSet ||= Boolean(body.credentials.passphrase);environment.yocoKeySet ||= Boolean(body.credentials.yocoKey);environment.webhookSecretSet ||= Boolean(body.credentials.webhookSecret);environment.complete=true
   Object.assign(provider.settings,{mode:body.mode,enabled:body.enabled,monthly:body.monthly,managed:true,version:provider.settings.version+1});settings.history.unshift({provider:provider.settings.provider,mode:body.mode,version:provider.settings.version,action:'SETTINGS_SAVED',note:body.reason,changedFields:'connection details',actorId:'account-1',actorName:'Naledi Mokoena',occurredAt:new Date().toISOString()})
   return route.fulfill({status:204})
  }
  if(method==='POST'&&path.endsWith('/connection-check')){environment.connection={id:'check-1',revisionId:environment.revisionId,successful:true,message:'API authentication confirmed. A callback pilot is still required.',checkedAt:new Date().toISOString(),checkedBy:'account-1'};return route.fulfill({json:environment.connection})}
  errors.push(`Unexpected configuration API ${method} ${path}`);return route.fulfill({status:500})
 })
 return {settings,requests,errors,setConflict:(value:boolean)=>{conflict=value}}
}
async function fillPayfast(page:Page){
 await page.getByLabel('Frontend website URL',{exact:true}).fill('https://church.example')
 await page.getByLabel('Backend API URL',{exact:true}).fill('https://api.church.example')
 await page.getByLabel('Merchant ID',{exact:true}).fill('10000100')
 await page.getByLabel('Merchant key',{exact:true}).fill('fixture-merchant-key')
 await page.getByLabel('Passphrase',{exact:true}).fill('fixture-private-passphrase')
 await page.getByLabel('Reason for this change',{exact:true}).fill('Initial test configuration')
}
for(const width of [390,1440]){
 test(`Save protected credentials, test and activate at ${width}px`,async({page})=>{
  await page.setViewportSize({width,height:1000});const {requests,errors}=await fixture(page);await page.goto('/administration/payment-settings')
  await expect(page.getByRole('heading',{name:'Payment provider settings.',exact:true})).toBeVisible();await expect(page.getByRole('button',{name:'Test saved connection',exact:true})).toBeDisabled()
  await fillPayfast(page);await expect(page.getByLabel('Passphrase',{exact:true})).toHaveAttribute('type','password');await page.getByRole('button',{name:'Save provider settings',exact:true}).click()
  await expect(page.getByText('Provider settings saved. Earlier payments retain their original connection details.',{exact:true})).toBeVisible();await expect(page.getByLabel('Passphrase',{exact:true})).toHaveValue('')
  expect(requests.find(r=>r.method==='PUT')?.body).toMatchObject({mode:'test',enabled:false,publicUrl:'https://church.example',backendUrl:'https://api.church.example',credentials:{merchantId:'10000100',merchantKey:'fixture-merchant-key',passphrase:'fixture-private-passphrase'}})
  await page.getByRole('button',{name:'Test saved connection',exact:true}).click();await expect(page.getByText('Connection check passed',{exact:true})).toBeVisible()
  await page.getByLabel('Enable new checkouts',{exact:true}).check();await page.getByLabel('Reason for this change',{exact:true}).fill('Pilot checked; enable test checkout');await page.getByRole('button',{name:'Save provider settings',exact:true}).click()
  await expect(page.getByText('Enabled · Test',{exact:true})).toBeVisible();expect(requests.filter(r=>r.method==='PUT').at(-1)?.body).toMatchObject({expectedVersion:1,enabled:true,credentials:{merchantId:'',merchantKey:'',passphrase:''}})
  expect(await page.evaluate(()=>JSON.stringify(localStorage))).not.toContain('fixture-private-passphrase')
  await expect(page.getByText('Explain this change.',{exact:true})).toHaveCount(0);const bounds=await page.getByRole('button',{name:'Save provider settings',exact:true}).boundingBox();expect(bounds!.x+bounds!.width).toBeLessThanOrEqual(width);await page.evaluate(()=>window.scrollTo(0,0));await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);await page.screenshot({path:`test-results/payment-settings-${width}.png`,fullPage:true});expect(errors).toEqual([])
 })
}
test('Changing provider or environment clears replacement secrets',async({page})=>{
 const {errors}=await fixture(page);await page.goto('/administration/payment-settings');await fillPayfast(page);await page.getByRole('button',{name:'Live environment',exact:true}).click();await expect(page.getByLabel('Passphrase',{exact:true})).toHaveValue('');await expect(page.getByLabel('Frontend website URL',{exact:true})).toHaveValue('')
 await page.getByRole('button',{name:/Yoco Not configured in settings/}).click();await expect(page.getByLabel('Yoco secret API key',{exact:true})).toBeVisible();await expect(page.getByLabel('Webhook signing secret',{exact:true})).toHaveValue('');await expect(page.getByLabel('Offer monthly giving',{exact:true})).toHaveCount(0);expect(errors).toEqual([])
})
test('A stale save requires an explicit reload and never echoes credentials',async({page})=>{
 const {errors,setConflict}=await fixture(page);await page.goto('/administration/payment-settings');await fillPayfast(page);setConflict(true);await page.getByRole('button',{name:'Save provider settings',exact:true}).click();await expect(page.getByRole('button',{name:'Reload settings',exact:true})).toBeVisible();await expect(page.getByRole('button',{name:'Save provider settings',exact:true})).toBeDisabled();setConflict(false);await page.getByRole('button',{name:'Reload settings',exact:true}).click();await expect(page.getByLabel('Merchant key',{exact:true})).toHaveValue('');expect(errors).toEqual([])
})
test('Missing server encryption blocks credential storage',async({page})=>{
 await fixture(page,true,false);await page.goto('/administration/payment-settings');await expect(page.getByText(/The server encryption key needs to be configured/)).toBeVisible();await expect(page.getByRole('button',{name:'Save provider settings',exact:true})).toBeDisabled()
})
test('Payment managers do not gain settings access implicitly',async({page})=>{
 const {requests}=await fixture(page,false);await page.goto('/administration/payment-settings');await expect(page.getByRole('heading',{name:'Payment provider settings.',exact:true})).toHaveCount(0);expect(requests).toHaveLength(0)
})
test('Connection-detail changes can be saved disabled without a misleading stale-edit lock',async({page})=>{
 const {settings,errors}=await fixture(page);const provider=settings.providers[0]!;Object.assign(provider.settings,{enabled:true,managed:true,version:1});Object.assign(provider.environments[0]!,{revisionId:'original',publicUrl:'https://church.example',backendUrl:'https://api.church.example',ipRanges:'197.97.145.144/28,41.74.179.192/27',merchantIdSet:true,merchantKeySet:true,passphraseSet:true,complete:true,connection:{successful:true,checkedAt:new Date().toISOString(),message:'Connection confirmed.'}})
 await page.goto('/administration/payment-settings');await page.getByLabel('Passphrase',{exact:true}).fill('fixture-replacement-secret');await page.getByLabel('Reason for this change',{exact:true}).fill('Rotate saved test credentials');await page.getByRole('button',{name:'Save provider settings',exact:true}).click()
 await expect(page.getByText(/Save changed connection details with new checkouts disabled/)).toBeVisible();await expect(page.getByRole('button',{name:'Reload settings',exact:true})).toHaveCount(0);await expect(page.getByRole('button',{name:'Save provider settings',exact:true})).toBeEnabled()
 await page.getByLabel('Enable new checkouts',{exact:true}).uncheck();await page.getByRole('button',{name:'Save provider settings',exact:true}).click();await expect(page.getByText('Provider settings saved. Earlier payments retain their original connection details.',{exact:true})).toBeVisible();expect(errors).toEqual([])
})
test('Yoco explains the separate signing secret and shows a safe failed-check message', async ({ page }) => {
  const { settings, errors } = await fixture(page)
  const provider = settings.providers.find(p => p.settings.provider === 'yoco')!
  Object.assign(provider.settings, { managed: true, version: 1 })
  Object.assign(provider.environments[0]!, {
    revisionId: 'yoco-revision', publicUrl: 'https://church.example', backendUrl: 'https://api.church.example',
    yocoKeySet: true, webhookSecretSet: true, complete: true,
  })
  const message = 'The Yoco webhook signing secret is malformed. Copy the complete whsec_... value returned when registering the callback URL, including any trailing = characters.'
  await page.route('**/api/payments/settings/yoco/connection-check', route => route.fulfill({
    json: { successful: false, message },
  }))
  await page.setViewportSize({ width: 390, height: 1000 })
  await page.goto('/administration/payment-settings')
  await page.getByRole('button', { name: /Yoco New checkouts disabled/ }).click()
  await expect(page.getByText(/Use the secret sk_test_/)).toBeVisible()
  await expect(page.getByText(/This is separate from your API key/)).toBeVisible()
  await page.getByRole('button', { name: 'Test saved connection', exact: true }).click()
  await expect(page.getByText(message, { exact: true })).toBeVisible()
  await expect(page.getByLabel('Webhook signing secret', { exact: true })).toHaveValue('')
  await expect(page.getByText('https://api.church.example/api/payments/webhooks/yoco', { exact: true })).toBeVisible()
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
  expect(errors).toEqual([])
})

test('Payment settings belongs to Administration and old bookmarks redirect there', async ({ page }) => {
  await fixture(page)
  await page.setViewportSize({ width: 1440, height: 1000 })
  await page.goto('/payments/settings')
  await expect(page).toHaveURL(/\/administration\/payment-settings$/)
  await expect(page.getByRole('heading', { name: 'Payment provider settings.', exact: true })).toBeVisible()
  const administration = page.locator('.app-drawer__group').filter({ hasText: 'Administration' })
  await expect(administration.getByRole('link', { name: 'Payment settings', exact: true })).toBeVisible()
  const giving = page.locator('.app-drawer__group').filter({ hasText: 'Giving' })
  await expect(giving.getByRole('link', { name: 'Payment settings', exact: true })).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Online payments', exact: true })).toHaveCount(0)
})

test('Yoco subscriptions can be registered and obsolete ones removed without exposing secrets', async ({ page }) => {
  const { settings, errors } = await fixture(page)
  const provider = settings.providers.find(p => p.settings.provider === 'yoco')!
  Object.assign(provider.settings, { managed: true, version: 1 })
  Object.assign(provider.environments[0]!, { revisionId: 'saved-yoco', publicUrl: 'https://church.example', backendUrl: 'https://api.church.example', yocoKeySet: true })
  const subscriptions = [{ id: 'sub_old', name: 'Old tunnel', url: 'https://old.example/api/payments/webhooks/yoco', mode: 'test' }]
  const changes: any[] = []
  await page.route('**/api/payments/settings/yoco/webhooks**', async route => {
    const request = route.request()
    if (request.method() === 'GET') return route.fulfill({ json: { subscriptions, pending: [] } })
    const body = request.postDataJSON(); changes.push(body); provider.settings.version++
    if (new URL(request.url()).pathname.endsWith('/removal')) subscriptions.splice(0, 1)
    else { subscriptions.push({ id: 'sub_new', name: 'OneChurch test', url: 'https://api.church.example/api/payments/webhooks/yoco', mode: 'test' }); provider.environments[0]!.webhookSecretSet = true }
    return route.fulfill({ json: { requestId: body.requestId, status: 'APPLIED', message: 'Webhook change saved securely.' } })
  })
  await page.setViewportSize({ width: 390, height: 1000 })
  await page.goto('/administration/payment-settings')
  await page.getByRole('button', { name: /Yoco New checkouts disabled/ }).click()
  await page.getByRole('button', { name: 'Load webhooks', exact: true }).click()
  await expect(page.getByText('Old tunnel', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Register saved callback', exact: true }).click()
  let dialog = page.getByRole('dialog').filter({ hasText: 'Register Yoco callback' }).last()
  await dialog.getByLabel('Webhook change reason', { exact: true }).fill('Initial test callback registration')
  await dialog.getByRole('button', { name: 'Register and save securely', exact: true }).click()
  await expect(dialog).toBeHidden()
  await expect(page.getByText('OneChurch test', { exact: true })).toBeVisible()
  expect(changes[0]).toMatchObject({ expectedVersion: 1, mode: 'test' })
  expect(changes[0]).not.toHaveProperty('credentials')
  await page.getByText('Old tunnel', { exact: true }).locator('..').locator('..').getByRole('button', { name: 'Remove', exact: true }).click()
  dialog = page.getByRole('dialog').filter({ hasText: 'Remove Yoco subscription' }).last()
  await dialog.getByLabel('Webhook change reason', { exact: true }).fill('Obsolete test tunnel reviewed')
  await expect(dialog.getByRole('button', { name: 'Remove subscription', exact: true })).toBeDisabled()
  await dialog.getByLabel('I reviewed this subscription and its use by other integrations', { exact: true }).check()
  await dialog.getByRole('button', { name: 'Remove subscription', exact: true }).click()
  await expect(dialog).toBeHidden()
  await expect(page.getByText('Old tunnel', { exact: true })).toHaveCount(0)
  expect(changes[1]).toMatchObject({ expectedVersion: 2 })
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
  await page.evaluate(() => window.scrollTo(0, 0))
  await page.screenshot({ path: 'test-results/payment-webhooks-390.png', fullPage: true })
  expect(await page.evaluate(() => JSON.stringify(localStorage))).not.toContain('whsec_')
  expect(errors).toEqual([])
})
