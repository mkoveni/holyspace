import { defineConfig } from '@playwright/test'
export default defineConfig({
  testDir: './children-tests',
  workers: 1,
  timeout: 60_000,
  use: {
    actionTimeout: 12_000,
    baseURL: process.env.CHILDREN_E2E_BASE_URL || 'http://127.0.0.1:5175',
    viewport: { width: 1440, height: 1000 },
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
})
