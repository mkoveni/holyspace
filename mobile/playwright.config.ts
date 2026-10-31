import { defineConfig } from "@playwright/test";
export default defineConfig({
  testDir: "./e2e",
  workers: 1,
  timeout: 60000,
  use: {
    baseURL: "http://127.0.0.1:8081",
    viewport: { width: 390, height: 844 },
    actionTimeout: 12000,
    navigationTimeout: 60000,
    screenshot: "only-on-failure",
    trace: "retain-on-failure",
  },
});
