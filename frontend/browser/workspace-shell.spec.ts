import { expect, type Page, test } from '@playwright/test'

const permissions = [
  'people.read', 'people.manage', 'families.read', 'life_events.read', 'giving.read', 'giving.manage',
  'events.read', 'events.manage', 'volunteering.read', 'identity.manage', 'followups.work', 'ministries.read',
  'reports.read', 'forms.read', 'communications.manage', 'communications.templates.read', 'children.read',
]
const people = [
  { id: 'p1', details: { titleCode: null, firstName: 'Naledi', lastName: 'Mokoena', dateOfBirth: '1989-04-12', gender: 'FEMALE', lifeStatus: 'LIVING', dateOfDeath: null }, membershipType: 'MEMBER', ageCategory: 'ADULT', ageAsOf: '2026-11-13', archivedAt: null, version: 1 },
  { id: 'p2', details: { titleCode: null, firstName: 'Thabo', lastName: 'Dlamini', dateOfBirth: '2015-01-02', gender: 'MALE', lifeStatus: 'LIVING', dateOfDeath: null }, membershipType: 'VISITOR', ageCategory: 'CHILD', ageAsOf: '2026-11-13', archivedAt: null, version: 1 },
]
const roles = [
  { id: 'r1', name: 'administrator', description: 'Full access', permissions: ['identity.manage', 'people.manage', 'giving.manage', 'events.manage', 'reports.read', 'forms.read'] },
  { id: 'r2', name: 'greeter', description: '', permissions: [] },
]
const permissionCatalogue = ['identity.manage', 'people.manage', 'giving.manage', 'events.manage', 'reports.read', 'forms.read', 'people.read'].map((code, index) => ({ id: `perm-${index}`, code, description: '' }))
const accounts = [
  { id: 'u1', username: 'admin', displayName: 'Ada Admin', email: 'ada@example.org', personId: null, status: 'active', roles: ['administrator'], createdAt: '2025-01-01T10:00:00Z', updatedAt: '2025-01-01T10:00:00Z' },
  { id: 'u2', username: 'volunteer', displayName: 'Vic Volunteer', email: 'vic@example.org', personId: null, status: 'active', roles: [], createdAt: '2025-01-02T10:00:00Z', updatedAt: '2025-01-02T10:00:00Z' },
]

const shots = process.env.SHOT_DIR ?? 'test-results'

async function openApp (page: Page, path: string, width = 1440) {
  await page.setViewportSize({ width, height: 900 })
  await page.addInitScript(() => localStorage.setItem('divinecommunityspace.java.auth.token', 'shell-test-session'))
  await page.route('**/api/**', async route => {
    const url = new URL(route.request().url())
    const responses: Record<string, unknown> = {
      '/api/me': { id: 'u1', username: 'admin', displayName: 'Ada Admin', status: 'active', roles: ['administrator'], permissions, mfaEnabled: true, mfaVerified: true },
      '/api/people': people,
      '/api/people/metadata': { titles: [], membershipTypes: [{ code: 'MEMBER', label: 'Member' }, { code: 'VISITOR', label: 'Visitor' }] },
      '/api/roles': roles,
      '/api/permissions': permissionCatalogue,
      '/api/users': { items: accounts },
      '/api/users/u1/permissions': ['identity.manage', 'people.manage'],
      '/api/users/u1': accounts[0],
      '/api/followups': { items: [{ id: 'f1', title: 'Call back', personName: 'Naledi', status: 'open', dueOn: '2026-01-01', overdue: true }] },
    }
    await route.fulfill({ json: responses[url.pathname] ?? [] })
  })
  await page.goto(path)
  await expect(page.getByRole('navigation', { name: 'Main navigation' })).toBeVisible()
}

test('sidebar groups pages into areas and shows section tabs', async ({ page }) => {
  await openApp(page, '/giving')
  const nav = page.getByRole('navigation', { name: 'Main navigation' })
  await expect(nav.getByRole('link')).toHaveCount(7)
  await expect(page.getByRole('navigation', { name: 'Giving sections' }).first()).toBeVisible()
  await page.screenshot({ path: `${shots}/shell-giving.png`, animations: 'disabled' })
})

