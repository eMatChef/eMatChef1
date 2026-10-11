import { test, expect, type Page } from '@playwright/test'

/**
 * Demo «Grossanlass Event 2027» gegen eine ISOLIERTE, mit `app:demo:sync --all` befüllte Umgebung (nie gegen Develop-Daten,
 * die jemand von Hand verändert hat). Aktiv nur mit E2E_GA_DEMO=1 und dem Demo-Konto ga-mw (Passwort siehe demo-accounts.json).
 * Prüft den Anlasszeitraum, die Schnellwahl der Uhr, die Budget-Ansicht samt Navigation und dass ein Zeitsprung nichts verändert.
 */
const email = process.env.E2E_USER_EMAIL
const password = process.env.E2E_USER_PASSWORD

test.describe('GA Event Demo', () => {
  test.skip(process.env.E2E_GA_DEMO !== '1' || !email || !password, 'E2E_GA_DEMO=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD (Demo-Konto ga-mw) nötig')
  test.setTimeout(180_000)

  async function login(page: Page): Promise<string> {
    await page.goto('/login')
    await page.getByRole('textbox', { name: /E-Mail/i }).fill(email!)
    await page.locator('input[type="password"]').first().fill(password!)
    await page.getByRole('button', { name: /Anmelden/i }).click()
    await page.waitForURL((url) => !/\/login(?:\?|$)/.test(url.pathname), { timeout: 60_000 })
    const match = /\/([0-9a-f]{12})\//.exec(page.url())
    expect(match, 'Department-ID in der URL').not.toBeNull()
    return match![1]
  }

  test('event period, quick clock selection, budget and navigation', async ({ page }) => {
    const id = await login(page)

    // Anlasszeitraum (Aufbaubeginn bis Abbauende) und Demo-Uhr am Ausgangspunkt 04.04.2027
    await expect(page.getByText('19.05.–23.06.27')).toBeVisible({ timeout: 60_000 })
    const clock = page.getByTestId('clock-control')
    // Eine von einem früheren Lauf verstellte Uhr bleibt bewusst bestehen (Seed-Sync setzt sie nie zurück): zuerst zurücksetzen
    await expect(clock).toBeVisible({ timeout: 60_000 })
    if (!(await clock.innerText()).includes('04.04.2027 16:')) {
      await clock.click()
      await page.getByRole('button', { name: 'Zurücksetzen' }).click()
      await page.keyboard.press('Escape')
    }
    await expect(clock).toContainText('04.04.2027 16:', { timeout: 30_000 })

    // Stammdaten: Datum und Uhrzeit des Anlasses, ohne Zeitverschiebung
    await page.goto(`/${id}/ga/activity-settings/general`)
    await expect(page.getByLabel('Beginn (Uhrzeit)')).toHaveValue('13:00', { timeout: 60_000 })
    await expect(page.getByLabel('Ende (Uhrzeit)')).toHaveValue('12:00')

    // Navigation unter «Beschaffung»
    await page.goto(`/${id}/ga/procurement/inquiries`)
    for (const item of ['inquiries', 'commitments', 'orders', 'budget']) {
      await expect(page.getByTestId(`nav-beschaffung-${item}`)).toBeVisible({ timeout: 60_000 })
    }
    await page.getByTestId('nav-beschaffung-budget').click()
    await expect(page).toHaveURL(/\/ga\/procurement\/budget$/)
    await page.mouse.move(900, 500) // die Seitenleiste klappt bei Hover auf und würde die Reiter verdecken

    // Budget Gesamt: Limits 2000 + 900 = 2900, getrennte Reiter Material und Logistik
    await expect(page.getByTestId('budget-planned')).toContainText(/2.?900\.00/, { timeout: 60_000 })
    const before = await page.getByTestId('budget-ordered').textContent()
    const actualBefore = await page.getByTestId('budget-actual').textContent()
    await page.getByTestId('budget-tab-material').click()
    await expect(page.getByTestId('budget-planned')).toContainText(/2.?000\.00/)
    await expect(page.getByTestId('budget-over')).toHaveCount(0)
    await page.getByTestId('budget-tab-logistik').click()
    await expect(page.getByTestId('budget-planned')).toContainText(/900\.00/)
    // Der Teleskoplader (950) übersteigt das Logistik-Limit (900) um 50: Überschreitung sichtbar
    await expect(page.getByTestId('budget-over')).toContainText(/50\.00/)

    // Schnellwahl der Uhr: Aufbau; der Zeitsprung ändert keine Budgetzahlen
    await clock.click()
    await page.getByTestId('clock-preset-build').click()
    await expect(clock).toContainText('31.05.2027 14:', { timeout: 30_000 })
    await page.reload()
    await page.getByTestId('budget-tab-total').click()
    await expect(page.getByTestId('budget-ordered')).toHaveText(before!, { timeout: 60_000 })
    await expect(page.getByTestId('budget-actual')).toHaveText(actualBefore!)

    // Zurück zum Ausgangspunkt (Header-Zurücksetzen)
    await clock.click()
    await page.getByRole('button', { name: 'Zurücksetzen' }).click()
    await expect(clock).toContainText('04.04.2027 16:', { timeout: 30_000 })
  })
})
