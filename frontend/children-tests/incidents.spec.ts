import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'
const full = [
  'children.read',
  'children.manage',
  'children.safeguarding',
  'children.station',
  'children.supervise',
  'children.support',
  'people.read',
  'people.manage',
  'identity.manage',
  'children.incidents.record',
  'children.incidents.manage',
]
async function incidentFixture(page: Page, permissions = full) {
  const fixture = await ministryFixture(page, permissions)
  const requests: { path: string; method: string; body: any }[] = []
  let reported = false
  const incident = {
    id: 'incident-1',
    childId: 'child-1',
    sessionId: 'session-1',
    segmentId: 'segment-1',
    category: 'INJURY',
    severity: 'LOW',
    occurredAt: new Date(Date.now() - 60000).toISOString(),
    reportedBy: 'account-1',
    reportedAt: new Date().toISOString(),
    status: 'OPEN',
    ownerId: null as string | null,
    reviewOn: '2026-10-26',
    retainUntil: '2027-10-26',
    version: 0,
  }
  const events = [
    {
      id: 'event-0',
      revision: 0,
      type: 'REPORTED',
      actorId: 'account-1',
      occurredAt: new Date().toISOString(),
      note: 'Incident recorded.',
      guardianId: null,
    },
  ]
  await page.route('**/api/children/incidents**', async (route) => {
    const req = route.request(),
      url = new URL(req.url()),
      path = url.pathname,
      body = req.postDataJSON()
    requests.push({ path, method: req.method(), body })
    if (path.endsWith('/attendance'))
      return route.fulfill({
        json: {
          visits: [
            {
              visit: {
                segmentId: 'segment-1',
                childId: 'child-1',
                arrivedAt: new Date(Date.now() - 180000).toISOString(),
                leftAt: null,
              },
              childName: 'Lindiwe Mokoena',
            },
          ],
          total: 1,
        },
      })
    if (path.endsWith('/owners'))
      return route.fulfill({
        json: [{ id: 'account-1', name: 'Naledi Mokoena' }],
      })
    if (path.endsWith('/guardians'))
      return route.fulfill({
        json: [
          {
            authorityId: 'authority-1',
            guardianId: 'adult-1',
            name: 'Solly Mabasa',
          },
        ],
      })
    if (path.endsWith('/access'))
      return route.fulfill({
        json: {
          incident,
          details: {
            description: 'Private incident account',
            immediateAction: 'Private immediate response',
          },
          events: [...events].reverse(),
          totalEvents: events.length,
        },
      })
    if (path.endsWith('/follow-up')) {
      incident.version++
      if (body.action === 'TRIAGED') {
        incident.status = 'IN_REVIEW'
        incident.ownerId = body.ownerId
        incident.reviewOn = body.reviewOn
      }
      if (body.action === 'CLOSED') incident.status = 'CLOSED'
      events.push({
        id: `event-${incident.version}`,
        revision: incident.version,
        type: body.action,
        actorId: 'account-1',
        occurredAt: new Date().toISOString(),
        note: body.note,
        guardianId: null,
      })
      return route.fulfill({ status: 204 })
    }
    if (req.method() === 'POST') {
      reported = true
      return route.fulfill({ status: 201, json: { id: incident.id } })
    }
    return route.fulfill({
      json: {
        incidents:
          reported && incident.status !== 'CLOSED'
            ? [{ incident, childName: 'Lindiwe Mokoena' }]
            : [],
        total: reported && incident.status !== 'CLOSED' ? 1 : 0,
        overdue: reported ? 1 : 0,
        unassigned: reported && !incident.ownerId ? 1 : 0,
      },
    })
  })
  return {
    ...fixture,
    incidentRequests: requests,
    seed: () => {
      reported = true
    },
  }
}
for (const width of [390, 1440])
  test(`record and triage an incident with explicit private access at ${width}px`, async ({
    page,
  }) => {
    const fixture = await incidentFixture(page)
    await page.setViewportSize({ width, height: 1000 })
    await page.goto('/children/incidents?session=session-1')
    await page
      .getByRole('button', { name: 'Record incident', exact: true })
      .click()
    const form = page.getByRole('dialog')
    await form
      .getByLabel("Child's attendance *", { exact: true })
      .press('ArrowDown')
    await page.getByRole('option', { name: /Lindiwe Mokoena/ }).click()
    await form
      .getByLabel('What happened *', { exact: true })
      .fill('Private incident account')
    await form
      .getByLabel('Immediate action taken *', { exact: true })
      .fill('Private immediate response')
    await form
      .getByRole('button', { name: 'Record incident', exact: true })
      .click()
    await expect(form).toHaveCount(0)
    await expect(
      page.getByText('Lindiwe Mokoena', { exact: true }),
    ).toBeVisible()
    expect(
      fixture.incidentRequests.filter((r) => r.path.endsWith('/access')),
    ).toHaveLength(0)
    await expect(
      page.getByText('Private incident account', { exact: true }),
    ).toHaveCount(0)
    await page.getByRole('button', { name: 'Open private details' }).click()
    const details = page.getByRole('dialog')
    await expect(
      details.getByText('Private incident account', { exact: true }),
    ).toBeVisible()
    await details
      .getByLabel('Responsible coordinator *', { exact: true })
      .fill('Naledi')
    await page
      .getByRole('option', { name: 'Naledi Mokoena', exact: true })
      .click()
    await details
      .getByLabel('Private follow-up note *', { exact: true })
      .fill('Owner will review with the team')
    await details
      .getByRole('button', { name: 'Save follow-up', exact: true })
      .click()
    await expect(
      details.getByText('Owner will review with the team', { exact: true }),
    ).toBeVisible()
    expect(await details.evaluate((e) => e.scrollWidth <= e.clientWidth)).toBe(
      true,
    )
    await details.screenshot({
      path: `test-results/incident-details-${width}.png`,
      animations: 'disabled',
    })
    await details
      .getByRole('button', { name: 'Close incident details', exact: true })
      .click()
    await expect(
      page.getByText('Private incident account', { exact: true }),
    ).toHaveCount(0)
    const report = fixture.incidentRequests.find(
      (r) => r.path === '/api/children/incidents' && r.method === 'POST',
    )?.body
    expect(report.segmentId).toBe('segment-1')
    expect(report.occurredAt).toMatch(/Z$/)
    expect(report.requestId).toBeTruthy()
    expect(fixture.errors).toEqual([])
  })
