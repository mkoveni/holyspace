import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'

async function seriesFixture (page: Page, permissions?: string[]) {
  const fixture = await ministryFixture(page, permissions)
  // Recurrence tests use a same-day source; relative times can cross midnight in the location zone.
  const sourceDate = new Date().toISOString().slice(0, 10)
  fixture.session.session.startsAt = `${sourceDate}T08:00:00.000Z`
  fixture.session.session.endsAt = `${sourceDate}T09:00:00.000Z`
  const requests: { method: string, path: string, body: any }[] = []
  let created = false
  let cancelled = false
  let clash = false
  const summary = () => ({
    id: 'series-1',
    name: 'Sunday Explorers weekly sessions',
    sourceSessionId: 'session-1',
    className: 'Sunday Explorers',
    roomName: 'Garden Room',
    timeZone: 'Africa/Johannesburg',
    firstDate: '2026-11-01',
    intervalWeeks: 1,
    occurrences: 3,
    totalSessions: 3,
    plannedSessions: cancelled ? 0 : 3,
    cancelledSessions: cancelled ? 3 : 0,
    stopped: cancelled,
    version: cancelled ? 1 : 0,
  })
  await page.route('**/api/children/session-series**', async route => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    const body = request.postDataJSON()
    requests.push({ method: request.method(), path, body })
    if (path.endsWith('/preview')) {
      const first = new Date(body.recurrence.firstDate + 'T12:00:00Z')
      return route.fulfill({
        json: {
          className: 'Sunday Explorers',
          roomName: 'Garden Room',
          timeZone: 'Africa/Johannesburg',
          seatLimit: 20,
          team: [
            { workerId: 'worker-1', name: 'Naledi Mokoena', role: 'LEADER' },
          ],
          checkedAt: new Date().toISOString(),
          occurrences: Array.from(
            { length: body.recurrence.occurrences },
            (_, index) => {
              const date = new Date(first.getTime() + index * body.recurrence.intervalWeeks * 7 * 86_400_000).toISOString().slice(0, 10)
              const starts = new Date(`${date}T${body.recurrence.startsAt}+02:00`)
              const excluded = body.recurrence.excludedDates.includes(date)
              return {
                slot: {
                  date,
                  startsAt: starts.toISOString(),
                  endsAt: new Date(`${date}T${body.recurrence.endsAt}+02:00`).toISOString(),
                  excluded,
                },
                conflicts:
                  clash && index === 1 && !excluded
                    ? ['The class or room is already booked for this time.']
                    : [],
                staffingWarnings:
                  index > 0 ? ['Renew screening before this session.'] : [],
              }
            },
          ),
        },
      })
    }
    if (path.endsWith('/cancel')) {
      cancelled = true
      return route.fulfill({ status: 204 })
    }
    if (
      path === '/api/children/session-series'
      && request.method() === 'POST'
    ) {
      created = true
      return route.fulfill({ status: 201, json: { id: 'series-1' } })
    }
    if (path === '/api/children/session-series') {
      return route.fulfill({
        json: { series: created ? [summary()] : [], total: created ? 1 : 0 },
      })
    }
    return route.fulfill({
      json: {
        series: summary(),
        sessions: Array.from({ length: 3 }, (_, index) => ({
          id: `future-${index}`,
          date: `2026-11-${String(1 + index * 7).padStart(2, '0')}`,
          startsAt: new Date(
            Date.now() + (index + 1) * 7 * 86_400_000,
          ).toISOString(),
          endsAt: new Date(
            Date.now() + (index + 1) * 7 * 86_400_000 + 3_600_000,
          ).toISOString(),
          status: cancelled ? 'CANCELLED' : 'PLANNED',
        })),
      },
    })
  })
  return {
    ...fixture,
    seriesRequests: requests,
    setClash: () => {
      clash = true
    },
  }
}

