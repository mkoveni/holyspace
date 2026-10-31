import type { Page } from '@playwright/test'
import { readFile } from 'node:fs/promises'
import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'

async function reports (page: Page, permissions?: string[]) {
  const fixture = await ministryFixture(page, permissions)
  const visits = Array.from({ length: 51 }, (_, index) => ({
    id: `segment-${index}`,
    attendanceId: `attendance-${index}`,
    childId: `child-${index}`,
    childName:
      index === 50
        ? 'Zanele Mabasa'
        : (index === 0
            ? 'Lindiwe Mabasa'
            : `Child ${index}`),
    sessionId: 'session-1',
    programmeId: 'programme-1',
    programme: 'Little Lights',
    ministryClass: 'Sunday Explorers',
    room: 'Garden Room',
    location: 'Main Campus',
    timeZone: 'Africa/Johannesburg',
    arrivedAt: new Date().toISOString(),
    leftAt: index === 0 ? new Date().toISOString() : null,
    outcome: index === 0 ? 'COLLECTED' : 'ATTENDING',
    supervisorCollection: index === 0,
  }))
  const queries: URLSearchParams[] = []
  await page.route('**/api/children/attendance-reports**', async route => {
    const url = new URL(route.request().url())
    queries.push(url.searchParams)
    const matching = visits.filter(visit =>
      visit.childName
        .toLowerCase()
        .includes((url.searchParams.get('search') || '').toLowerCase()),
    )
    if (url.pathname.endsWith('/export')) {
      return route.fulfill({
        contentType: 'text/csv',
        headers: {
          'Content-Disposition':
            'attachment; filename="children-attendance.csv"',
        },
        body:
          'Child,Outcome\r\n'
          + matching
            .map(visit => `${visit.childName},${visit.outcome}`)
            .join('\r\n'),
      })
    }
    const offset = Number(url.searchParams.get('offset') || 0)
    return route.fulfill({
      json: {
        visits: matching.slice(offset, offset + 25),
        summary: {
          visits: matching.length,
          children: matching.length,
          attending: matching.filter(visit => visit.outcome === 'ATTENDING')
            .length,
          collected: matching.filter(visit => visit.outcome === 'COLLECTED').length,
          transferred: 0,
          supervisorCollections: matching.filter(visit => visit.supervisorCollection).length,
        },
        timeZone: 'Africa/Johannesburg',
        checkedAt: new Date().toISOString(),
      },
    })
  })
  await page.route('**/api/children/attendance/*/timeline?**', route =>
    route.fulfill({
      json: {
        visits: [
          {
            ...visits[0],
            id: 'source',
            room: 'Welcome Room',
            outcome: 'TRANSFERRED',
            supervisorCollection: false,
          },
          visits[0],
        ],
        summary: {
          visits: 2,
          children: 1,
          collected: 1,
          transferred: 1,
          attending: 0,
          supervisorCollections: 1,
        },
        timeZone: 'Africa/Johannesburg',
        checkedAt: new Date().toISOString(),
      },
    }),
  )
  return { ...fixture, queries }
}

for (const width of [390, 1440]) {
  test(`reports show global totals, paginated visits and journeys at ${width}px`, async ({
    page,
  }) => {
    const fixture = await reports(page)
    await page.setViewportSize({ width, height: 1000 })
    await page.goto('/children/reports')
    await expect(
      page.getByRole('heading', { name: 'Every visit tells a story.' }),
    ).toBeVisible()
    await expect(page.getByText('1–25 of 51 visits')).toBeVisible()
    await page
      .getByRole('button', { name: 'View journey for Lindiwe Mabasa' })
      .click()
    const dialog = page.getByRole('dialog')
    await expect(
      dialog.getByRole('heading', { name: 'Attendance journey' }),
    ).toBeVisible()
    await expect(
      dialog.getByRole('heading', { name: 'Sunday Explorers · Welcome Room' }),
    ).toBeVisible()
    await expect(
      dialog.getByText(
        'Supervisor label exception · identity and pickup authority verified',
      ),
    ).toBeVisible()
    await dialog
      .getByRole('button', { name: 'Close attendance journey' })
      .click()
    await page.getByRole('button', { name: 'Next', exact: true }).click()
    await expect(page.getByText('26–50 of 51 visits')).toBeVisible()
    await page.getByLabel('Child name', { exact: true }).fill('Zanele')
    await expect(
      page.getByRole('button', { name: 'Export CSV' }),
    ).toBeDisabled()
    await page.getByRole('button', { name: 'Apply filters' }).click()
    await expect(page.getByText('1–1 of 1 visits')).toBeVisible()
    await expect(
      page.getByRole('button', { name: 'View journey for Zanele Mabasa' }),
    ).toBeVisible()
    expect(fixture.queries.at(-1)?.get('offset')).toBe('0')
    expect(fixture.queries.at(-1)?.get('search')).toBe('Zanele')
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: `test-results/attendance-report-${width}.png`,
      fullPage: true,
      animations: 'disabled',
    })
    expect(fixture.errors).toEqual([])
  })
}

