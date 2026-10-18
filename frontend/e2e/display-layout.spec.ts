import { expect, test, type Page } from '@playwright/test'

/**
 * Infoscreen-Layouts (Phase 5.3/5.4): Screen ohne Gerät gestalten, aus Vorlage, im Editor ändern, Vorschau für
 * verschiedene Bildschirmformate, speichern/veröffentlichen, Gerät koppeln und passive Anzeige prüfen.
 *
 * Läuft NUR gegen eine isolierte, frisch migrierte Umgebung (nie gegen Nutzerdaten) und nur mit E2E_DISPLAY_LAYOUT=1.
 * Voraussetzungen (siehe docs/E2E.md): Grossanlass-Department E2E_GA_DEPT und ein Verwaltungs-User (E2E_USER_EMAIL /
 * E2E_USER_PASSWORD) mit Verwaltungsrolle, App-Host E2E_BASE_URL (app.…) und Display-Host E2E_DISPLAY_URL (display.…).
 */
const gaDept = process.env.E2E_GA_DEPT || ''
const normalDept = process.env.E2E_NORMAL_DEPT || ''
const displayUrl = (process.env.E2E_DISPLAY_URL || '').replace(/\/$/, '')

test.describe.configure({ mode: 'serial' })
test.skip(process.env.E2E_DISPLAY_LAYOUT !== '1', 'E2E_DISPLAY_LAYOUT=1 und isolierte Umgebung erforderlich')

async function login(page: Page) {
  await page.goto('/login')
  await page.getByRole('textbox', { name: /E-Mail/i }).fill(process.env.E2E_USER_EMAIL!)
  await page.locator('input[type="password"]').first().fill(process.env.E2E_USER_PASSWORD!)
  await page.getByRole('button', { name: /Anmelden/i }).click()
  await page.waitForURL((url) => !/\/login(?:\?|$)/.test(url.pathname + url.search), { timeout: 30_000 })
}

let screenId = ''
const screenName = `E2E Materiallager ${Date.now()}`

test('Infoscreen ohne Gerät aus Vorlage erstellen und im Editor verändern', async ({ page }) => {
  await login(page)
  await page.goto(`/${gaDept}/ga/displays`)

  // Klare Unterscheidung: Infoscreen erstellen vs. Bildschirm hinzufügen
  await expect(page.getByRole('button', { name: 'Infoscreen erstellen' }).first()).toBeVisible()
  await expect(page.getByRole('button', { name: /Bildschirm mit neuem Infoscreen verbinden/ })).toBeVisible()

  await page.getByRole('button', { name: 'Infoscreen erstellen' }).first().click()
  await page.getByLabel('Name', { exact: false }).first().fill(screenName)
  // Vorlagenkarten zeigen echte Vorschaubilder (Screenshots des Renderers), keine nachgezeichneten Raster
  const previews = page.locator('img.tpl-preview')
  await expect(previews).toHaveCount(4)
  expect(await previews.evaluateAll((imgs) => imgs.every((i) => (i as HTMLImageElement).complete && (i as HTMLImageElement).naturalWidth > 0))).toBe(true)
  await page.getByTestId('template-ga_materiallager').or(page.getByRole('radio', { name: /Materiallager/ })).first().click()
  // Das Studio öffnet standardmässig in einem neuen Tab.
  const studioPromise = page.context().waitForEvent('page')
  await page.getByRole('button', { name: 'Infoscreen erstellen' }).last().click()
  const editor = await studioPromise
  await editor.waitForURL(/\/ga\/displays\/[^/]+\/layout/)
  screenId = editor.url().split('/displays/')[1]!.split('/')[0]!
  await expect(editor.getByText('Noch nicht veröffentlicht').first()).toBeVisible()
  await expect(editor.getByTestId('layout-canvas')).toBeVisible()
  // Vorlage ist eine Kopie: Elemente sind vorhanden
  await expect(editor.locator('[data-testid^="layout-item-"]').first()).toBeVisible()

  // Die Vorlage füllt die Seite: Elemente dürfen sich nie überlappen, ein weiteres Widget findet keinen Platz.
  await editor.getByTestId('palette-clock').click()
  await expect(editor.getByText('Es ist kein freier Platz vorhanden.').first()).toBeVisible()
  await expect(editor.getByTestId('status-unsaved')).toHaveCount(0)

  // Platz schaffen (Aktivitätenfeed entfernen), dann Widget aus der Bibliothek hinzufügen – danach ungespeichert
  await editor.getByTestId('layout-item-i-feed').click()
  await editor.keyboard.press('Delete')
  await editor.getByTestId('palette-clock').click()
  await expect(editor.getByTestId('status-unsaved')).toBeVisible()
  const item = editor.locator('[data-testid^="layout-item-"].item--selected')
  await expect(item).toHaveCount(1)

  // Pfeiltaste verschiebt; in der Randzeile blockiert der Rand, ohne etwas zu überlappen
  const start = await item.boundingBox()
  await editor.keyboard.press('ArrowDown')
  expect((await item.boundingBox())!.y).toBeGreaterThan(start!.y)

  // Drag-and-drop: Element mit der Maus nach unten verschieben (seitlich begrenzt der Rand)
  const before = await item.boundingBox()
  await editor.mouse.move(before!.x + before!.width / 2, before!.y + before!.height / 2)
  await editor.mouse.down()
  await editor.mouse.move(before!.x + before!.width / 2, before!.y + before!.height / 2 + 40, { steps: 8 })
  await editor.mouse.up()
  expect((await item.boundingBox())!.y).toBeGreaterThan(before!.y)

  // Skalieren über den Griff (nach unten)
  const handle = editor.getByTestId('layout-resize-handle')
  const hb = await handle.boundingBox()
  const sizeBefore = await item.boundingBox()
  await editor.mouse.move(hb!.x + 4, hb!.y + 4)
  await editor.mouse.down()
  await editor.mouse.move(hb!.x + 4, hb!.y + 4 + 25, { steps: 6 })
  await editor.mouse.up()
  expect((await item.boundingBox())!.height).toBeGreaterThan(sizeBefore!.height)

  // Undo/Redo: Skalieren rückgängig machen und wiederholen
  await editor.getByTestId('btn-undo').click()
  expect(Math.abs((await item.boundingBox())!.height - sizeBefore!.height)).toBeLessThan(2)
  await editor.getByTestId('btn-redo').click()
  expect((await item.boundingBox())!.height).toBeGreaterThan(sizeBefore!.height)

  // Das Test-Widget wieder entfernen
  await editor.getByRole('button', { name: 'Entfernen' }).click()

  // Eigenschaften: Titel der Vorlagenkopie ändern
  await editor.locator('[data-testid^="layout-item-"]').filter({ hasText: 'Materiallager' }).first().click()
  await editor.getByLabel('Titel', { exact: true }).first().fill('Willkommen im Lager')

  await editor.getByTestId('btn-save').click()
  await expect(editor.getByText('Entwurf gespeichert.').first()).toBeVisible()
  await expect(editor.getByTestId('status-unpublished')).toBeVisible()
})

