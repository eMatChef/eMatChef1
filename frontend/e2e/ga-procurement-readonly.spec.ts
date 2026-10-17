import { test, expect, type Page } from '@playwright/test'

/**
 * Bedarf-Pool, nur lesend (Demo-Konto ga-mw): Pool mit Zuständigkeits-Chips, Dialog «Kategorisieren» mit
 * Zuständigkeit (nur öffnen, nie bestätigen), Dialog «Grundeingabe bearbeiten», Vorschlags-Endpunkt (lesend).
 * Verändert keine Daten; geeignet auch gegen die lokale Entwicklungsumgebung (https://app.ematchef.test).
 * Aktiv mit E2E_GA_READONLY=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD.
 */
const email = process.env.E2E_USER_EMAIL
const password = process.env.E2E_USER_PASSWORD

test.describe('GA Bedarf-Pool (lesend)', () => {
  test.skip(process.env.E2E_GA_READONLY !== '1' || !email || !password, 'E2E_GA_READONLY=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD nötig')
  test.use({ ignoreHTTPSErrors: true })
  test.setTimeout(150_000)

  async function login(page: Page): Promise<string> {
    await page.goto('/login')
    await page.getByRole('textbox', { name: /E-Mail/i }).fill(email!)
    await page.locator('input[type="password"]').first().fill(password!)
    await page.getByRole('button', { name: /Anmelden/i }).click()
    await page.waitForURL((url) => !/\/login(?:\?|$)/.test(url.pathname), { timeout: 60_000 })
    return /\/([0-9a-f]{12})\//.exec(page.url())![1]
  }

  const rows = (page: Page) => page.locator('[data-testid="pool-list"] .pool-row:not(.pool-row--vehicle)')

  test('pool, responsibility, dialogs and suggestions endpoint', async ({ page }) => {
    const id = await login(page)
    await page.goto(`/${id}/ga/procurement/demand`)
    await expect(rows(page).first()).toBeVisible({ timeout: 60_000 })

    // Jeder Wunsch zeigt seine Zuständigkeit (Altbestand: «Noch nicht festgelegt»)
    await expect(rows(page).first().locator('[data-testid^="pool-responsibility-"]')).toBeVisible()
    await expect(page.locator('.wish-pool-list')).toHaveCount(1)

    // Lesende API: Overview und Vorschläge
    const overviewUrl = `/api/departments/${id}/grossanlass/beschaffung/bedarf`
    const overview = await (await page.request.get(overviewUrl)).json()
    expect(overview.pool.length).toBeGreaterThan(0)
    expect('procurement_responsibility' in overview.pool[0]).toBe(true)
    expect(Array.isArray(overview.department_wishes)).toBe(true)
    const res = await page.request.post(`${overviewUrl.replace(/bedarf$/, '')}wishes/similar-lines`, {
      data: { wish_line_ids: [overview.pool[0].id] },
    })
    expect(res.ok()).toBe(true)
    expect(Object.keys((await res.json()).matches)).toEqual([overview.pool[0].id])
    // Ressort-Direktpositionen werden nie vorgeschlagen
    const directIds = new Set(overview.lines.filter((l: { self_organized: boolean }) => l.self_organized).map((l: { id: string }) => l.id))
    const bulk = await page.request.post(`${overviewUrl.replace(/bedarf$/, '')}wishes/similar-lines`, {
      data: { wish_line_ids: overview.pool.slice(0, 20).map((w: { id: string }) => w.id) },
    })
    const suggested = Object.values((await bulk.json()).matches).flat() as Array<{ line_id: string }>
    for (const m of suggested) expect(directIds.has(m.line_id)).toBe(false)

    // «Kategorisieren» nur öffnen: Zuständigkeit ist wählbar, Standard «Unverändert»; nichts bestätigen
    await rows(page).first().locator('input[type="checkbox"]').check()
    await page.getByTestId('pool-categorize').click()
    const dialog = page.locator('.v-dialog', { hasText: 'Materialwünsche kategorisieren' })
    await expect(dialog.getByRole('combobox', { name: /Beschaffungszuständigkeit/ })).toHaveValue('Unverändert')
    await expect(page.getByTestId('categorize-confirm')).toBeDisabled()
    await dialog.getByRole('button', { name: 'Abbrechen' }).click()

    // Ohne zentrale Zuständigkeit keine Übernahme: Hinweis statt Dialog (auch über «Ähnliche Wünsche»)
    const suggestionBundle = page.getByRole('button', { name: 'Prüfen und bündeln' }).first()
    if (await suggestionBundle.isVisible().catch(() => false)) {
      await suggestionBundle.click()
      await expect(page.locator('.v-dialog', { hasText: 'Bündeln prüfen' })).toHaveCount(0)
    }
    await page.getByRole('button', { name: /^\d+ Wunsch.*bündeln$/ }).click()
    await expect(page.locator('.v-dialog', { hasText: 'Bündeln prüfen' })).toHaveCount(0)

    // «Grundeingabe bearbeiten» zeigt die Zuständigkeit
    await rows(page).first().getByTitle(/Bearbeiten/i).click()
    await expect(page.getByRole('combobox', { name: /Beschaffungszuständigkeit/ })).toBeVisible({ timeout: 60_000 })
  })
})
