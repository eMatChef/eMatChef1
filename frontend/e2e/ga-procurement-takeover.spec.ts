import { test, expect, type Page } from '@playwright/test'

/**
 * Bedarf-Pool: Kategorisieren mit Beschaffungszuständigkeit, Vorschlag ähnlicher Positionen,
 * Zuordnung zu bestehender Position und Übernahme in eine neue Position (Demo-Konto ga-mw).
 * SCHREIBT (Zuständigkeit, Kategorie, Positionen) — nur gegen die isolierte Wegwerf-Umgebung (docs/E2E.md), nie mvdb.
 * Aktiv nur mit E2E_GA_TAKEOVER=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD.
 * Erwartet die Demo-Wünsche «Crowd-Barrier 1.2 m» (ähnlich zur im Test angelegten Position «Crowd Barrier 1.2 m»), «Nägel» (kein Treffer)
 * und «Erdungsset Bau-NEA» (Ausgangswunsch der Position).
 */
const email = process.env.E2E_USER_EMAIL
const password = process.env.E2E_USER_PASSWORD
const MATCHING_WISH = 'Crowd-Barrier 1.2 m'
const OTHER_WISH = 'Nägel'
const SETUP_WISH = 'Erdungsset Bau-NEA'
const EXISTING_LINE = 'Crowd Barrier 1.2 m'

