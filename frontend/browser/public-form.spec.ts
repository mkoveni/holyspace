import { expect, test } from '@playwright/test'

for (const kind of ['event-desktop', 'event-mobile', 'general-mobile']) {
  test(`public form has a polished anonymous experience for ${kind}`, async ({ page }) => {
    const eventForm = kind.startsWith('event')
    await page.setViewportSize({ width: kind.endsWith('desktop') ? 1440 : 390, height: 1000 })
    const token = 'a'.repeat(64)
    const submissions: Record<string, unknown>[] = []
    await page.route('**/api/public/forms/**', async route => {
      expect(route.request().headers().authorization).toBeUndefined()
      const path = new URL(route.request().url()).pathname
      if (path.endsWith('/event-banner')) {
        await route.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="300"><defs><linearGradient id="g"><stop stop-color="#102d43"/><stop offset="1" stop-color="#65958a"/></linearGradient></defs><path fill="url(#g)" d="M0 0h1200v300H0z"/><circle cx="980" cy="95" r="58" fill="#f1d6a3"/><path d="M0 260Q250 100 550 250T1200 210V300H0" fill="#254f55"/></svg>' })
        return
      }
      if (path.endsWith('/banner')) { await route.fulfill({ status: 404 }); return }
      if (route.request().method() === 'POST') {
        submissions.push(route.request().postDataJSON())
        await route.fulfill({ status: submissions.length === 1 ? 503 : 202, json: { message: 'Please try again in a moment.' } })
        return
      }
      await route.fulfill({ json: {
        title: eventForm ? 'Join us for a community celebration' : 'Welcome to our community',
        description: 'We would love to get to know you. Share a few details with our team.',
        version: 2, challenge: 'signed-challenge', isEventRegistration: eventForm,
        fields: [{ key: 'name', label: 'Full name', type: 'text', required: true }, { key: 'email', label: 'Email address', type: 'email', required: true }, { key: 'note', label: 'Anything we should know?', type: 'textarea', required: false }],
        event: eventForm ? { title: 'A Sunday together', description: 'Gather for a morning of worship, warm conversation and new connections. Everyone is welcome.', startsAt: '2030-01-06T09:00:00Z', endsAt: '2030-01-06T11:00:00Z', timezone: 'Africa/Johannesburg', location: 'Community Hall, 24 Jacaranda Avenue', status: 'scheduled' } : null,
      } })
    })
    await page.goto(`/forms/public/${token}`)
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(eventForm ? 'A Sunday together' : 'Welcome to our community')
    if (eventForm) {
      await expect(page.getByRole('img', { name: 'A Sunday together banner' })).toBeVisible()
      await expect(page.getByText('Africa/Johannesburg')).toBeVisible()
      await expect(page.getByText('Community Hall, 24 Jacaranda Avenue')).toBeVisible()
      await expect(page.getByText('Submitting this form does not reserve or confirm a place.')).toBeVisible()
    } else await expect(page.getByRole('complementary', { name: 'Event details' })).toHaveCount(0)
    const submit = page.getByRole('button', { name: eventForm ? 'Send registration request' : 'Send response' })
    await expect(submit).toBeEnabled()
    await submit.click()
    await expect(page.getByText('This field is required.').first()).toBeVisible()
    await page.getByLabel('Full name').fill('Naledi Mokoena')
    await page.getByLabel('Email address').fill('naledi@example.org')
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    await page.screenshot({ path: `test-results/public-form-${kind}.png`, fullPage: true, animations: 'disabled' })
    await submit.click()
    await expect(page.getByRole('alert').filter({ hasText: 'Please try again in a moment.' })).toBeVisible()
    await expect(page.getByLabel('Full name')).toHaveValue('Naledi Mokoena')
    await submit.click()
    await expect(page.getByRole('heading', { name: eventForm ? 'Your request is with the team' : 'Thank you for reaching out' })).toBeVisible()
    expect(submissions).toHaveLength(2)
    expect(submissions[0]!.submissionKey).toBe(submissions[1]!.submissionKey)
    if (eventForm) await expect(page.getByText('Your place is confirmed only after the church approves your request.')).toBeVisible()
  })
}
