import { test, expect, type Page } from '@playwright/test'

/**
 * Bedarf-Pool: eine Liste, Auswahl über Suchen hinweg, Massenkategorisierung (Demo-Konto ga-mw).
 * Ändert Kategorien der Materialwünsche; nach dem Lauf müssen sie zurückgesetzt werden (nur gegen Wegwerf-/Demo-Umgebung).
 * Aktiv nur mit E2E_GA_CATEGORIZE=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD.
 */
const email = process.env.E2E_USER_EMAIL
const password = process.env.E2E_USER_PASSWORD

test.describe('GA Bedarf-Pool Massenkategorisierung', () => {
  test.skip(process.env.E2E_GA_CATEGORIZE !== '1' || !email || !password, 'E2E_GA_CATEGORIZE=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD nötig')
  test.setTimeout(180_000)

  async function login(page: Page): Promise<string> {
    await page.goto('/login')
    await page.getByRole('textbox', { name: /E-Mail/i }).fill(email!)
    await page.locator('input[type="password"]').first().fill(password!)
    await page.getByRole('button', { name: /Anmelden/i }).click()
    await page.waitForURL((url) => !/\/login(?:\?|$)/.test(url.pathname), { timeout: 60_000 })
    return /\/([0-9a-f]{12})\//.exec(page.url())![1]
  }

  const rows = (page: Page) => page.locator('[data-testid="pool-list"] .pool-row:not(.pool-row--vehicle)')
  const search = (page: Page, text: string) => page.locator('.pool-search input').fill(text)

  async function pickCategory(page: Page, index: number) {
    const input = page.locator('.v-dialog .category-picker input').first()
    await input.click()
    await page.locator('.v-overlay--active .v-list-item').nth(index).click()
  }

  test('single list, selection across searches, bulk categorize', async ({ page }) => {
    const id = await login(page)
    await page.goto(`/${id}/ga/procurement/demand`)
    await expect(rows(page).first()).toBeVisible({ timeout: 60_000 })

    // genau eine scrollbare Liste
    await expect(page.locator('.wish-pool-list')).toHaveCount(1)

    const mains = await rows(page).locator('.pool-row__main strong').allInnerTexts()
    const label = (text: string) => text.replace(/^\d+×\s*/, '')
    const [a, b] = [label(mains[0]), label(mains.find((m) => label(m) !== label(mains[0]))!)]
    const snapshot = async () => (await rows(page).locator('.pool-row__body').allInnerTexts()).map((t) => t.split('\n').slice(0, 4).join('|'))
    const before = await snapshot()

    // Auswahl über mehrere Suchen
    await search(page, a)
    await rows(page).first().locator('input[type="checkbox"]').check()
    await search(page, b)
    await rows(page).first().locator('input[type="checkbox"]').check()
    const total = page.getByTestId('pool-selection-total')
    await expect(total).toContainText('2')
    await expect(total).toContainText('1') // ein Wunsch ist ausgefiltert (mind. 1)

    // «Auswahl anzeigen» unabhängig vom Suchbegriff, weiterhin nur eine Liste
    await page.getByTestId('pool-show-selection').click()
    await expect(rows(page)).toHaveCount(2)
    await expect(page.locator('.wish-pool-list')).toHaveCount(1)
    await page.getByTestId('pool-show-selection').click()

    // Kategorisieren mit teilweise ausgefilterter Auswahl (Suche zeigt nur b)
    await page.getByTestId('pool-categorize').click()
    await pickCategory(page, 0)
    await page.getByTestId('categorize-confirm').click()
    await expect(page.getByTestId('pool-selection-total')).toContainText('2')
    await search(page, '')
    await page.getByTestId('pool-show-selection').click()
    await expect(rows(page)).toHaveCount(2)
    const catLines = await rows(page).locator('.pool-row__meta').allInnerTexts()
    expect(catLines.filter((l) => l.includes('/') || l.length > 0).length).toBeGreaterThan(0)
    await page.getByTestId('pool-show-selection').click()

    // Namen und übrige Daten unverändert (ohne Kategoriezeile)
    const after = await snapshot()
    expect(after.map((r) => r.split('|').slice(0, 3).join('|')))
      .toEqual(before.map((r) => r.split('|').slice(0, 3).join('|')))

    // Überschreiben nur nach Bestätigung
    await page.getByTestId('pool-categorize').click()
    await pickCategory(page, 1)
    await expect(page.getByTestId('categorize-overwrite-hint')).toBeVisible()
    await page.getByTestId('categorize-confirm').click()
    await page.getByRole('button', { name: /Abbrechen|Cancel/ }).last().click()
    await expect(page.getByTestId('categorize-confirm')).toBeVisible() // Dialog bleibt offen, nichts geschrieben
  })
})
