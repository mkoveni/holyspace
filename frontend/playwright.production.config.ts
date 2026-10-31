import { defineConfig } from '@playwright/test'

export default defineConfig({
  testDir: './production-tests',
  workers: 1,
  timeout: 90_000,
  use: {
    baseURL: process.env.RELEASE_BROWSER_BASE_URL,
    viewport: { width: 1440, height: 1000 },
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
})