test.describe('GA Bedarf-Pool Übernahme', () => {
  test.skip(process.env.E2E_GA_TAKEOVER !== '1' || !email || !password, 'E2E_GA_TAKEOVER=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD nötig')
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
  const search = (page: Page, text: string) => page.locator('.pool-search input').fill(text)

  async function selectWish(page: Page, label: string) {
    await search(page, label)
    const row = rows(page).filter({ has: page.locator('.pool-row__main strong', { hasText: new RegExp(`^\\d+×\\s*${label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`) }) })
    await expect(row).toHaveCount(1)
    await row.locator('input[type="checkbox"]').check()
  }

  async function pickCategory(page: Page) {
    const input = page.locator('.v-dialog .category-picker input').first()
    await input.click()
    await page.locator('.v-overlay--active .v-list-item').first().click()
  }

  test('categorize central, assign to similar position, create new position', async ({ page }) => {
    const id = await login(page)
    await page.goto(`/${id}/ga/procurement/demand`)
    await expect(rows(page).first()).toBeVisible({ timeout: 60_000 })

    const overviewUrl = `/api/departments/${id}/grossanlass/beschaffung/bedarf`
    const before = await (await page.request.get(overviewUrl)).json()
    const originalOf = (label: string) => before.pool.find((w: { label: string }) => w.label === label)
    // Ausgangslage: eine zentrale Position mit ähnlichem Namen (aus einem dritten Wunsch)
    const setup = originalOf(SETUP_WISH)
    expect(setup, 'Demo-Wunsch fehlt').toBeTruthy()
    const categoryId = before.categories[0].id
    const post = async (url: string, data: unknown) => {
      const res = await page.request.post(url, { data })
      expect(res.ok(), await res.text()).toBe(true)
    }
    await post(`${overviewUrl.replace(/bedarf$/, '')}wishes/categorize`, {
      wish_line_ids: [setup.id], procurement_responsibility: 'central', category_id: categoryId,
    })
    await post(`${overviewUrl.replace(/bedarf$/, '')}lines`, {
      wish_line_ids: [setup.id], label: EXISTING_LINE, category_id: categoryId, cost_kind: 'loan',
    })
    const matching = originalOf(MATCHING_WISH)
    const other = originalOf(OTHER_WISH)
    expect(matching, 'Demo-Wunsch fehlt').toBeTruthy()
    expect(other, 'Demo-Wunsch fehlt').toBeTruthy()
    expect(matching.procurement_responsibility ?? null).toBeNull() // noch nicht festgelegt

    // 1. Zwei Wünsche auswählen (über zwei Suchen hinweg)
    await selectWish(page, MATCHING_WISH)
    await selectWish(page, OTHER_WISH)
    await expect(page.getByTestId('pool-selection-total')).toContainText('2')

    // Ohne Zuständigkeit keine Übernahme
    await page.getByRole('button', { name: /bündeln/i }).first().click()
    await expect(page.locator('.v-dialog:visible')).toHaveCount(0)

    // 2. Kategorie und zentrale Beschaffung festlegen
    await page.getByTestId('pool-categorize').click()
    await pickCategory(page)
    await page.getByRole('combobox', { name: /Beschaffungszuständigkeit/ }).click({ force: true })
    await page.locator('.v-overlay--active .v-list-item', { hasText: 'Zentrale Beschaffung' }).click()
    await page.getByTestId('categorize-confirm').click()

    // 3. Ähnliche bestehende Position wird vorgeschlagen (nur Vorschlag)
    const dialog = page.locator('.v-dialog', { hasText: 'Passende bestehende Positionen' })
    await expect(dialog).toBeVisible({ timeout: 30_000 })
    const matchingSection = dialog.locator(`[data-testid="similar-wish-${matching.id}"]`)
    const card = matchingSection.locator('.similar-card', { hasText: EXISTING_LINE })
    await expect(card).toBeVisible()
    await expect(card).toContainText('Sehr ähnlich')
    await expect(dialog.locator(`[data-testid="similar-wish-${other.id}"]`)).toContainText('Keine ähnliche Position gefunden')

    // 4. Einen Wunsch zuordnen (manuelle Menge: Bestätigung, falls verlangt)
    await card.getByRole('button', { name: 'Zu dieser Position hinzufügen' }).click()
    const confirm = page.getByRole('dialog').filter({ hasText: 'Manuell festgelegte Menge ändern?' })
    if (await confirm.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await confirm.getByRole('button').last().click()
    }
    await expect(matchingSection).toHaveCount(0, { timeout: 30_000 })

    // 5. Zweiten Wunsch in eine neue Position übernehmen
    await dialog.getByTestId('similar-create-new').click()
    const bundle = page.locator('.v-dialog', { hasText: 'Position' }).last()
    await expect(bundle.locator('input').first()).toHaveValue(OTHER_WISH)
    await bundle.getByRole('button', { name: /Position erstellen|Bündeln|Speichern|Erstellen/i }).last().click()

    // 7. Beide Wünsche sind aus dem offenen Pool verschwunden
    await expect.poll(async () => {
      const after = await (await page.request.get(overviewUrl)).json()
      return after.pool.filter((w: { id: string }) => [matching.id, other.id].includes(w.id)).length
    }, { timeout: 30_000 }).toBe(0)

    // 6./8. Originalnamen, Mengen und Herkunft bleiben erhalten; Position führt die Originalwünsche
    const after = await (await page.request.get(overviewUrl)).json()
    const existingLine = after.lines.find((l: { label: string }) => l.label === EXISTING_LINE)
    const newLine = after.lines.find((l: { source_wishes: Array<{ id: string }> }) => l.source_wishes.some((w) => w.id === other.id))
    expect(existingLine.quantity).toBe(setup.quantity + matching.quantity)
    expect(existingLine.source_wishes.map((w: { id: string }) => w.id)).toContain(matching.id)
    expect(newLine.label).toBe(OTHER_WISH)
    for (const original of [matching, other]) {
      const kept = [...existingLine.source_wishes, ...newLine.source_wishes].find((w: { id: string }) => w.id === original.id)
      expect(kept.label).toBe(original.label)
      expect(kept.quantity).toBe(original.quantity)
      expect(kept.quantity_unit).toBe(original.quantity_unit)
      expect(kept.group_id).toBe(original.group_id)
      expect(kept.round_id).toBe(original.round_id)
      expect(kept.valid_from).toBe(original.valid_from)
      expect(kept.valid_to).toBe(original.valid_to)
      expect(kept.procurement_responsibility).toBe('central')
    }
  })
})
