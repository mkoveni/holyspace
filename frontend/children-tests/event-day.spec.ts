import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import QRCode from 'qrcode'
import { ministryFixture } from './fixture'
async function fixture(page:Page,manage=true) {
 const permissions=['events.read','events.registrations.read','events.attendance.read','people.read','events.communications.send',...(manage?['events.attendance.manage','events.registrations.manage']:[])]
 const base=await ministryFixture(page,permissions),requests:{path:string;method:string;body:any}[]=[]
 const start=new Date(Date.now()+900000).toISOString(),end=new Date(Date.now()+4500000).toISOString()
 const occurrence={id:'occurrence-1',eventId:'event-1',seriesId:'series-1',originDate:start.slice(0,10),timeZone:'Africa/Johannesburg',startsAt:start,endsAt:end,venueId:'venue-1',venueName:'Main hall',venueAddress:'10 Church Road',status:'SCHEDULED',overridden:false,version:0,scheduleRevision:0}
 const registration={id:'registration-1',personId:'person-1',status:'CONFIRMED',position:1,version:0}
 const attendees:any[]=[{registration,attendance:null,name:'Solly Mabasa',status:'EXPECTED'},{registration:{id:'registration-2',personId:'person-2',status:'CANCELLED',position:2,version:1},attendance:null,name:'Naledi Mokoena',status:'CANCELLED'}]
 const counts=()=>({confirmed:1,waitlisted:attendees.filter(a=>a.status==='WAITLISTED').length,cancelled:attendees.filter(a=>a.status==='CANCELLED').length,attended:attendees.filter(a=>a.status==='PRESENT').length,absent:0})
 await page.route('**/api/events**',async route=>{
  const request=route.request(),path=new URL(request.url()).pathname,method=request.method(),body=request.postDataJSON();requests.push({path,method,body})
  if(path.endsWith('/scan-member')) {attendees[0].status='PRESENT';attendees[0].attendance={id:'attendance-1',status:'PRESENT',version:0};return route.fulfill({status:201,json:{id:'attendance-1'}})}
  if(path.endsWith('/attendance-report.csv'))return route.fulfill({contentType:'text/csv',body:'Event,Attended,Absent\r\nSunday gathering,1,0\r\n'})
  if(path.endsWith('/attendance-report'))return route.fulfill({json:{occurrences:[{occurrenceId:occurrence.id,eventId:'event-1',title:'Sunday gathering',startsAt:start,endsAt:end,timeZone:occurrence.timeZone,venue:'Main hall',status:'SCHEDULED',counts:{...counts(),attended:1,absent:0}}],totals:{occurrences:1,...counts(),attended:1,absent:0}}})
  if(path.endsWith('/attendance'))return route.fulfill({json:{attendees,total:attendees.length,counts:counts(),admissionOpen:true}})
  if(path.endsWith('/check-in')){attendees[0].attendance={id:'attendance-1',registrationId:registration.id,arrivedAt:new Date().toISOString(),status:'PRESENT',version:0};attendees[0].status='PRESENT';return route.fulfill({status:201,json:{id:'attendance-1'}})}
  if(path.endsWith('/attendance-correction')){attendees[0].attendance.status=body.status;attendees[0].attendance.version++;attendees[0].status=body.status==='PRESENT'?'PRESENT':'EXPECTED';return route.fulfill({status:204})}
  if(path.endsWith('/attendance-history'))return route.fulfill({json:[{id:'history-1',actorId:'account-1',action:'CHECKED_IN',reason:'Arrival recorded.',occurredAt:new Date().toISOString()}]})
  if(path.endsWith('/walk-ins')){attendees[1].registration.status='WAITLISTED';attendees[1].status='WAITLISTED';return route.fulfill({status:201,json:{registrationId:'registration-2',admitted:false,status:'WAITLISTED'}})}
  if(path.endsWith('/message-contacts'))return route.fulfill({json:[{id:'contact-1',value:'solly@example.org'}]})
  if(path.endsWith('/notification-review'))return route.fulfill({json:{hash:'notice-hash',recipient:'solly@example.org',subject:'Your gathering is coming up',html:'<h2>Hello Solly</h2><p>Welcome to Main hall.</p>',purpose:body.purpose,templateVersion:0}})
  if(path.endsWith('/notifications'))return route.fulfill({status:201,json:{id:'delivery-1'}})
  if(path.endsWith('/deliveries'))return route.fulfill({json:{deliveries:[{id:'delivery-1',status:'BLOCKED',scheduledAt:start,attempts:0,errorCode:'ELIGIBILITY_CHANGED',captured:false,version:0}],total:1,failed:0,waiting:0}})
  if(path.endsWith('/registrations'))return route.fulfill({json:{registrations:[{registration,name:'Solly Mabasa'}],total:1,counts:counts()}})
  if(path.endsWith('/occurrences/occurrence-1'))return route.fulfill({json:{occurrence,title:'Sunday gathering',type:'SERVICE',series:{id:'series-1',timeZone:occurrence.timeZone,frequency:'ONCE',interval:1,count:1,firstDate:occurrence.originDate,startsAt:'09:00',endsAt:'10:30',endDayOffset:0,venueId:'venue-1',version:0},registrationPolicy:{occurrenceId:occurrence.id,open:true,capacity:1,waitlistEnabled:true,version:0},registrations:counts()}})
  return route.fulfill({status:500,json:{message:'Unexpected Event Day request: '+path}})
 })
 return {...base,requests,attendees}
}
for(const width of [390,1440])test(`event attendance records arrivals and reasoned corrections at ${width}px`,async({page})=>{
 await page.setViewportSize({width,height:1000});const {requests,errors,attendees}=await fixture(page);await page.goto('/events/occurrences/occurrence-1');const panel=page.locator('section').filter({has:page.getByRole('heading',{name:'Attendance desk',exact:true})});await panel.getByRole('button',{name:'Check in',exact:true}).click();const row=panel.getByRole('row').filter({hasText:'Solly Mabasa'});await expect(row.getByText('PRESENT',{exact:true})).toBeVisible();await expect(row.getByText(new Date(attendees[0].attendance.arrivedAt).toLocaleString('en-US',{dateStyle:'medium',timeStyle:'short',timeZone:'Africa/Johannesburg'}),{exact:true})).toBeVisible();await page.screenshot({path:'/app/test-results/event-attendance-'+width+'.png',fullPage:true});expect(requests.find(r=>r.path.endsWith('/check-in'))?.body).toMatchObject({expectedVersion:0});await row.getByRole('button',{name:'Correct attendance',exact:true}).click();const dialog=page.getByRole('dialog');await dialog.getByLabel('Reason for correction',{exact:true}).fill('Wrong person selected');await dialog.getByRole('button',{name:'Save changes',exact:true}).click();await expect(dialog).toBeHidden();await expect(row.getByText('Arrival voided')).toBeVisible();expect(requests.find(r=>r.path.endsWith('/attendance-correction'))?.body).toMatchObject({expectedVersion:0,status:'VOIDED',reason:'Wrong person selected'});await row.getByRole('button',{name:'Attendance history',exact:true}).click();await expect(page.getByRole('dialog').getByText('Arrival recorded.')).toBeVisible();expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);expect(errors).toEqual([])
})
test('a waitlisted walk-in is clearly distinguished from an admitted attendee',async({page})=>{
 const {requests,errors}=await fixture(page);await page.goto('/events/occurrences/occurrence-1');await page.getByRole('button',{name:'Register again at door',exact:true}).click();const dialog=page.getByRole('dialog');await dialog.getByRole('button',{name:'Register walk-in',exact:true}).click();await expect(dialog).toBeHidden();await expect(page.getByText('Added to the waitlist. This person has not been checked in.',{exact:true})).toBeVisible();expect(requests.find(r=>r.path.endsWith('/walk-ins'))?.body).toMatchObject({personId:'person-2',expectedVersion:1});expect(requests.filter(r=>r.path.endsWith('/check-in'))).toHaveLength(0);expect(errors).toEqual([])
})
test('attendance readers can inspect the desk without admission controls',async({page})=>{
 const {errors}=await fixture(page,false);await page.goto('/events/occurrences/occurrence-1');await expect(page.getByRole('heading',{name:'Attendance desk'})).toBeVisible();await expect(page.getByRole('button',{name:'Check in',exact:true})).toHaveCount(0);await expect(page.getByRole('button',{name:'Register walk-in',exact:true})).toHaveCount(0);expect(errors).toEqual([])
})
test('event notices require reviewed content and recipient verification',async({page})=>{
 const {requests,errors}=await fixture(page);await page.goto('/events/occurrences/occurrence-1');await page.getByRole('button',{name:'Prepare email notice',exact:true}).click();const dialog=page.getByRole('dialog');await dialog.getByLabel('Current email address',{exact:true}).press('ArrowDown');await page.getByRole('option',{name:'solly@example.org',exact:true}).click();await dialog.getByLabel('Personal message',{exact:true}).fill('See you soon');await dialog.getByRole('button',{name:'Review notice',exact:true}).click();const approval=dialog.getByRole('button',{name:'Approve and queue notice',exact:true});await expect(approval).toBeDisabled();await expect(dialog.locator('iframe')).toHaveAttribute('sandbox','');await dialog.getByLabel('I verified this recipient\'s identity and permission to receive this message',{exact:true}).check();await dialog.getByLabel('Verification evidence',{exact:true}).fill('Recipient confirmed own email by phone');await approval.click();await expect(dialog).toBeHidden();await expect(page.getByText('Reviewed notice queued.')).toBeVisible();expect(requests.find(r=>r.path.endsWith('/notifications'))?.body).toMatchObject({purpose:'CONFIRMATION',contactId:'contact-1',reviewHash:'notice-hash',verificationEvidence:'Recipient confirmed own email by phone'});expect(errors).toEqual([])
})
test('changing a notice draft requires a new content review',async({page})=>{
 const {requests,errors}=await fixture(page);await page.goto('/events/occurrences/occurrence-1');await page.getByRole('button',{name:'Prepare email notice',exact:true}).click();const dialog=page.getByRole('dialog');await dialog.getByLabel('Current email address',{exact:true}).press('ArrowDown');await page.getByRole('option',{name:'solly@example.org',exact:true}).click();await dialog.getByLabel('Personal message',{exact:true}).fill('Initial wording');await dialog.getByRole('button',{name:'Review notice',exact:true}).click();await expect(dialog.getByRole('button',{name:'Approve and queue notice'})).toBeVisible();await dialog.getByLabel('Personal message',{exact:true}).fill('Updated wording');await expect(dialog.getByRole('button',{name:'Review notice',exact:true})).toBeVisible();await expect(dialog.locator('iframe')).toHaveCount(0);expect(requests.filter(r=>r.path.endsWith('/notifications'))).toHaveLength(0);expect(errors).toEqual([])
})
for(const width of [390,1440])test(`attendance reports and export fit at ${width}px`,async({page})=>{
 await page.setViewportSize({width,height:1000});const {requests,errors}=await fixture(page);await page.goto('/events/reports');await expect(page.getByRole('heading',{name:'Understand your gathering rhythm.'})).toBeVisible();await expect(page.getByRole('link',{name:'Sunday gathering',exact:true})).toBeVisible();await page.screenshot({path:'/app/test-results/event-report-'+width+'.png',fullPage:true});const downloadPromise=page.waitForEvent('download');await page.getByRole('button',{name:'Export CSV',exact:true}).click();const download=await downloadPromise;expect(download.suggestedFilename()).toBe('event-attendance.csv');expect(requests.some(r=>r.path.endsWith('/attendance-report.csv'))).toBe(true);expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);expect(errors).toEqual([])
})
test('event delivery tracking explains blocked eligibility without exposing message contents',async({page})=>{
 const {errors}=await fixture(page);await page.goto('/events/email-deliveries');await expect(page.getByRole('heading',{name:'Every notice, accounted for.'})).toBeVisible();await expect(page.getByText('ELIGIBILITY_CHANGED')).toBeVisible();await expect(page.getByText('solly@example.org')).toHaveCount(0);expect(errors).toEqual([])
})

test('staff can decode a QR photo and explicitly record an event arrival',async({page})=>{
 const {requests,errors}=await fixture(page)
 await page.goto('/events/occurrences/occurrence-1')
 await page.getByRole('button',{name:'Scan member QR',exact:true}).click()
 const dialog=page.getByRole('dialog'),code='divinecommunityspace:event:v1:abcdefghijklmnopqrstuvwxyz0123456789'
 await dialog.getByLabel('Upload event QR photo',{exact:true}).setInputFiles({name:'event-attendance.png',mimeType:'image/png',buffer:await QRCode.toBuffer(code,{width:400,margin:4})})
 await expect(dialog.getByLabel('Event attendance code',{exact:true})).toHaveValue(code)
 expect(requests.filter(r=>r.path.endsWith('/scan-member'))).toHaveLength(0)
 await dialog.getByRole('button',{name:'Record arrival',exact:true}).click()
 await expect(dialog.getByText('Arrival recorded. The attendance roster has been refreshed.')).toBeVisible()
 expect(requests.find(r=>r.path.endsWith('/scan-member'))?.body).toMatchObject({credential:code})
 await dialog.getByRole('button',{name:'Close',exact:true}).click()
 await expect(dialog).toBeHidden()
 expect(errors).toEqual([])
})
