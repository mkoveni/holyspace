import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'
async function fixture(page: Page, manage = true, confirmed = false) {
  const permissions = [
    'volunteering.read',
    'volunteering.serve',
    ...(manage
      ? [
          'volunteering.manage',
          'volunteering.communications.send',
          'people.read',
          'volunteering.credentials.manage',
        ]
      : []),
  ]
  const base = await ministryFixture(page, permissions)
  const team = { id: 'team-1', name: 'Welcome team', active: true, version: 1 }
  const role = {
    id: 'role-1',
    teamId: team.id,
    name: 'Welcomer',
    childrenRole: 'NONE',
    active: true,
    requiredCredentials: [],
  }
  const person = {
    volunteer: {
      id: 'volunteer-1',
      personId: 'person-1',
      accountId: 'account-1',
      status: 'ACTIVE',
      version: 0,
      availabilityRevision: 0,
    },
    name: 'Solly Mabasa',
  }
  const shift = {
    id: 'shift-1',
    teamId: team.id,
    roleId: role.id,
    sourceKind: 'STANDALONE',
    sourceId: null,
    startsAt: new Date(Date.now() + 86400000).toISOString(),
    endsAt: new Date(Date.now() + 90000000).toISOString(),
    positions: 2,
    status: 'OPEN',
    version: 0,
    scheduleRevision: 0,
  }
  const invitation = {
    invitation: {
      id: 'invitation-1',
      assignmentId: 'assignment-1',
      version: 0,
      status: 'PENDING',
      expiresAt: shift.startsAt,
    },
    effectiveStatus: 'PENDING',
    team: team.name,
    role: role.name,
    startsAt: shift.startsAt,
    endsAt: shift.endsAt,
    readiness: {
      ready: false,
      issues: ['Availability is unknown or does not cover the entire shift.'],
    },
  }
  const booking = {
    assignment: {
      id: 'assignment-1',
      shiftId: shift.id,
      volunteerId: person.volunteer.id,
      status: confirmed ? 'CONFIRMED' : 'PROPOSED',
      version: 0,
    },
    name: person.name,
    readiness: invitation.readiness,
    invitations: [invitation],
  }
  const view = {
    shift,
    team: team.name,
    role: role.name,
    assignments: [booking],
    reserved: 1,
    confirmed: 0,
    ready: 0,
    vacancies: 1,
  }
  const requests: { method: string; path: string; body: any }[] = []
  await page.route('**/api/volunteering/**', async (route) => {
    const request = route.request(),
      path = new URL(request.url()).pathname,
      body = request.postDataJSON()
    requests.push({ method: request.method(), path, body })
    if (path.endsWith('/credential-types')) return route.fulfill({ json: [] })
    if (path.endsWith('/activities')) return route.fulfill({ json: [] })
    if (path.endsWith('/me/invitations'))
      return route.fulfill({ json: [invitation] })
    if (path.endsWith('/respond')) {
      invitation.effectiveStatus = body.decision
      return route.fulfill({ status: 204 })
    }
    if (path.endsWith('/volunteers/volunteer-1'))
      return route.fulfill({
        json: { person, credentials: [], weekly: [], exceptions: [] },
      })
    if (path.endsWith('/volunteers'))
      return route.fulfill({ json: { volunteers: [person], total: 1 } })
    if (path.endsWith('/teams/team-1'))
      return route.fulfill({
        json: { team, roles: [role], memberships: [], approvals: [] },
      })
    if (path.endsWith('/teams'))
      return route.fulfill({ json: { teams: [team], total: 1 } })
    if (path.endsWith('/shifts/shift-1')) return route.fulfill({ json: view })
    if (path.endsWith('/shifts/preview'))
      return route.fulfill({
        json: body.dates.map((date: string, index: number) => ({
          requestId: 'request-' + index,
          teamId: body.teamId,
          roleId: body.roleId,
          sourceKind: 'STANDALONE',
          sourceId: null,
          startsAt: date + 'T06:00:00Z',
          endsAt: date + 'T09:00:00Z',
          positions: body.positions,
        })),
      })
    if (path.endsWith('/shifts/bulk'))
      return route.fulfill({ json: ['new-shift'] })
    if (path.endsWith('/shifts'))
      return route.fulfill({ json: { shifts: [view], total: 1 } })
    if (path.endsWith('/deliveries'))
      return route.fulfill({
        json: { deliveries: [], total: 0, failed: 0, waiting: 0 },
      })
    return route.fulfill({ json: [] })
  })
  return { ...base, requests }
}
for (const width of [390, 1440])
  test(`volunteer workspace fits at ${width}px and exposes preparation separately`, async ({
    page,
  }) => {
    await page.setViewportSize({ width, height: 1000 })
    const { errors } = await fixture(page)
    await page.goto('/volunteering')
    if (width === 1440)
      await expect(
        page
          .locator('.app-drawer')
          .getByRole('link', { name: 'Volunteers', exact: true }),
      ).toBeVisible()
    await expect(
      page.getByRole('heading', { name: 'Make room for every gift.' }),
    ).toBeVisible()
    await page.getByRole('button', { name: 'Open volunteer profile' }).click()
    await expect(
      page.getByRole('dialog').getByRole('heading', { name: 'Solly Mabasa' }),
    ).toBeVisible()
    await expect(
      page.getByText('Availability has not been recorded.'),
    ).toBeVisible()
    await page.getByRole('button', { name: 'Close volunteer profile' }).click()
    await page
      .getByRole('navigation', { name: 'Volunteer management' })
      .getByRole('link', { name: 'Teams & roles' })
      .click()
    await expect(
      page.getByRole('heading', { name: 'Teams with purpose.' }),
    ).toBeVisible()
    await page.getByRole('button', { name: 'Open team', exact: true }).click()
    await expect(page.getByText('Welcomer', { exact: true })).toBeVisible()
    await page.getByRole('button', { name: 'Close team', exact: true }).click()
    await page
      .getByRole('navigation', { name: 'Volunteer management' })
      .getByRole('link', { name: 'Schedule', exact: true })
      .click()
    await expect(
      page.getByRole('heading', { name: 'A thoughtful rhythm of serving.' }),
    ).toBeVisible()
    await page.screenshot({
      path: `/app/test-results/serving-schedule-${width}.png`,
      fullPage: true,
    })
    await page.getByRole('button', { name: 'Open shift', exact: true }).click()
    await expect(
      page.getByText('Preparation needed', { exact: true }),
    ).toBeVisible()
    await expect(
      page.getByText(
        'Availability is unknown or does not cover the entire shift.',
      ),
    ).toBeVisible()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
      ),
    ).toBe(true)
    expect(errors).toEqual([])
  })
