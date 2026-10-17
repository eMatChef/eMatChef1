import { test, expect, type Page } from '@playwright/test'

/**
 * GrossanlassOrderDialog (Demo-Konto ga-mw): Dropdown schliesst bei Klick auf Titel/Rand/Aktionsleiste, Auswahl per Maus, anderer EDialog unbeeinträchtigt,
 * Helferbedarf-Layout bei schmaler Breite. Nur lesend: der neue Aufgabenblock bleibt ohne Titel und wird nicht gespeichert.
 * Aktiv nur mit E2E_GA_ORDERDIALOG=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD.
 */
const email = process.env.E2E_USER_EMAIL
const password = process.env.E2E_USER_PASSWORD

test.describe('GA Auftragsdialog', () => {
  test.skip(process.env.E2E_GA_ORDERDIALOG !== '1' || !email || !password, 'E2E_GA_ORDERDIALOG=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD nötig')
  test.setTimeout(120_000)
  test.use({ ignoreHTTPSErrors: true })

  async function login(page: Page) {
    await page.goto('/login')
    await page.getByRole('textbox', { name: /E-Mail/i }).fill(email!)
    await page.locator('input[type="password"]').first().fill(password!)
    await page.getByRole('button', { name: /Anmelden/i }).click()
    await page.waitForURL((url) => !/\/login(?:\?|$)/.test(url.pathname), { timeout: 60_000 })
  }

  async function openOrder(page: Page, title: string) {
    await login(page)
    await page.locator('a[href$="/ga/planning"]').first().click()
    await page.locator('article', { hasText: title }).first().locator('button:has-text("Öffnen")').click()
    await expect(page.locator('.v-dialog .v-overlay__content')).toBeVisible()
  }

  const items = (page: Page) => page.locator('.v-overlay--active .v-list-item')

  test('dropdown closes on dialog title, body and actions, keyboard still works', async ({ page }) => {
    await openOrder(page, 'Bar-Tresen West')
    const select = page.locator('.v-dialog .v-select').first()
    const dialog = page.locator('.v-dialog .v-overlay__content')
    const box = (await dialog.boundingBox())!
    const spots = {
      title: { x: box.x + box.width / 2, y: box.y + 20 },
      edge: { x: box.x + box.width - 4, y: box.y + box.height / 2 },
      actions: { x: box.x + 150, y: box.y + box.height - 10 },
    }
    for (const [name, pos] of Object.entries(spots)) {
      await select.click()
      await expect(items(page).first(), name).toBeVisible()
      await page.mouse.click(pos.x, pos.y)
      await expect(items(page), name).toHaveCount(0)
    }
    // Tastatur: öffnen, Pfeil, Escape schliesst nur das Dropdown
    await select.locator('input').focus()
    await page.keyboard.press('ArrowDown')
    await expect(items(page).first()).toBeVisible()
    await page.waitForTimeout(300) // Vuetify setzt den Overlay-Stack asynchron
    await page.keyboard.press('Escape')
    await expect(items(page)).toHaveCount(0)
    await expect(dialog).toBeVisible()
    // Klick auf das Feld selbst öffnet und wird nicht sofort wieder geschlossen
    await select.click()
    await expect(items(page).first()).toBeVisible()
  })

  test('helper need layout at 420px has no clipped fields', async ({ page }) => {
    await openOrder(page, 'Bar-Tresen West')
    await page.setViewportSize({ width: 420, height: 900 })
    await page.getByRole('tab', { name: /Aufgaben/ }).click()
    await page.locator('.ga-block__add').first().click()
    const helpers = page.locator('.ga-block__helpers').last()
    await helpers.scrollIntoViewIfNeeded()
    await helpers.locator('[data-testid=task-helper-total] input').fill('3')
    await helpers.getByText('Funktion hinzufügen').click()
    const row = helpers.locator('.ga-block__helper-fn').first()
    await row.locator('input').first().fill('Elektriker')
    await row.locator('input').nth(1).fill('5')
    await expect(helpers.locator('[data-testid=task-helper-overflow]')).toBeVisible()
    const dims = await helpers.evaluate((el) => {
      const w = (n: Element | null) => Math.round(n?.getBoundingClientRect().width ?? 0)
      const f = el.querySelector('.ga-block__helper-fn')!
      const kids = [...f.children].map(w)
      const right = Math.max(el.getBoundingClientRect().right, ...[...f.children].map((c) => c.getBoundingClientRect().right))
      return { fn: w(f), kids, overflowX: right > document.documentElement.clientWidth, helpersW: w(el) }
    })
    expect(dims.overflowX).toBe(false)
    await expect(row.locator('button.action-btn')).toBeInViewport()
    await expect(helpers.locator('[data-testid=task-helper-overflow]')).toBeInViewport({ ratio: 0.9 })
    expect(dims.kids[0]).toBeGreaterThanOrEqual(110) // Funktionsfeld nicht gequetscht
    await helpers.screenshot({ path: 'test-results/helper-420.png' })
  })

  test('dropdown items stay selectable by mouse', async ({ page }) => {
    await openOrder(page, 'Bar-Tresen West')
    // Auswahl per Mausklick im Entwurf eines Aufgabenblocks (ohne Titel, wird nicht gespeichert)
    await page.getByRole('tab', { name: /Aufgaben/ }).click()
    await page.locator('.ga-block__add').first().click()
    const assignee = page.locator('.ga-block').last().locator('.v-autocomplete').first()
    await assignee.click()
    const first = items(page).first()
    await expect(first).toBeVisible()
    const label = (await first.innerText()).trim().split('\n')[0]
    await first.click()
    await expect(items(page)).toHaveCount(0)
    await expect(assignee).toContainText(label)
  })

  test('other EDialog is unaffected by the pointerdown handler', async ({ page }) => {
    await login(page)
    await page.locator('a[href$="/ga/procurement"]').first().click()
    await page.locator('button[title="Bearbeiten"]').first().click()
    const dialog = page.locator('.v-dialog .v-overlay__content')
    await expect(dialog).toBeVisible()
    const select = dialog.locator('[data-testid=wish-edit-responsibility]')
    await select.scrollIntoViewIfNeeded()
    await select.click()
    await expect(items(page).first()).toBeVisible()
    const target = items(page).nth(1)
    const label = (await target.innerText()).trim().split('\n')[0]
    await target.click()
    await expect(items(page)).toHaveCount(0)
    await expect(select).toContainText(label)
    // Klick auf Titel und Text schliesst den Dialog nicht und ändert nichts
    const box = (await dialog.boundingBox())!
    await page.mouse.click(box.x + box.width / 2, box.y + 20)
    await expect(dialog).toBeVisible()
    await expect(select).toContainText(label)
    // Verwerfen, nicht speichern
    await dialog.getByRole('button', { name: /Abbrechen/ }).click()
    await expect(dialog).toHaveCount(0)
  })
})
