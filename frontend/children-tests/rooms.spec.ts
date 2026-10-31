import type { Location, Room } from '../src/children/types'
import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'

for (const mobile of [false, true]) {
  test(`create the first location and room, retaining the location after a room failure at ${mobile ? 'mobile' : 'desktop'} size`, async ({
    page,
  }) => {
    await ministryFixture(page)
    await page.setViewportSize({ width: mobile ? 390 : 1440, height: 1000 })
    const locations: Location[] = []
    const rooms: Room[] = []
    let locationAttempts = 0
    let roomAttempts = 0
    await page.route('**/api/children/locations**', async route => {
      if (route.request().method() === 'GET') {
        await route.fulfill({ json: locations })
        return
      }
      locationAttempts++
      const id = locationAttempts === 1 ? 'location-new' : 'location-second'
      locations.push({ id, ...route.request().postDataJSON() })
      await route.fulfill({ status: 201, json: { id } })
    })
    await page.route('**/api/children/rooms**', async route => {
      if (route.request().method() === 'GET') {
        await route.fulfill({ json: rooms })
        return
      }
      roomAttempts++
      if (roomAttempts === 1) {
        await route.fulfill({
          status: 500,
          json: { message: 'Unable to save room. Please try again.' },
        })
        return
      }
      rooms.push({ id: 'room-new', ...route.request().postDataJSON() })
      await route.fulfill({ status: 201, json: { id: 'room-new' } })
    })
    await page.goto('/children/settings')
    await expect(
      page.getByText(
        'Choose Add room to create your first location and room together.',
      ),
    ).toBeVisible()
    await page.getByRole('button', { name: 'Add room', exact: true }).click()
    await page
      .getByRole('button', { name: 'Create location & room', exact: true })
      .click()
    await expect(page.getByText('This field is required.').first()).toBeVisible()
    expect(locationAttempts).toBe(0)
    await page
      .getByLabel('Location name *', { exact: true })
      .fill('West Campus')
    await page.getByLabel('Room name *', { exact: true }).fill('Sunshine Room')
    await page.getByLabel('Room capacity *', { exact: true }).fill('16')
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: `test-results/first-room-${mobile ? 'mobile' : 'desktop'}.png`,
      fullPage: true,
      animations: 'disabled',
    })
    await page
      .getByRole('button', { name: 'Create location & room', exact: true })
      .click()
    await expect(
      page.getByRole('alert').filter({ hasText: 'Unable to save room' }),
    ).toBeVisible()
    await expect(page.getByLabel('Room name *', { exact: true })).toHaveValue(
      'Sunshine Room',
    )
    await expect(
      page.getByRole('dialog').getByText('West Campus', { exact: true }),
    ).toBeVisible()
    await page
      .getByRole('button', { name: 'Save changes', exact: true })
      .click()
    await expect(page.getByRole('dialog')).toHaveCount(0)
    await expect(page.getByText('Sunshine Room', { exact: true })).toBeVisible()
    expect(locationAttempts).toBe(1)
    expect(roomAttempts).toBe(2)
    expect(locations).toEqual([
      {
        id: 'location-new',
        name: 'West Campus',
        timeZone: 'Africa/Johannesburg',
      },
    ])
    expect(rooms).toEqual([
      {
        id: 'room-new',
        locationId: 'location-new',
        name: 'Sunshine Room',
        capacity: 16,
      },
    ])

    await page.getByRole('button', { name: 'Add room', exact: true }).click()
    await expect(
      page.getByLabel('Location name *', { exact: true }),
    ).toHaveCount(0)
    await expect(
      page.getByRole('dialog').getByText('West Campus', { exact: true }),
    ).toBeVisible()
    await page
      .getByRole('button', { name: 'Add a new location', exact: true })
      .click()
    await page
      .getByLabel('Location name *', { exact: true })
      .fill('East Campus')
    await page.getByLabel('Room name *', { exact: true }).fill('Garden Room')
    await page
      .getByRole('button', { name: 'Create location & room', exact: true })
      .click()
    await expect(page.getByRole('dialog')).toHaveCount(0)
    expect(locationAttempts).toBe(2)
    await expect(page.getByText('East Campus', { exact: true })).toBeVisible()
    expect(rooms[1]?.locationId).toBe('location-second')
  })
}
