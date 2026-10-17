import { defineConfig, devices } from '@playwright/test'

/**
 * Smoke-E2E gegen eine laufende Umgebung (typisch app-dev).
 * Secrets/ENV: siehe docs/E2E.md
 */
const baseURL = process.env.E2E_BASE_URL || 'https://dev.ematchef.ch'

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
    // Isolierte lokale Umgebung: Host-Namen auf 127.0.0.1 abbilden, z. B. «MAP *.ematchef.test 127.0.0.1» (siehe docs/E2E.md)
    launchOptions: { args: process.env.E2E_HOST_RESOLVER ? [`--host-resolver-rules=${process.env.E2E_HOST_RESOLVER}`] : [] },
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
