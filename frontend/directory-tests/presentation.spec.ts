import { expect, test } from '@playwright/test'
import type { PersonProfile } from '../src/directory/types'
const person: PersonProfile = {
  id: 'person-1', details: { titleCode: 'DR', firstName: 'Naledi', lastName: 'Mokoena', dateOfBirth: '1989-04-12', gender: 'FEMALE', lifeStatus: 'LIVING', dateOfDeath: null },
  membershipType: 'ACTIVE_MEMBER', ageCategory: 'ADULT', ageAsOf: '2026-10-25', archivedAt: null, version: 3,
  contacts: [{ id: 'contact-1', personId: 'person-1', kind: 'EMAIL', value: 'naledi@example.org', primary: true, createdAt: '2026-01-01T12:00:00Z', endedAt: null }],
  addresses: [{ id: 'address-1', personId: 'person-1', line1: '24 Jacaranda Avenue', line2: '', city: 'Johannesburg', region: 'Gauteng', postalCode: '2196', countryCode: 'ZA', createdAt: '2026-01-01T12:00:00Z', endedAt: null }],
}
for (const mobile of [false, true]) {
  test(`read-only profile and directory are accessible at ${mobile ? 'mobile' : 'desktop'} width`, async ({ page }) => {
    await page.setViewportSize({ width: mobile ? 390 : 1440, height: 1000 })
    await page.addInitScript(() => localStorage.setItem('divinecommunityspace.java.auth.token', 'isolated-test-session'))
    const unexpected: string[] = []; const errors: string[] = []
    page.on('pageerror', error => errors.push(error.message))
    await page.route('**/api/**', async route => {
      const path = new URL(route.request().url()).pathname
      const data: Record<string, unknown> = {
        '/api/me': { id: 'reader', displayName: 'Community Reader', username: 'reader', status: 'active', permissions: ['people.read'], roles: [] },
        '/api/people/metadata': { titles: [{ code: 'DR', label: 'Dr' }], membershipTypes: [{ code: 'ACTIVE_MEMBER', label: 'Active Member' }, { code: 'REGULAR_VISITOR', label: 'Regular Visitor' }, { code: 'VISITOR', label: 'Visitor' }, { code: 'TRANSFERRED', label: 'Transferred' }], timeZone: 'Africa/Johannesburg', adultAge: 18 },
        '/api/people/person-1': person,
        '/api/people/person-1/relationships': [],
        '/api/people/person-1/households': [],
        '/api/people': [person],
      }
      if (!(path in data) || route.request().method() !== 'GET') { unexpected.push(path); await route.fulfill({ status: 500, json: { message: 'Unexpected request' } }); return }
      await route.fulfill({ json: data[path] })
    })
    await page.goto('/people/person-1')
    await expect(page.getByRole('heading', { name: 'Dr Naledi Mokoena' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'naledi@example.org' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Edit profile', exact: true })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Add contact', exact: true })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Archive person', exact: true })).toHaveCount(0)
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(0)
    await page.screenshot({ path: `test-results/java-profile-reader-${mobile ? 'mobile' : 'desktop'}.png`, fullPage: true, animations: 'disabled' })
    await page.goto('/people')
    await expect(page.getByRole('heading', { name: 'Every person. A story.' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'View Naledi Mokoena', exact: true })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Add person', exact: true })).toHaveCount(0)
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(0)
    await page.screenshot({ path: `test-results/java-directory-reader-${mobile ? 'mobile' : 'desktop'}.png`, fullPage: true, animations: 'disabled' })
    expect(unexpected).toEqual([]); expect(errors).toEqual([])
  })
}
