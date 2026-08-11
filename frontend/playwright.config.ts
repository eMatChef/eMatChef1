import { defineConfig, devices } from '@playwright/test'

/**
 * Smoke-E2E gegen eine laufende Umgebung (typisch app-dev).
 * Secrets/ENV: siehe docs/E2E.md
 */
const baseURL = process.env.E2E_BASE_URL || 'https://app.dev.ematchef.ch'

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  timeout: 60_000,
  expect: { timeout: 15_000 },
  reporter: process.env.CI ? [['github'], ['list']] : 'list',
  use: {
    baseURL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    ...devices['Desktop Chrome'],
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
