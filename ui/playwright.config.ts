import { defineConfig } from '@playwright/test'

export default defineConfig({
  testDir: './tests/browser',
  fullyParallel: false,
  workers: 1,
  timeout: 90_000,
  expect: { timeout: 15_000 },
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: 'http://127.0.0.1:8099',
    viewport: { width: 1440, height: 1000 },
    actionTimeout: 15_000,
    trace: 'on',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    launchOptions: process.env.BROWSER_EXECUTABLE_PATH
      ? { executablePath: process.env.BROWSER_EXECUTABLE_PATH }
      : {},
  },
  webServer: {
    command: 'node scripts/browser-host.mjs --reset',
    url: 'http://127.0.0.1:8099/testing/health',
    reuseExistingServer: false,
    timeout: 30_000,
  },
})