test('Vorschau für TV und LED-Wall', async ({ page }) => {
  await login(page)
  await page.goto(`/${gaDept}/ga/displays/${screenId}/layout`)
  await page.getByTestId('btn-preview').click()
  const frame = page.getByTestId('preview-frame')
  await expect(frame).toBeVisible()
  await expect(frame.locator('.dl')).toBeVisible()
  const ratio = async () => {
    const b = (await frame.boundingBox())!
    return b.width / b.height
  }
  await page.locator('.preview-bar__select .v-field').click()
  await page.getByRole('option', { name: /Fernseher 16:9/ }).click()
  expect(Math.abs((await ratio()) - 16 / 9)).toBeLessThan(0.05)
  await page.locator('.preview-bar__select .v-field').click()
  await page.getByRole('option', { name: /LED-Wall 3:2/ }).click()
  expect(Math.abs((await ratio()) - 3 / 2)).toBeLessThan(0.05)
  // Die Vorschau zeigt den gespeicherten Entwurf (Titel der Vorlagenkopie) mit Demo-Daten-Kennzeichnung.
  await expect(frame).toContainText('Willkommen im Lager')
  await expect(frame.locator('.ga-w__demo').first()).toBeVisible()
})

test('Leeres Layout: Material und Logistik nebeneinander', async ({ page }) => {
  await login(page)
  await page.goto(`/${gaDept}/ga/displays`)
  await page.getByRole('button', { name: 'Infoscreen erstellen' }).first().click()
  await page.getByLabel('Name', { exact: false }).first().fill(`E2E Leer ${Date.now()}`)
  await page.getByTestId('template-blank').or(page.getByRole('radio', { name: /Leere Anzeige/ })).first().click()
  const studioPromise = page.context().waitForEvent('page')
  await page.getByRole('button', { name: 'Infoscreen erstellen' }).last().click()
  const editor = await studioPromise
  await editor.waitForURL(/\/ga\/displays\/[^/]+\/layout/)

  await editor.getByTestId('palette-ga_material_overview').click()
  await editor.getByTestId('palette-ga_transport_overview').click()
  await expect(editor.locator('[data-testid^="layout-item-"]')).toHaveCount(2)
  await expect(editor.getByText(/Hinweis.*überlappen/)).toHaveCount(0)
  await editor.getByTestId('btn-save').click()
  await expect(editor.getByText('Entwurf gespeichert.').first()).toBeVisible()

  await editor.getByTestId('btn-preview').click()
  const frame = editor.getByTestId('preview-frame')
  await expect(frame.locator('.ga-w__demo')).toHaveCount(2)
  const boxes = await frame.locator('.dl-cell').evaluateAll((els) => els.map((e) => e.getBoundingClientRect().x))
  expect(new Set(boxes.map((x) => Math.round(x))).size).toBe(2)
  await editor.keyboard.press('Escape')
  await expect(frame).toBeHidden()

  // Gesamtanzeige: zweite Seite hinzufügen und Gesamtrotation einschalten
  await editor.getByRole('button', { name: 'Seite hinzufügen' }).click()
  await editor.getByTestId('tab-display').click()
  await editor.getByRole('combobox', { name: /Seite zum Ablauf hinzufügen/ }).click({ force: true })
  await editor.locator('.v-overlay-container').getByRole('option').first().click()
  await editor.getByRole('button', { name: 'Hinzufügen' }).last().click()
  await expect(editor.getByText('Rotation aktiv')).toBeVisible()
})

