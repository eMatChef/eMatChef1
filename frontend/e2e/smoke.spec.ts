import { test, expect } from '@playwright/test'

/**
 * Smoke: Login-Seite erreichbar + optional Login mit Test-User.
 * Ohne E2E_USER_EMAIL/E2E_USER_PASSWORD läuft nur der öffentliche Teil.
 * User anlegen: `php bin/console app:ensure-e2e-user` — siehe docs/E2E.md
 */
test.describe('Smoke', () => {
  test('login page loads', async ({ page }) => {
    await page.goto('/login')
    await expect(page.getByRole('heading', { name: 'eMatChef' })).toBeVisible()
    await expect(page.getByRole('textbox', { name: /E-Mail/i })).toBeVisible()
    await expect(page.locator('input[type="password"]').first()).toBeVisible()
  })

  test('login and reach authenticated shell', async ({ page }) => {
    const email = process.env.E2E_USER_EMAIL
    const password = process.env.E2E_USER_PASSWORD
    test.skip(!email || !password, 'E2E_USER_EMAIL / E2E_USER_PASSWORD nicht gesetzt')

    await page.goto('/login')
    await page.getByRole('textbox', { name: /E-Mail/i }).fill(email!)
    await page.locator('input[type="password"]').first().fill(password!)
    await page.getByRole('button', { name: /Anmelden/i }).click()

    const invalid = page.getByRole('alert').filter({ hasText: /Invalid credentials|ungültig|falsch/i })
    await Promise.race([
      page.waitForURL((url) => !/\/login(?:\?|$)/.test(url.pathname + url.search), { timeout: 30_000 }),
      invalid.waitFor({ state: 'visible', timeout: 30_000 }).then(async () => {
        throw new Error(
          `Login fehlgeschlagen (Invalid credentials) für ${email}. ` +
            'Auf Develop: php bin/console app:ensure-e2e-user --password=… und GitHub-Secrets prüfen.'
        )
      }),
    ])

    await expect(page).not.toHaveURL(/\/login(?:\?|$)/)
    await expect(page.locator('body')).not.toContainText(/Netzwerkfehler|Internal Server Error/i)
  })
})