test('command palette opens with Ctrl+K and navigates', async ({ page }) => {
  await openApp(page, '/')
  await page.keyboard.press('Control+k')
  const search = page.getByRole('combobox', { name: /search pages/i })
  await expect(search).toBeFocused()
  await search.fill('nal')
  await expect(page.getByRole('option', { name: /Naledi Mokoena/ })).toBeVisible()
  await page.keyboard.press('Enter')
  await expect(page).toHaveURL(/\/people\/p1$/)
})

test('the attention bell lists overdue follow-ups', async ({ page }) => {
  await openApp(page, '/people')
  await page.getByRole('button', { name: 'Things that need your attention' }).click()
  await expect(page.getByText('1 overdue follow-up')).toBeVisible()
})

test('people list selects, exports and saves views', async ({ page }) => {
  await openApp(page, '/people')
  await expect(page.getByText('Naledi Mokoena')).toBeVisible()
  await page.getByLabel('Search people').fill('thabo')
  await page.getByRole('button', { name: 'Saved views' }).click()
  await page.getByLabel('Name this view').fill('Thabo only')
  await page.getByRole('button', { name: 'Save', exact: true }).click()
  await page.keyboard.press('Escape')
  await page.getByLabel('Search people').clear()
  await page.getByLabel('Select Naledi Mokoena').check()
  await expect(page.getByText('1 selected')).toBeVisible()
  const download = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Export selected' }).click()
  expect((await download).suggestedFilename()).toBe('people.csv')
  await page.reload()
  await page.getByRole('button', { name: 'Saved views' }).click()
  await expect(page.getByText('Thabo only')).toBeVisible()
  await page.screenshot({ path: `${shots}/shell-people.png`, animations: 'disabled' })
})

test('roles show their permissions and users show their roles', async ({ page }) => {
  await openApp(page, '/roles')
  await expect(page.getByRole('row', { name: /administrator/ }).getByText('identity.manage')).toBeVisible()
  await expect(page.getByRole('row', { name: /administrator/ }).getByText('+2 more')).toBeVisible()
  await page.getByRole('row', { name: /greeter/ }).click()
  await expect(page.getByText('This role does not grant anything yet')).toBeVisible()
  await page.screenshot({ path: `${shots}/shell-roles.png`, animations: 'disabled' })
  await page.goto('/user-accounts')
  await expect(page.getByRole('row', { name: /Ada Admin/ }).getByText('administrator')).toBeVisible()
  await expect(page.getByRole('row', { name: /Vic Volunteer/ }).getByText('No roles')).toBeVisible()
})

test('form dialogs warn before discarding edits', async ({ page }) => {
  await openApp(page, '/roles')
  await page.getByRole('button', { name: 'Create Role' }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Role name').fill('Usher')
  await dialog.getByRole('button', { name: 'Cancel' }).click()
  await expect(page.getByText('Discard your changes?')).toBeVisible()
  await page.getByRole('button', { name: 'Keep editing' }).click()
  await expect(dialog.getByLabel('Role name')).toHaveValue('Usher')
  await dialog.getByRole('button', { name: 'Cancel' }).click()
  await page.getByRole('button', { name: 'Discard' }).click()
  await expect(page.locator('.form-dialog')).toBeHidden()
})

test('dark theme applies and phones get the compact layout', async ({ page }) => {
  await openApp(page, '/people')
  await page.getByRole('button', { name: 'Switch to dark theme' }).click()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
  await page.screenshot({ path: `${shots}/shell-dark.png`, animations: 'disabled' })
  await page.setViewportSize({ width: 390, height: 800 })
  await page.screenshot({ path: `${shots}/shell-mobile.png`, animations: 'disabled' })
  const overflowing = await page.evaluate(() => [...document.querySelectorAll('.workspace-content *')]
    .filter(element => !element.closest('.v-slide-group, .directory-table-scroll') && element.getBoundingClientRect().right > window.innerWidth + 1)
    .slice(0, 5)
    .map(element => `${element.tagName}.${String(element.className).slice(0, 60)}`))
  expect(overflowing).toEqual([])
})