test('Gerät koppeln, passive Anzeige, Entwurf vs. Veröffentlichung', async ({ page, browser }) => {
  test.setTimeout(240_000)
  await login(page)
  await page.goto(`/${gaDept}/ga/displays/${screenId}/layout`)

  // Fernseher (eigener Kontext, Display-Domain) öffnen und Kopplungslink abfangen, noch ohne Veröffentlichung.
  const tvContext = await browser.newContext()
  const tv = await tvContext.newPage()
  let pairUrl = ''
  tv.on('response', async (res) => {
    if (res.url().endsWith('/api/public/display-pairing') && res.request().method() === 'POST') {
      pairUrl = ((await res.json()) as { pair_url: string }).pair_url
    }
  })
  await tv.goto(`${displayUrl}/display/connect`)
  await expect.poll(() => pairUrl, { timeout: 20_000 }).not.toBe('')

  // Zurück in der Verwaltung: «Bildschirm hinzufügen» zeigt die Display-Adresse und die Anleitung.
  await page.goto(`/${gaDept}/ga/displays`)
  await page.getByRole('button', { name: screenName }).click()
  await page.getByRole('button', { name: 'Bildschirm hinzufügen' }).first().click()
  const kiosk = page.getByTestId('kiosk-url')
  await expect(kiosk).toBeVisible()
  await expect(kiosk).toContainText(new URL(displayUrl).host)
  await expect(page.getByText('Öffne auf deinem Fernseher oder Anzeigegerät einen Browser.')).toBeVisible()
  await expect(page.getByRole('button', { name: /kopieren/i })).toHaveCount(0)

  await page.getByLabel(/Kopplungs-Link/).fill(pairUrl)
  await page.getByRole('button', { name: 'Weiter' }).click()
  await page.getByLabel('Gerätename').fill('Fernseher E2E')
  await page.getByRole('button', { name: 'Verbinden', exact: true }).click()
  await expect(page.getByText('Der Bildschirm ist verbunden.')).toBeVisible()
  await expect(page.getByText('Fernseher E2E').first()).toBeVisible()
  await page.getByRole('button', { name: 'Fertig' }).click()

  // Noch nichts veröffentlicht: neutraler Wartezustand ohne Entwurfsdaten.
  await tv.waitForURL(/\/display\/device/, { timeout: 20_000 })
  await expect(tv.locator('.display-layout--waiting')).toBeVisible()
  await expect(tv.locator('body')).not.toContainText('Willkommen im Lager')

  // Veröffentlichen im Editor
  await page.goto(`/${gaDept}/ga/displays/${screenId}/layout`)
  await page.getByTestId('btn-publish').click()
  await page.getByTestId('btn-publish-confirm').click()
  await expect(page.getByText(/Veröffentlicht: Revision 1/).first()).toBeVisible()

  // Gerät übernimmt die Revision beim nächsten Abruf, ohne neue Kopplung.
  await tv.reload()
  await expect(tv.locator('.dl')).toBeVisible({ timeout: 20_000 })
  await expect(tv.locator('.dl')).toContainText('Willkommen im Lager')

  // Veröffentlichte Anzeigen verwenden standardmässig echte Daten: Live-Widgets, keine Demo-Kennzeichnung.
  await expect(tv.getByTestId('ga-live-widget').first()).toBeVisible({ timeout: 20_000 })
  await expect(tv.locator('.ga-w__demo')).toHaveCount(0)
  await expect(tv.getByTestId('display-demo-badge')).toHaveCount(0)

  // Passive Anzeige: keine Bedienelemente.
  await expect(tv.locator('button, input, textarea, select, a[href], form, [role="button"], [role="tab"], [tabindex]')).toHaveCount(0)

  // Entwurf ändern und speichern: das Gerät bleibt unverändert.
  await page.goto(`/${gaDept}/ga/displays/${screenId}/layout`)
  await page.locator('[data-testid^="layout-item-"]').filter({ hasText: 'Willkommen im Lager' }).first().click()
  await page.getByLabel('Titel', { exact: true }).first().fill('Neuer Entwurf')
  await page.getByTestId('btn-save').click()
  await expect(page.getByText('Entwurf gespeichert.').first()).toBeVisible()
  await tv.reload()
  await expect(tv.locator('.dl')).toBeVisible({ timeout: 20_000 })
  await expect(tv.locator('.dl')).not.toContainText('Neuer Entwurf')

  // Veröffentlichen: Gerät übernimmt die neue Revision.
  await page.getByTestId('btn-publish').click()
  await page.getByTestId('btn-publish-confirm').click()
  await expect(page.getByText(/Veröffentlicht: Revision 2/).first()).toBeVisible()
  await tv.reload()
  await expect(tv.locator('.dl')).toContainText('Neuer Entwurf', { timeout: 20_000 })

  // Zonenrotation: der Inhalt der rotierenden Zone wechselt von selbst.
  const rotation = tv.locator('.dl-rotation').first()
  const first = await rotation.innerText()
  await expect.poll(async () => rotation.innerText(), { timeout: 30_000 }).not.toBe(first)

  // Demomodus der Anzeige: nur nach ausdrücklicher Einstellung und Veröffentlichung.
  await page.goto(`/${gaDept}/ga/displays/${screenId}/layout`)
  await page.getByTestId('tab-display').click()
  await page.getByTestId('published-demo-toggle').getByRole('checkbox').check()
  await page.getByTestId('btn-publish').click()
  await expect(page.getByTestId('publish-mode')).toContainText('Demodaten')
  await page.getByTestId('btn-publish-confirm').click()
  await expect(page.getByText(/Veröffentlicht: Revision 3/).first()).toBeVisible()
  await tv.reload()
  await expect(tv.getByTestId('display-demo-badge')).toBeVisible({ timeout: 20_000 })
  await expect(tv.getByTestId('display-demo-badge')).toHaveText('Demo-Daten')
  await expect(tv.getByTestId('ga-live-widget')).toHaveCount(0)

  // Zurück auf echte Daten.
  await page.getByTestId('published-demo-toggle').getByRole('checkbox').uncheck()
  await page.getByTestId('btn-publish').click()
  await page.getByTestId('btn-publish-confirm').click()
  await expect(page.getByText(/Veröffentlicht: Revision 4/).first()).toBeVisible()
  await tv.reload()
  await expect(tv.getByTestId('ga-live-widget').first()).toBeVisible({ timeout: 20_000 })
  await expect(tv.getByTestId('display-demo-badge')).toHaveCount(0)

  await tvContext.close()
})