test('CSV exports the complete filtered result, not the visible page', async ({
  page,
}) => {
  const fixture = await reports(page)
  await page.goto('/children/reports')
  await expect(page.getByText('1–25 of 51 visits')).toBeVisible()
  const downloaded = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Export CSV' }).click()
  const file = await downloaded
  const data = await readFile((await file.path())!, 'utf8')
  expect(file.suggestedFilename()).toBe('children-attendance.csv')
  expect(data).toContain('Zanele Mabasa')
  expect(data.trim().split('\r\n')).toHaveLength(52)
  expect(fixture.queries.at(-1)?.has('offset')).toBe(false)
  expect(fixture.errors).toEqual([])
})

test('readers can review history and dates without export controls', async ({
  page,
}) => {
  const fixture = await reports(page, ['children.read'])
  await page.goto('/children/reports?childId=child-0')
  await expect(
    page.getByText('One child’s complete attendance history for these dates'),
  ).toBeVisible()
  await expect(page.getByRole('button', { name: 'Export CSV' })).toHaveCount(0)
  await page.getByLabel('From date', { exact: true }).click()
  const picker = page.getByRole('dialog', { name: 'From date' })
  await expect(picker.getByRole('button', { name: 'Use date' })).toBeVisible()
  await picker.getByRole('button', { name: 'Cancel', exact: true }).click()
  expect(fixture.queries.at(-1)?.get('childId')).toBe('child-0')
  expect(fixture.errors).toEqual([])
})

test('failed exports show the server explanation instead of downloading a partial report', async ({
  page,
}) => {
  const fixture = await reports(page)
  await page.route('**/api/children/attendance-reports/export?**', route =>
    route.fulfill({
      status: 400,
      contentType: 'application/json',
      body: JSON.stringify({
        message:
          'This export exceeds 10,000 visits. Narrow the dates or filters.',
      }),
    }),
  )
  await page.goto('/children/reports')
  await expect(page.getByText('1–25 of 51 visits')).toBeVisible()
  const downloads: string[] = []
  page.on('download', download =>
    downloads.push(download.suggestedFilename()),
  )
  await page.getByRole('button', { name: 'Export CSV' }).click()
  await expect(
    page.getByText(
      'This export exceeds 10,000 visits. Narrow the dates or filters.',
    ),
  ).toBeVisible()
  expect(downloads).toEqual([])
  expect(fixture.errors).toEqual([])
})

test('a child profile opens journeys from its all-time attendance history', async ({
  page,
}) => {
  const fixture = await reports(page)
  await page.route('**/api/children/attendance/children/child-1?**', route =>
    route.fulfill({
      json: [
        {
          id: 'attendance-old',
          childId: 'child-1',
          sessionId: 'session-old',
          arrivedAt: '2020-01-05T07:00:00Z',
          checkedOutAt: '2020-01-05T08:00:00Z',
          collectorId: 'collector-1',
          authorizationId: 'grant-1',
          identityVerified: true,
          exceptionUsed: false,
          labelVersion: 1,
          labelIssued: false,
          labelExpiresAt: null,
          version: 2,
        },
      ],
    }),
  )
  await page.goto('/children/participants/child-1?programme=programme-1')
  await page
    .getByRole('button', { name: 'View attendance journey', exact: true })
    .click()
  const journey = page.getByRole('dialog')
  await expect(
    journey.getByRole('heading', { name: 'Attendance journey' }),
  ).toBeVisible()
  await expect(journey.getByText('2 room visits')).toBeVisible()
  expect(fixture.errors).toEqual([])
})
