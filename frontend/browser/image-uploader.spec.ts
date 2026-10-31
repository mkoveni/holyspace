import { expect, test } from '@playwright/test'

for (const size of [{ width: 250, height: 250 }, { width: 1200, height: 300 }]) {
  test(`crop editor exports ${size.width}×${size.height} pixels and retains edits after a failed upload`, async ({ page }) => {
    await page.route('**/__image-uploader-test', route => route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><div id="app"></div><script type="module">
      import { createApp, h } from '/node_modules/.vite/deps/vue.js';
      import { createVuetify } from '/node_modules/.vite/deps/vuetify.js';
      import '/node_modules/vuetify/lib/styles/main.css';
      import ImageUploader from '/src/components/common/ImageUploader.vue';
      let attempts = 0;
      createApp({ render: () => h(ImageUploader, { width: ${size.width}, height: ${size.height}, label: 'Test image',
        upload: async file => { if (++attempts === 1) throw new Error('Upload unavailable. Try again.'); const bitmap = await createImageBitmap(file); window.exported = { width: bitmap.width, height: bitmap.height, type: file.type }; bitmap.close(); }
      }) }).use(createVuetify()).mount('#app');
      </script></body></html>` }))
    if (size.width === 250) await page.setViewportSize({ width: 390, height: 844 })
    await page.goto('/__image-uploader-test')
    await expect(page.getByRole('button', { name: 'Choose an image' })).toBeVisible()
    const data = await page.evaluate(() => {
      const canvas = document.createElement('canvas')
      canvas.width = 1600
      canvas.height = 900
      const context = canvas.getContext('2d')!
      context.fillStyle = '#1e3a5f'
      context.fillRect(0, 0, 800, 900)
      context.fillStyle = '#e3b77a'
      context.fillRect(800, 0, 800, 900)
      return canvas.toDataURL('image/png').split(',')[1]!
    })
    await page.locator('input[type=file]').setInputFiles({ name: 'landscape.png', mimeType: 'image/png', buffer: Buffer.from(data, 'base64') })
    await expect(page.locator('canvas')).toBeVisible()
    await page.getByRole('slider', { name: 'Zoom' }).press('ArrowRight')
    await page.getByRole('slider', { name: 'Zoom' }).press('End')
    const bounds = await page.locator('canvas').boundingBox()
    if (!bounds) throw new Error('Crop preview is unavailable')
    await page.mouse.move(bounds.x + bounds.width / 2, bounds.y + bounds.height / 2)
    await page.mouse.down()
    await page.mouse.move(bounds.x + bounds.width / 4, bounds.y + bounds.height / 2)
    await page.mouse.up()
    await expect(page.getByRole('slider', { name: 'Horizontal position' })).not.toHaveAttribute('aria-valuenow', '50')
    await page.getByRole('button', { name: 'Reset crop' }).click()
    await page.getByRole('button', { name: 'Save image' }).click()
    await expect(page.getByRole('alert')).toContainText('Upload unavailable. Try again.')
    await expect(page.locator('canvas')).toBeVisible()
    await page.screenshot({ path: `test-results/image-uploader-${size.width}.png`, fullPage: true })
    await page.getByRole('button', { name: 'Save image' }).click()
    await expect.poll(() => page.evaluate(() => (window as Window & { exported?: unknown }).exported)).toEqual({ ...size, type: 'image/png' })
    await expect(page.locator('canvas')).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Choose an image' })).toBeVisible()
  })
}
