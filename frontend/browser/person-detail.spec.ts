import { expect, test } from '@playwright/test'

for (const mode of ['desktop-editor', 'mobile-reader']) {
  test(`person profile balances information and preserves actions for ${mode}`, async ({ page }) => {
    const editing = mode === 'desktop-editor'
    await page.setViewportSize({ width: editing ? 1440 : 390, height: 1000 })
    await page.addInitScript(() => localStorage.setItem('holyspace.auth.token', 'profile-test-session'))
    const person = { id: 'person-layout', firstName: 'Naledi', lastName: 'Mokoena', email: 'naledi.mokoena@example.org', phone: '+27 82 123 4567', dateOfBirth: '1989-04-12', gender: 'female', status: 'active', createdAt: '2023-02-15T10:00:00Z', updatedAt: '2026-10-21T10:00:00Z' }
    await page.route('**/api/**', async route => {
      const path = new URL(route.request().url()).pathname
      const responses: Record<string, unknown> = {
        '/api/me': { id: 'test-user', username: 'Profile reviewer', status: 'active', roles: [], permissions: ['people.read', 'life_events.read', ...(editing ? ['people.manage'] : [])], mfaEnabled: true, mfaVerified: true },
        '/api/people': [person],
        '/api/people/person-layout': person,
        '/api/people/person-layout/families': [{ id: 'family-layout', name: 'Mokoena household', role: 'Parent' }],
        '/api/people/person-layout/relationships': [{ id: 'relationship-layout', relatedPersonId: 'related-layout', relatedFirstName: 'Thabo', relatedLastName: 'Mokoena', type: 'spouse', endedAt: null }],
        '/api/people/person-layout/life-events': [{ id: 'event-layout', type: 'baptism', eventDate: '2023-03-19' }],
        '/api/people/person-layout/profile': {
          addresses: [{ id: 'address-layout', type: 'Home', line1: '24 Jacaranda Avenue', city: 'Johannesburg', province: 'Gauteng', postalCode: '2196', country: 'South Africa' }],
          communicationOptions: [],
          education: [{ id: 'education-layout', institution: 'University of Johannesburg', qualification: 'Bachelor of Education', startDate: '2008-01-01', endDate: '2011-12-01' }],
          employment: [],
        },
        '/api/media/person/person-layout': { version: 0, image: null },
      }
      await route.fulfill({ json: responses[path] ?? [] })
    })
    await page.goto('/people/person-layout')
    await expect(page.getByRole('heading', { name: 'Naledi Mokoena' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Contact & membership' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'naledi.mokoena@example.org' })).toBeVisible()
    await expect(page.getByText('Mokoena household')).toBeVisible()
    await expect(page.getByText('Thabo Mokoena')).toBeVisible()
    await expect(page.getByText('24 Jacaranda Avenue, Johannesburg')).toBeVisible()
    await page.getByRole('tab', { name: 'Education' }).click()
    await expect(page.getByText('University of Johannesburg')).toBeVisible()
    if (editing) {
      const summary = await page.locator('.person-overview').boundingBox()
      const details = await page.locator('.profile-details').boundingBox()
      expect(summary?.width).toBe(details?.width)
      const cards = await page.locator('.person-community > .v-card').evaluateAll(items => items.map(item => item.getBoundingClientRect().top))
      expect(new Set(cards).size).toBe(1)
      await page.getByRole('button', { name: 'Change photo' }).click()
      await expect(page.getByRole('dialog')).toContainText('250 × 250 px')
      await page.getByRole('button', { name: 'Done', exact: true }).click()
      await page.getByRole('button', { name: 'Edit', exact: true }).click()
      await expect(page.getByRole('dialog')).toBeVisible()
      await page.getByRole('dialog').getByRole('button', { name: 'Cancel', exact: true }).click()
      await expect(page.getByRole('dialog')).toBeHidden()
    } else {
      await expect(page.getByRole('button', { name: 'Change photo' })).toHaveCount(0)
      await expect(page.getByRole('button', { name: 'Edit', exact: true })).toHaveCount(0)
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    }
    await page.screenshot({ path: `test-results/person-detail-${mode}.png`, fullPage: true, animations: 'disabled' })
  })
}
