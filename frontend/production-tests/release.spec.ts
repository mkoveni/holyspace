import { readFileSync } from 'node:fs'
import { expect, test } from '@playwright/test'

const baseURL = process.env.RELEASE_BROWSER_BASE_URL
const passwordFile = process.env.RELEASE_BROWSER_PASSWORD_FILE
if (!baseURL || new URL(baseURL).hostname !== '127.0.0.1' || !passwordFile) {
  throw new Error(
    'Production browser checks require a disposable loopback deployment and password file.',
  )
}

test('built Java workspace supports a private authenticated journey under its production CSP', async ({
  page,
  request,
}) => {
  const browserErrors: string[] = []
  page.on('pageerror', error => browserErrors.push(error.message))
  page.on('console', message => {
    if (
      message.type() === 'error'
      && /Content Security Policy|violates.*directive/i.test(message.text())
    ) {
      browserErrors.push(
        'Production content security policy blocked application content.',
      )
    }
  })
  await page.goto('/login')
  await page.getByPlaceholder('you@yourchurch.org').fill('release-verification')
  await page
    .getByPlaceholder('••••••••')
    .fill(readFileSync(passwordFile!, 'utf8').trim())
  await page.getByRole('button', { name: 'Sign In', exact: true }).click()
  await expect(
    page.getByRole('heading', { name: 'Every person. A story.' }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Add person', exact: true }).click()
  await page.getByLabel('First name *', { exact: true }).fill('Release')
  await page.getByLabel('Last name *', { exact: true }).fill('Fixture')
  await page.getByRole('button', { name: 'Create person', exact: true }).click()
  await expect(
    page.getByRole('heading', { name: 'Release Fixture', exact: true }),
  ).toBeVisible()
  await page.reload()
  await expect(
    page.getByRole('heading', { name: 'Release Fixture', exact: true }),
  ).toBeVisible()
  await page.setViewportSize({ width: 390, height: 844 })
  await expect.poll(() => page.evaluate(
    () => document.documentElement.scrollWidth <= innerWidth,
  )).toBe(true)
  const token = await page.evaluate(() =>
    localStorage.getItem('holyspace.java.auth.token'),
  )
  expect(token).toBeTruthy()
  await page.getByRole('button', { name: 'Account menu', exact: true }).click()
  await page.getByText('Sign out', { exact: true }).click()
  await expect(
    page.getByRole('heading', { name: 'Sign in to your account' }),
  ).toBeVisible()
  expect(
    await page.evaluate(() =>
      localStorage.getItem('holyspace.java.auth.token'),
    ),
  ).toBeNull()
  const response = await request.get('/api/me', {
    headers: { Authorization: `Bearer ${token}` },
  })
  expect(response.status()).toBe(401)
  expect(browserErrors).toEqual([])
})
