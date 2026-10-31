import { expect, test } from '@playwright/test'
const resource = '11111111-1111-4111-8111-111111111111',
  child = '22222222-2222-4222-8222-222222222222'
for (const kind of ['arrival', 'collection'] as const) {
  test(`family ${kind} scanner validates its purpose and selected record before emitting`, async ({
    page,
  }) => {
    const errors: string[] = []
    page.on('pageerror', (error) => errors.push(error.message))
    await page.route('**/__family-qr-test', (route) =>
      route.fulfill({
        contentType: 'text/html',
        body: `<!doctype html><html><body><div id="app"></div><script type="module">
  import {createApp,h} from '/node_modules/.vite/deps/vue.js';
  import {createVuetify} from '/node_modules/.vite/deps/vuetify.js';
  import '/node_modules/vuetify/lib/styles/main.css';
  import FamilyQrScanner from '/src/children/components/FamilyQrScanner.vue';
  createApp({render:()=>h(FamilyQrScanner,{kind:'${kind}',resourceId:'${resource}',onScanned:reference=>{window.scanned=reference}})}).use(createVuetify()).mount('#app');
  </script></body></html>`,
      }),
    )
    await page.goto('/__family-qr-test')
    await page
      .getByRole('button', {
        name:
          kind === 'arrival'
            ? 'Scan family check-in reference'
            : 'Scan collection QR',
        exact: true,
      })
      .click()
    await page
      .getByLabel('Family QR reference', { exact: true })
      .fill(`holyspace:event:v1:wrong-type`)
    await page
      .getByRole('button', { name: 'Use reference', exact: true })
      .click()
    await expect(
      page.getByText(/Use a family QR for this selected session/),
    ).toBeVisible()
    expect(await page.evaluate(() => 'scanned' in window)).toBe(false)
    const value = kind === 'arrival' ? child : 'a'.repeat(22)
    await page
      .getByLabel('Family QR reference', { exact: true })
      .fill(`holyspace:child-${kind}:v1:${resource}:${value}`)
    await page
      .getByRole('button', { name: 'Use reference', exact: true })
      .click()
    await expect(page.getByRole('dialog')).toHaveCount(0)
    expect(
      await page.evaluate(
        () => (window as Window & { scanned: unknown }).scanned,
      ),
    ).toEqual(kind === 'arrival' ? { childId: child } : { pickupCode: value })
    expect(errors).toEqual([])
  })
}

test('collection scanner decodes a QR photo but waits for explicit use', async ({
  page,
}) => {
  await page.route('**/__family-photo-test', (route) =>
    route.fulfill({
      contentType: 'text/html',
      body: `<!doctype html><html><body><div id="app"></div><script type="module">
    import {createApp,h} from '/node_modules/.vite/deps/vue.js';
    import {createVuetify} from '/node_modules/.vite/deps/vuetify.js';
    import '/node_modules/vuetify/lib/styles/main.css';
    import FamilyQrScanner from '/src/children/components/FamilyQrScanner.vue';
    createApp({render:()=>h(FamilyQrScanner,{kind:'collection',resourceId:'${resource}',onScanned:reference=>{window.scanned=reference}})}).use(createVuetify()).mount('#app');
    </script></body></html>`,
    }),
  )
  await page.goto('/__family-photo-test')
  await page
    .getByRole('button', { name: 'Scan collection QR', exact: true })
    .click()
  const QRCode = await import('qrcode')
  const image = await QRCode.toBuffer(
    `holyspace:child-collection:v1:${resource}:${'a'.repeat(22)}`,
    { width: 500 },
  )
  await page
    .locator('input[type=file]')
    .setInputFiles({
      name: 'collection.png',
      mimeType: 'image/png',
      buffer: image,
    })
  await expect(
    page.getByLabel('Family QR reference', { exact: true }),
  ).not.toHaveValue('')
  expect(await page.evaluate(() => 'scanned' in window)).toBe(false)
  await page.getByRole('button', { name: 'Use reference', exact: true }).click()
  await expect(page.getByRole('dialog')).toHaveCount(0)
})
