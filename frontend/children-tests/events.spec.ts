import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'
import { readinessFixture } from './event-readiness-fixture'
async function fixture(page:Page,manage=true) {
 const base=await ministryFixture(page,['events.read','events.registrations.read','people.read',...(manage?['events.manage','events.registrations.manage']:[])])
 const venue={id:'venue-1',name:'Main hall',address:'10 Church Road',exclusive:true,active:true,version:0}
 const event={id:'event-1',title:'Sunday gathering',type:'SERVICE',description:'Make room for everyone.',active:true,version:0}
 const start=new Date(Date.now()+86400000).toISOString(),end=new Date(Date.now()+91800000).toISOString()
 const occurrence={id:'occurrence-1',eventId:event.id,seriesId:'series-1',originDate:start.slice(0,10),timeZone:'Africa/Johannesburg',startsAt:start,endsAt:end,venueId:venue.id,venueName:venue.name,venueAddress:venue.address,status:'SCHEDULED',overridden:false,version:0,scheduleRevision:0}
 const series={id:'series-1',timeZone:'Africa/Johannesburg',frequency:'WEEKLY',interval:1,count:4,firstDate:start.slice(0,10),startsAt:'09:00',endsAt:'10:30',endDayOffset:0,venueId:venue.id,version:0}
 const counts={confirmed:1,waitlisted:1,cancelled:0},policy={occurrenceId:occurrence.id,open:true,capacity:1,waitlistEnabled:true,version:0}
 const memberOffer={enabled:false,version:0}
 const requests:{method:string;path:string;body:any}[]=[]
 await page.route('**/api/events**',async route=>{
  const request=route.request(),path=new URL(request.url()).pathname,body=request.postDataJSON(),method=request.method();requests.push({method,path,body})
  if(path.endsWith('/member-offer')) {if(method==='PUT'){memberOffer.enabled=body.enabled;memberOffer.version++;return route.fulfill({status:204})}return route.fulfill({json:memberOffer})}
  if(path.endsWith('/readiness'))return route.fulfill({json:readinessFixture(occurrence)})
  if(path.endsWith('/schedule/preview'))return route.fulfill({json:{hash:'reviewed-schedule',venue,dates:[{originDate:body.draft.firstDate,startsAt:start,endsAt:end}]}})
  if(path.endsWith('/occurrences/preview'))return route.fulfill({json:{hash:'reviewed-change',changes:[{before:occurrence,after:{...occurrence,status:body.draft.action==='CANCEL'?'CANCELLED':'SCHEDULED',version:1},registrations:counts}],skipped:[],confirmedAffected:1,waitlistedAffected:1}})
  if(path.endsWith('/occurrences/cancel')||path.endsWith('/occurrences/restore')||path.endsWith('/occurrences/reschedule')){occurrence.status=body.draft.action==='CANCEL'?'CANCELLED':'SCHEDULED';return route.fulfill({status:204})}
  if(path.endsWith('/registration'))return route.fulfill({status:204})
  if(path.endsWith('/history'))return route.fulfill({json:[{id:'history-1',action:'SCHEDULED',reason:'Occurrence scheduled.',occurredAt:start,startsAt:start,endsAt:end,venueName:venue.name}]})
  if(path.endsWith('/registrations'))return route.fulfill({json:{registrations:[{registration:{id:'registration-1',personId:'person-1',status:'CONFIRMED',position:1,version:0},name:'Solly Mabasa'}],total:1,counts}})
  if(path.endsWith('/venues'))return route.fulfill(method==='POST'?{status:201,json:{id:'new-venue'}}:{json:{venues:[venue],total:1}})
  if(path.endsWith('/calendar'))return route.fulfill({json:{occurrences:[{occurrence,title:event.title,type:event.type,registrations:counts}],total:1}})
  if(path.endsWith('/occurrences/occurrence-1'))return route.fulfill({json:{occurrence,title:event.title,type:event.type,series,registrationPolicy:policy,registrations:counts}})
  if(path.endsWith('/event-1'))return route.fulfill(method==='PUT'?{status:204}:{json:{event,series:[series],totalSeries:1}})
  if(path==='/api/events')return route.fulfill(method==='POST'?{status:201,json:{id:event.id}}:{json:{events:[event],total:1}})
  return route.fulfill({status:500,json:{message:'Unexpected Events request'}})
 })
 return {...base,requests,venue,event,occurrence}
}
for(const width of [390,1440])test(`events calendar fits at ${width}px`,async({page})=>{
 await page.setViewportSize({width,height:1000});const {errors}=await fixture(page);await page.goto('/events');await expect(page.getByRole('heading',{name:'Make room for meaningful moments.'})).toBeVisible();await expect(page.getByRole('heading',{name:'Sunday gathering',exact:true})).toBeVisible();if(width===1440)await page.screenshot({path:'/app/test-results/events-desktop.png',fullPage:true});await page.getByRole('link',{name:'Open occurrence',exact:true}).click();await expect(page.getByRole('heading',{name:'Bookings'})).toBeVisible();await expect(page.getByText('Solly Mabasa')).toBeVisible();expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);expect(errors).toEqual([])
})
test('event creation reviews dates and invalidates the review after an edit',async({page})=>{
 const {requests,errors}=await fixture(page);await page.goto('/events?action=create');const dialog=page.getByRole('dialog');await expect(dialog.getByRole('heading',{name:'Plan an event'})).toBeVisible();await dialog.getByLabel('Event title',{exact:true}).fill('Community lunch');const venueResults=page.waitForResponse(r=>new URL(r.url()).pathname.endsWith('/venues')&&new URL(r.url()).searchParams.get('search')==='Main hall');await dialog.getByLabel('Venue',{exact:true}).fill('Main hall');await venueResults;await page.getByRole('option',{name:'Main hall',exact:true}).click();await dialog.getByRole('button',{name:'Preview dates',exact:true}).click();await expect(dialog.getByRole('button',{name:'Create reviewed dates'})).toBeVisible();expect(requests.filter(r=>r.method==='POST'&&r.path==='/api/events')).toHaveLength(0);await dialog.getByLabel('Starts at',{exact:true}).fill('11:00');await expect(dialog.getByRole('button',{name:'Preview dates',exact:true})).toBeVisible();await dialog.getByRole('button',{name:'Preview dates',exact:true}).click();await dialog.getByRole('button',{name:'Create reviewed dates'}).click();await expect(dialog).toBeHidden();expect(requests.find(r=>r.method==='POST'&&r.path==='/api/events')?.body).toMatchObject({title:'Community lunch',previewHash:'reviewed-schedule',schedule:{startsAt:'11:00',venueId:'venue-1'}});expect(errors).toEqual([])
})
test('cancellation reviews affected bookings and retains accessible history',async({page})=>{
 const {requests,errors}=await fixture(page);await page.goto('/events/occurrences/occurrence-1');await page.getByRole('button',{name:'Cancel occurrence',exact:true}).click();const dialog=page.getByRole('dialog');await dialog.getByLabel('Reason for change',{exact:true}).fill('Building maintenance');await dialog.getByRole('button',{name:'Preview change',exact:true}).click();await expect(dialog.getByText('1 dates · 1 confirmed · 1 waitlisted')).toBeVisible();await dialog.getByRole('button',{name:'Apply reviewed change',exact:true}).click();await expect(dialog).toBeHidden();expect(requests.find(r=>r.path.endsWith('/occurrences/cancel'))?.body).toMatchObject({reviewHash:'reviewed-change',reason:'Building maintenance',draft:{scope:'THIS',expectedVersion:0}});await expect(page.getByRole('button',{name:'Restore occurrence',exact:true})).toBeVisible();await page.getByRole('button',{name:'View occurrence history'}).click();await expect(page.getByRole('dialog').getByText('Occurrence scheduled.')).toBeVisible();expect(errors).toEqual([])
})
test('read-only coordinators see dates without mutation controls',async({page})=>{
 const {errors}=await fixture(page,false);await page.goto('/events');await expect(page.getByRole('heading',{name:'Sunday gathering',exact:true})).toBeVisible();await expect(page.getByRole('button',{name:'Plan event',exact:true})).toHaveCount(0);await page.goto('/events/occurrences/occurrence-1');await expect(page.getByText('Solly Mabasa')).toBeVisible();await expect(page.getByRole('button',{name:'Cancel occurrence',exact:true})).toHaveCount(0);await expect(page.getByRole('button',{name:'Register person',exact:true})).toHaveCount(0);expect(errors).toEqual([])
})
test('venue catalogue creates reusable venues without leaving the workspace',async({page})=>{
 const {requests,errors}=await fixture(page);await page.goto('/events/venues');await page.getByRole('button',{name:'Add venue',exact:true}).click();const dialog=page.getByRole('dialog');await dialog.getByLabel('Venue name',{exact:true}).fill('Garden');await dialog.getByRole('button',{name:'Save changes',exact:true}).click();await expect(dialog).toBeHidden();expect(requests.find(r=>r.method==='POST'&&r.path.endsWith('/venues'))?.body).toMatchObject({name:'Garden',exclusive:false});expect(errors).toEqual([])
})

test('event publication is explicit and versioned for the member app',async({page})=>{
 const {requests,errors}=await fixture(page)
 await page.goto('/events/event-1')
 await page.getByRole('button',{name:'Publish to member app',exact:true}).click()
 await expect(page.getByText('Published to members',{exact:true})).toBeVisible()
 expect(requests.find(r=>r.method==='PUT'&&r.path.endsWith('/member-offer'))?.body).toEqual({enabled:true,expectedVersion:0})
 await page.getByRole('button',{name:'Withdraw from member app',exact:true}).click()
 await expect(page.getByText('Not published',{exact:true})).toBeVisible()
 expect(errors).toEqual([])
})
