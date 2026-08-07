import { test, expect } from '@playwright/test'

/**
 * Smoke: Login-Seite erreichbar + optional Login mit Test-User.
 * Ohne E2E_USER_EMAIL/E2E_USER_PASSWORD läuft nur der öffentliche Teil.
 */
test.describe('Smoke', () => {
  test('login page loads', async ({ page }) => {
    await page.goto('/login')
    await expect(page.getByRole('heading', { name: 'eMatChef' })).toBeVisible()
    await expect(page.locator('#email, input[type="email"]').first()).toBeVisible()
    await expect(page.locator('#password, input[type="password"]').first()).toBeVisible()
  })

  test('login and reach authenticated shell', async ({ page }) => {
    const email = process.env.E2E_USER_EMAIL
    const password = process.env.E2E_USER_PASSWORD
    test.skip(!email || !password, 'E2E_USER_EMAIL / E2E_USER_PASSWORD nicht gesetzt')

    await page.goto('/login')
    await page.locator('#email, input[type="email"]').first().fill(email!)
    await page.locator('#password, input[type="password"]').first().fill(password!)
    await page.locator('form.login-form button[type="submit"], button.btn-submit').first().click()

    // Nach Login: weg von /login (Dashboard oder Dept-Home)
    await expect(page).not.toHaveURL(/\/login(?:\?|$)/, { timeout: 30_000 })
    await expect(page.locator('body')).not.toContainText(/Netzwerkfehler|Internal Server Error/i)
  })
})