for (const width of [390, 1440]) {
  test(`preview and create a weekly series with an absent team at ${width}px`, async ({
    page,
  }) => {
    const fixture = await seriesFixture(page)
    await page.setViewportSize({ width, height: 1024 })
    await page.goto('/children/sessions/session-1')
    await page
      .getByRole('button', { name: 'Repeat session', exact: true })
      .click()
    const dialog = page.getByRole('dialog')
    await expect(
      dialog.getByRole('heading', { name: 'A rhythm of belonging' }),
    ).toBeVisible()
    await dialog.getByLabel('Number of dates *', { exact: true }).fill('3')
    await expect(
      dialog.getByRole('button', { name: 'Create 3 sessions', exact: true }),
    ).toBeDisabled()
    await dialog.getByRole('button', { name: 'Preview dates & team' }).click()
    await expect(
      dialog.getByText(/presence to be confirmed on the day/),
    ).toBeVisible()
    await expect(
      dialog.getByText('Renew screening before this session.').first(),
    ).toBeVisible()
    await expect(
      dialog.getByRole('button', { name: 'Create 3 sessions', exact: true }),
    ).toBeEnabled()
    expect(
      await dialog.evaluate(
        element => element.scrollWidth <= element.clientWidth,
      ),
    ).toBe(true)
    await dialog.locator('.series-preview').scrollIntoViewIfNeeded()
    await dialog.screenshot({
      path: `test-results/recurring-preview-${width}.png`,
      animations: 'disabled',
    })
    await dialog
      .getByRole('button', { name: 'Create 3 sessions', exact: true })
      .click()
    await expect(page).toHaveURL(/children\/session-series\?series=series-1/)
    await expect(
      page.getByRole('dialog').getByRole('heading', {
        name: 'Sunday Explorers weekly sessions',
        exact: true,
      }),
    ).toBeVisible()
    const create = fixture.seriesRequests.find(
      value =>
        value.path === '/api/children/session-series'
        && value.method === 'POST',
    )
    expect(create?.body.draft.sourceSessionId).toBe('session-1')
    expect(create?.body.draft.recurrence.occurrences).toBe(3)
    expect(create?.body.requestId).toBeTruthy()
    expect(fixture.errors).toEqual([])
  })
}

test('conflicting dates can be skipped and require a fresh preview', async ({
  page,
}) => {
  const fixture = await seriesFixture(page)
  fixture.setClash()
  await page.goto('/children/sessions/session-1')
  await page
    .getByRole('button', { name: 'Repeat session', exact: true })
    .click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Number of dates *', { exact: true }).fill('3')
  await dialog.getByRole('button', { name: 'Preview dates & team' }).click()
  await expect(
    dialog.getByText('The class or room is already booked for this time.'),
  ).toBeVisible()
  await expect(
    dialog.getByRole('button', { name: 'Create 3 sessions', exact: true }),
  ).toBeDisabled()
  await dialog
    .getByRole('checkbox', { name: /^Include / })
    .nth(1)
    .uncheck()
  await expect(
    dialog.getByText(
      'The plan has changed. Refresh the preview before creating the series.',
    ),
  ).toBeVisible()
  await expect(
    dialog.getByRole('button', { name: 'Create 2 sessions', exact: true }),
  ).toBeDisabled()
  await dialog.getByRole('button', { name: 'Refresh preview' }).click()
  await expect(
    dialog.getByRole('button', { name: 'Create 2 sessions', exact: true }),
  ).toBeEnabled()
  await dialog
    .getByRole('button', { name: 'Create 2 sessions', exact: true })
    .click()
  const created = fixture.seriesRequests.find(
    value =>
      value.path === '/api/children/session-series' && value.method === 'POST',
  )
  expect(created?.body.draft.recurrence.excludedDates).toHaveLength(1)
  expect(fixture.errors).toEqual([])
})

test('coordinators can cancel future planned dates with a reason', async ({
  page,
}) => {
  const fixture = await seriesFixture(page)
  await page.goto('/children/session-series?series=series-1')
  await page
    .getByRole('button', { name: 'Cancel future planned dates', exact: true })
    .click()
  const confirmation = page.getByRole('dialog').last()
  await confirmation
    .getByLabel('Cancellation reason *', { exact: true })
    .fill('School holiday closure')
  await confirmation
    .getByRole('button', {
      name: 'Cancel future planned sessions',
      exact: true,
    })
    .click()
  await expect(
    page.getByRole('button', {
      name: 'Cancel future planned dates',
      exact: true,
    }),
  ).toHaveCount(0)
  expect(
    fixture.seriesRequests.find(value => value.path.endsWith('/cancel'))
      ?.body,
  ).toEqual({ expectedVersion: 0, reason: 'School holiday closure' })
  expect(fixture.errors).toEqual([])
})

test('readers can browse series but cannot repeat or cancel sessions', async ({
  page,
}) => {
  const fixture = await seriesFixture(page, ['children.read'])
  await page.goto('/children/sessions/session-1')
  await expect(
    page.getByRole('button', { name: 'Repeat session', exact: true }),
  ).toHaveCount(0)
  await page.goto('/children/session-series?series=series-1')
  await expect(
    page.getByRole('dialog').getByRole('heading', {
      name: 'Sunday Explorers weekly sessions',
      exact: true,
    }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', {
      name: 'Cancel future planned dates',
      exact: true,
    }),
  ).toHaveCount(0)
  expect(fixture.seriesRequests.some(value => value.method === 'POST')).toBe(
    false,
  )
  expect(fixture.errors).toEqual([])
})
