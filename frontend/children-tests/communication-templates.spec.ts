import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { ministryFixture } from './fixture'

async function templateFixture(page: Page, manage = true) {
  await ministryFixture(page, [
    'children.read',
    'communications.templates.read',
    ...(manage ? ['communications.templates.manage'] : []),
  ])
  let template = {
    id: 'template-1',
    key: 'children.consent-renewal',
    name: 'Children · Consent renewal',
    subject: 'A little preparation',
    html: '<h2>Hello</h2><p>{{message}}</p>',
    variables: ['message'],
    status: 'PUBLISHED',
    version: 0,
    publishedVersion: 0,
    updatedAt: new Date().toISOString(),
  }
  const revisions = [
    {
      version: 0,
      subject: template.subject,
      html: template.html,
      variables: ['message'],
      publishedAt: new Date().toISOString(),
    },
  ]
  const requests: { method: string; path: string; body: any }[] = []
  let conflict = false
  await page.route('**/api/communications/templates**', async (route) => {
    const request = route.request(),
      path = new URL(request.url()).pathname,
      body = request.postDataJSON()
    requests.push({ method: request.method(), path, body })
    if (path.endsWith('/preview')) {
      return route.fulfill({
        json: {
          subject: body.subject,
          html: `<h2>Hello</h2><p>${String(body.values.message || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')}</p>`,
        },
      })
    }
    if (path.endsWith('/revisions')) return route.fulfill({ json: revisions })
    if (path.endsWith('/publish')) {
      template = {
        ...template,
        status: 'PUBLISHED',
        version: template.version + 1,
        publishedVersion: template.version + 1,
      }
      revisions.unshift({
        version: template.version,
        subject: template.subject,
        html: template.html,
        variables: template.variables,
        publishedAt: new Date().toISOString(),
      })
      return route.fulfill({ json: { id: template.id } })
    }
    if (path.endsWith('/archive')) {
      template = {
        ...template,
        status: 'ARCHIVED',
        version: template.version + 1,
      }
      return route.fulfill({ json: { id: template.id } })
    }
    if (request.method() === 'PUT') {
      if (conflict)
        return route.fulfill({
          status: 409,
          json: { message: 'This record changed. Refresh before saving.' },
        })
      template = {
        ...template,
        ...body,
        version: template.version + 1,
        status: 'DRAFT',
      }
      return route.fulfill({ json: { id: template.id } })
    }
    if (path.endsWith('/template-1')) return route.fulfill({ json: template })
    return route.fulfill({ json: { templates: [template], total: 1 } })
  })
  return {
    requests,
    setConflict: () => {
      conflict = true
    },
  }
}

test('rich text edits, variables, preview and publication form one coherent flow', async ({
  page,
}) => {
  const fixture = await templateFixture(page)
  await page.goto('/communications/templates')
  await expect(
    page.getByRole('heading', { name: 'Thoughtful messages, every time.' }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Open editor' }).click()
  const dialog = page.getByRole('dialog').first()
  const body = page.getByRole('textbox', { name: 'Email body', exact: true })
  await expect(body).toContainText('{{message}}')
  await body.click()
  await page.keyboard.press('ControlOrMeta+End')
  await page.getByRole('button', { name: 'Bold', exact: true }).click()
  await page.keyboard.type(' Thank you for being here.')
  await page.getByRole('button', { name: 'Bold', exact: true }).click()
  await page
    .getByRole('button', { name: 'Insert variable', exact: true })
    .click()
  await page
    .getByRole('listitem')
    .filter({ hasText: /^message$/ })
    .click()
  await expect(body).toContainText('{{message}}')
  await page
    .getByRole('textbox', { name: 'Sample message', exact: true })
    .fill('<img src=x onerror=alert(1)> Hello Alex')
  await page.getByRole('button', { name: 'Update preview' }).click()
  const preview = page.frameLocator('iframe[title="Email preview"]')
  await expect(preview.locator('body')).toContainText(
    '<img src=x onerror=alert(1)> Hello Alex',
  )
  await expect(preview.locator('img')).toHaveCount(0)
  await page.getByRole('button', { name: 'Save draft', exact: true }).click()
  await expect(
    page.getByText('Your previous published revision is still in use.'),
  ).toBeVisible()
  expect(fixture.requests.find((r) => r.method === 'PUT')?.body.html).toContain(
    '<strong>',
  )
  await page
    .getByRole('button', { name: 'Publish revision', exact: true })
    .click()
  await page
    .getByRole('button', { name: 'Publish template', exact: true })
    .click()
  await expect(
    page.getByText('Your revision is published and ready for new messages.'),
  ).toBeVisible()
  await expect(
    dialog.getByText('Published revisions', { exact: true }),
  ).toBeVisible()
  await page.screenshot({
    path: 'test-results/templates-editor-desktop.png',
    fullPage: true,
  })
})

test('unsaved changes require an explicit discard and stale edits retain the draft', async ({
  page,
}) => {
  const fixture = await templateFixture(page)
  await page.goto('/communications/templates?template=template-1')
  await page
    .getByRole('textbox', { name: 'Template name', exact: true })
    .fill('A better name')
  await page.getByRole('button', { name: 'Close template editor' }).click()
  await expect(page.getByText('Keep your changes?')).toBeVisible()
  await page.getByRole('button', { name: 'Keep editing' }).click()
  fixture.setConflict()
  await page.getByRole('button', { name: 'Save draft', exact: true }).click()
  await expect(
    page.getByText('This record changed. Refresh before saving.'),
  ).toBeVisible()
  await expect(
    page.getByRole('textbox', { name: 'Template name', exact: true }),
  ).toHaveValue('A better name')
  await page.getByRole('button', { name: 'Close template editor' }).click()
  await page.getByRole('button', { name: 'Discard changes' }).click()
  await expect(page.getByRole('dialog')).toHaveCount(0)
})

test('read permission gives preview and revision history without editing controls', async ({
  page,
}) => {
  await templateFixture(page, false)
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/communications/templates')
  await expect(
    page.getByRole('button', { name: 'New email template' }),
  ).toHaveCount(0)
  await page.getByRole('button', { name: 'View template' }).click()
  await expect(
    page.getByRole('textbox', { name: 'Template name', exact: true }),
  ).toBeDisabled()
  await expect(
    page.getByRole('button', { name: 'Save draft', exact: true }),
  ).toHaveCount(0)
  await page.getByRole('button', { name: 'Update preview' }).click()
  await expect(page.locator('iframe[title="Email preview"]')).toBeVisible()
  await page.getByRole('button', { name: 'View wording' }).click()
  await expect(
    page.locator('iframe[title="Published template revision"]'),
  ).toBeVisible()
  await page.screenshot({
    path: 'test-results/templates-editor-mobile.png',
    fullPage: true,
  })
})