test('Bestehende Department-Screens behalten die Checkbox-Konfiguration', async ({ page }) => {
  test.skip(!normalDept, 'E2E_NORMAL_DEPT nicht gesetzt')
  await login(page)
  await page.goto(`/${normalDept}/dept/settings/my-department/display-screens`)
  await expect(page.getByRole('button', { name: 'Infoscreen erstellen' }).first()).toBeVisible()
  await page.getByRole('button', { name: 'Infoscreen erstellen' }).first().click()
  // Normales Department: nur Name, keine Layoutvorlagen
  await expect(page.getByText('Starten mit')).toHaveCount(0)
})

test('Department-Isolation: fremde Departments und Screens sind nicht erreichbar', async ({ page }) => {
  const foreign = process.env.E2E_FOREIGN_DEPT
  test.skip(!foreign, 'E2E_FOREIGN_DEPT (Department ohne Mitgliedschaft des Users) nicht gesetzt')
  await login(page)
  // Kein Verwaltungsrecht im fremden Department
  expect((await page.request.get(`/api/departments/${foreign}/display-layout/catalog`)).status()).toBe(403)
  expect((await page.request.get(`/api/departments/${foreign}/display-screens/${screenId}/layout`)).status()).toBe(403)
  // Screen eines anderen (eigenen) Departments über die falsche Department-ID: nicht gefunden
  expect((await page.request.get(`/api/departments/${normalDept}/display-screens/${screenId}/layout`)).status()).toBe(404)
  // Normales Department: Layouts sind (noch) nicht verfügbar, die Checkbox-Konfiguration bleibt
  const catalog = await (await page.request.get(`/api/departments/${normalDept}/display-layout/catalog`)).json()
  expect(catalog.enabled).toBe(false)
  expect(catalog.templates).toEqual([])
  // Nur der GA-Katalog bietet GA-Widgets und Vorlagen an
  const ga = await (await page.request.get(`/api/departments/${gaDept}/display-layout/catalog`)).json()
  expect(ga.widgets.some((w: { type: string }) => w.type === 'ga_projects')).toBe(true)
})