test('reporting access does not fetch private incident lists or details', async ({
  page,
}) => {
  const fixture = await incidentFixture(
    page,
    full.filter((p) => p !== 'children.incidents.manage'),
  )
  await page.goto('/children/incidents?session=session-1')
  await expect(
    page.getByText(
      'Follow-up and private details require incident-coordinator access.',
      { exact: false },
    ),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Open private details' }),
  ).toHaveCount(0)
  expect(fixture.incidentRequests).toEqual([])
  expect(fixture.errors).toEqual([])
})
test('closeout requires acknowledgement of outstanding incident follow-up', async ({
  page,
}) => {
  const fixture = await incidentFixture(page)
  fixture.seed()
  await page.route('**/api/children/sessions/session-1/closeout', (route) =>
    route.fulfill({
      json: {
        visits: 1,
        children: 1,
        awaitingCollection: 0,
        collected: 1,
        transferredOut: 0,
        labelsNeedingAttention: 0,
        failedHandoverAttempts: 0,
        supervisorCollections: 0,
        checkedAt: new Date().toISOString(),
        incidents: { unresolved: 1, overdue: 1, unassigned: 1 },
      },
    }),
  )
  await page.goto('/children/sessions/session-1')
  await page
    .getByRole('button', { name: 'Review & close session', exact: true })
    .click()
  const dialog = page.getByRole('dialog')
  await dialog
    .getByRole('checkbox', { name: /room|collection|confirm/i })
    .last()
    .check()
  await dialog
    .getByLabel('Closeout note *', { exact: true })
    .fill('Room checked; incident owner will follow up')
  await expect(
    dialog.getByRole('button', { name: 'Close session', exact: true }),
  ).toBeDisabled()
  await dialog
    .getByRole('checkbox', { name: /I reviewed the outstanding incidents/ })
    .check()
  await dialog
    .getByRole('button', { name: 'Close session', exact: true })
    .click()
  await expect(dialog).toHaveCount(0)
  expect(
    fixture.requests.find((r) => r.path === '/api/children/sessions/status')
      ?.body.acknowledgeIncidents,
  ).toBe(true)
  expect(fixture.errors).toEqual([])
})