test('recurring previews are invalidated after a form change and apply only reviewed drafts', async ({
  page,
}) => {
  const { requests, errors } = await fixture(page)
  await page.goto('/volunteering/schedule?action=create')
  const dialog = page.getByRole('dialog')
  await expect(
    dialog.getByRole('heading', { name: 'Make room for a rhythm' }),
  ).toBeVisible()
  await dialog.getByLabel('Team *', { exact: true }).press('ArrowDown')
  await page.getByRole('option', { name: 'Welcome team' }).click()
  await dialog.getByLabel('Role *', { exact: true }).press('ArrowDown')
  await page.getByRole('option', { name: 'Welcomer' }).click()
  await dialog.getByLabel('Number of shifts (1–25)').fill('3')
  await dialog
    .getByRole('button', { name: 'Review dates', exact: true })
    .click()
  await expect(dialog.getByText('3 shifts ready to create')).toBeVisible()
  await expect(
    dialog.getByRole('button', { name: 'Create reviewed shifts' }),
  ).toBeEnabled()
  await dialog.getByLabel('Number of shifts (1–25)').fill('2')
  await expect(
    dialog.getByRole('button', { name: 'Create reviewed shifts' }),
  ).toBeDisabled()
  await dialog
    .getByRole('button', { name: 'Review dates', exact: true })
    .click()
  await dialog.getByRole('button', { name: 'Create reviewed shifts' }).click()
  await expect(dialog).toBeHidden()
  expect(
    requests.find((request) => request.path.endsWith('/shifts/bulk'))?.body,
  ).toHaveLength(2)
  expect(errors).toEqual([])
})
test('a volunteer can accept an invitation despite outstanding preparation', async ({
  page,
}) => {
  const { requests, errors } = await fixture(page, false)
  await page.goto('/serve')
  await expect(
    page.getByRole('heading', { name: 'Your invitation to serve.' }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Accept', exact: true }).click()
  await page
    .getByRole('button', { name: 'Accept invitation', exact: true })
    .click()
  await expect(page.getByText('ACCEPTED', { exact: true })).toBeVisible()
  expect(
    requests.find((request) => request.path.endsWith('/respond'))?.body,
  ).toEqual({ expectedVersion: 0, decision: 'ACCEPTED' })
  expect(errors).toEqual([])
})
test('read-only coordinators can inspect rosters without planning controls', async ({
  page,
}) => {
  await fixture(page, false)
  await page.goto('/volunteering/schedule')
  await expect(
    page.getByRole('heading', { name: 'A thoughtful rhythm of serving.' }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Plan shifts', exact: true }),
  ).toHaveCount(0)
  await page.getByRole('button', { name: 'Open shift', exact: true }).click()
  await expect(
    page.getByRole('button', { name: 'Add volunteer', exact: true }),
  ).toHaveCount(0)
})

test('reminders require a fresh content review and recipient verification', async ({
  page,
}) => {
  const { errors } = await fixture(page, true, true)
  let approval: any = null
  await page.route(
    '**/api/volunteering/assignments/assignment-1/reminder-contacts',
    (route) =>
      route.fulfill({
        json: [{ id: 'contact-1', value: 'solly@example.org' }],
      }),
  )
  await page.route(
    '**/api/volunteering/assignments/assignment-1/reminder-review',
    (route) =>
      route.fulfill({
        json: {
          recipient: 'solly@example.org',
          subject: 'Your Welcome team shift',
          html: '<p>Hello Solly</p>',
          hash: 'review-hash',
        },
      }),
  )
  await page.route(
    '**/api/volunteering/assignments/assignment-1/reminders',
    (route) => {
      approval = route.request().postDataJSON()
      return route.fulfill({ json: 'delivery-1' })
    },
  )
  await page.goto('/volunteering/schedule')
  await page.getByRole('button', { name: 'Open shift', exact: true }).click()
  await page.getByRole('button', { name: 'Send reminder', exact: true }).click()
  const dialog = page.getByRole('dialog').last()
  await dialog
    .getByLabel('Recipient email *', { exact: true })
    .press('ArrowDown')
  await page.getByRole('option', { name: 'solly@example.org' }).click()
  await dialog
    .getByRole('button', { name: 'Review reminder', exact: true })
    .click()
  await expect(
    dialog.getByText('Your Welcome team shift', { exact: true }),
  ).toBeVisible()
  await dialog
    .getByLabel('Personal message *', { exact: true })
    .fill('A different message')
  await expect(
    dialog.getByText('Your Welcome team shift', { exact: true }),
  ).toHaveCount(0)
  await expect(
    dialog.getByRole('button', { name: 'Approve & queue reminder' }),
  ).toBeDisabled()
  await dialog
    .getByRole('button', { name: 'Review reminder', exact: true })
    .click()
  await dialog
    .getByLabel('Recipient verification and communication permission *', {
      exact: true,
    })
    .fill('Confirmed own email and reminder permission by telephone')
  await dialog
    .getByLabel(
      'I verified the recipient and permission to send this reminder.',
    )
    .check()
  await dialog.getByRole('button', { name: 'Approve & queue reminder' }).click()
  await expect(
    page.getByRole('heading', { name: 'A thoughtful reminder', exact: true }),
  ).toBeHidden()
  expect(approval.reviewHash).toBe('review-hash')
  expect(approval.contactId).toBe('contact-1')
  expect(errors).toEqual([])
})

test('event shifts reuse one team and role across several selected occurrences',async({page})=>{
 const {requests,errors}=await fixture(page)
 const activities=[{id:'event-date-1',label:'Sunday service',startsAt:new Date(Date.now()+86400000).toISOString(),endsAt:new Date(Date.now()+90000000).toISOString(),venueLabel:'Main hall'},{id:'event-date-2',label:'Evening service',startsAt:new Date(Date.now()+172800000).toISOString(),endsAt:new Date(Date.now()+176400000).toISOString(),venueLabel:'Main hall'}]
 await page.route('**/api/volunteering/activities?**',route=>route.fulfill({json:activities}))
 await page.goto('/volunteering/schedule?action=create');const dialog=page.getByRole('dialog')
 await dialog.getByLabel('Team *',{exact:true}).press('ArrowDown');await page.getByRole('option',{name:'Welcome team'}).click();await dialog.getByLabel('Role *',{exact:true}).press('ArrowDown');await page.getByRole('option',{name:'Welcomer'}).click();await dialog.getByLabel('Activity',{exact:true}).press('ArrowDown');await page.getByRole('option',{name:'Event occurrences',exact:true}).click()
 await dialog.getByLabel('Event occurrences',{exact:true}).press('ArrowDown');await page.getByRole('option',{name:/Sunday service/}).click();await page.getByRole('option',{name:/Evening service/}).click();await page.keyboard.press('Escape');await dialog.getByRole('button',{name:'Review dates',exact:true}).click();await expect(dialog.getByText('2 shifts ready to create')).toBeVisible();await dialog.getByRole('button',{name:'Create reviewed shifts'}).click();await expect(dialog).toBeHidden();const payload=requests.find(r=>r.path.endsWith('/shifts/bulk'))?.body;expect(payload).toHaveLength(2);expect(payload.map((d:any)=>d.sourceId)).toEqual(['event-date-1','event-date-2']);expect(payload.every((d:any)=>d.sourceKind==='EVENT_OCCURRENCE'&&d.teamId==='team-1'&&d.roleId==='role-1')).toBe(true);expect(errors).toEqual([])
})
