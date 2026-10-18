import { expect, test, type BrowserContext, type Page } from '@playwright/test'

/**
 * Infoscreen-Studio: eigenständiges Vollbild-Studio im neuen Tab, Bausteinbibliothek, Gruppen mit eigenem Raster,
 * eigene Bausteine, Zoom/Pan, Demo-/Live-Umschalter und Karte innerhalb von Gruppen.
 *
 * Läuft NUR gegen eine isolierte, frisch migrierte Umgebung und nur mit E2E_DISPLAY_STUDIO=1 (siehe docs/E2E.md,
 * gleiche Voraussetzungen wie display-layout.spec.ts).
 */
const gaDept = process.env.E2E_GA_DEPT || ''

test.describe.configure({ mode: 'serial' })
test.skip(process.env.E2E_DISPLAY_STUDIO !== '1', 'E2E_DISPLAY_STUDIO=1 und isolierte Umgebung erforderlich')

async function login(page: Page) {
  await page.goto('/login')
  await page.getByRole('textbox', { name: /E-Mail/i }).fill(process.env.E2E_USER_EMAIL!)
  await page.locator('input[type="password"]').first().fill(process.env.E2E_USER_PASSWORD!)
  await page.getByRole('button', { name: /Anmelden/i }).click()
  await page.waitForURL((url) => !/\/login(?:\?|$)/.test(url.pathname + url.search), { timeout: 30_000 })
}

const screenName = `E2E Studio ${Date.now()}`
let context: BrowserContext
let studio: Page

// Ein gemeinsamer Browserkontext für alle Schritte: das Studio bleibt als Tab geöffnet.
test.beforeAll(async ({ browser }) => {
  context = await browser.newContext({ baseURL: process.env.E2E_BASE_URL, viewport: { width: 1600, height: 900 } })
  const page = await context.newPage()
  await login(page)
  await page.goto(`/${gaDept}/ga/displays`)
  await page.getByRole('button', { name: 'Infoscreen erstellen' }).first().click()
  await page.getByLabel('Name', { exact: false }).first().fill(screenName)
  await page.getByTestId('template-blank').or(page.getByRole('radio', { name: /Leere Anzeige/ })).first().click()
  // Das Studio öffnet standardmässig in einem neuen Tab.
  const popup = context.waitForEvent('page')
  await page.getByRole('button', { name: 'Infoscreen erstellen' }).last().click()
  studio = await popup
  await studio.waitForURL(/\/ga\/displays\/[^/]+\/layout/)
})

test.afterAll(async () => {
  await context?.close()
})

test('Studio öffnet in einem neuen Tab ohne Sidebar und nutzt den ganzen Browserbereich', async () => {
  await studio.setViewportSize({ width: 1600, height: 900 })
  await expect(studio.getByTestId('studio-toolbar')).toBeVisible()
  await expect(studio.getByTestId('layout-canvas')).toBeVisible()
  // Keine normale App-Navigation: weder Sidebar noch Seitenheader.
  await expect(studio.locator('.v-navigation-drawer')).toHaveCount(0)
  await expect(studio.locator('header.v-toolbar, .top-header')).toHaveCount(0)
  // Kein Seiten-Scrollen: der Inhalt passt in den Browserbereich.
  const overflow = await studio.evaluate(() => document.documentElement.scrollHeight - window.innerHeight)
  expect(overflow).toBeLessThanOrEqual(1)
  // Die Arbeitsfläche belegt den Löwenanteil (mindestens die Hälfte der Fensterfläche).
  const viewport = await studio.getByTestId('studio-viewport').boundingBox()
  expect(viewport!.width * viewport!.height).toBeGreaterThan(1600 * 900 * 0.5)

  // Bibliothek und Eigenschaften scrollen unabhängig.
  expect(await studio.locator('.lib__scroll').evaluate((el) => getComputedStyle(el).overflowY)).toBe('auto')
  expect(await studio.locator('.panel__scroll').evaluate((el) => getComputedStyle(el).overflowY)).toBe('auto')
})

test('Bibliothek: Kategorien, Suche, Einfügen per Klick und Drag-and-drop; Demo ist der Standard', async () => {
  await expect(studio.getByTestId('mode-badge')).toContainText('Demo');
  await expect(studio.getByTestId('mode-demo')).toHaveAttribute('aria-pressed', 'true')

  for (const category of ['general', 'material', 'logistics', 'templates', 'custom']) {
    await expect(studio.getByTestId(`library-category-${category}`)).toBeVisible()
  }
  // Kategorie zuklappen
  const general = studio.getByTestId('library-category-general')
  await general.getByRole('button').first().click()
  await expect(studio.getByTestId('palette-clock')).toBeHidden()
  await general.getByRole('button').first().click()
  await expect(studio.getByTestId('palette-clock')).toBeVisible()

  // Suche
  await studio.getByTestId('library-search').fill('Fehlmaterial')
  await expect(studio.getByTestId('palette-ga_missing_material')).toBeVisible()
  await expect(studio.getByTestId('palette-clock')).toHaveCount(0)
  await studio.getByTestId('library-search').fill('')

  // Einfügen per Klick (Kopfzeile oben) und per Drag-and-drop (Uhr)
  await studio.getByTestId('palette-header').click()
  await expect(studio.locator('[data-testid^="layout-item-"]')).toHaveCount(1)
  const canvas = await studio.getByTestId('layout-canvas').boundingBox()
  const clock = studio.getByTestId('palette-clock')
  await clock.dragTo(studio.getByTestId('studio-viewport'), { targetPosition: { x: canvas!.x + canvas!.width * 0.7, y: canvas!.y + canvas!.height * 0.5 } })
  await expect(studio.locator('[data-testid^="layout-item-"]')).toHaveCount(2)
})

test('Zoom, Einpassen und Rasterlinien verändern nur die Ansicht', async () => {
  const zoomLabel = studio.getByTestId('studio-zoom')
  const before = await zoomLabel.innerText()
  await studio.getByTitle('Vergrössern').click()
  await expect(zoomLabel).not.toHaveText(before)
  await studio.getByTitle('Verkleinern').click()
  await studio.getByTitle('Verkleinern').click()
  await studio.getByTestId('btn-fit').click()
  await expect(zoomLabel).toHaveText(before)
  // Zoom ändert die gespeicherte Geometrie nicht: weiterhin nur die beiden Elemente, nichts verschoben, nicht geändert.
  await expect(studio.getByTestId('status-unsaved')).toBeVisible() // vom Einfügen
  const grid = studio.getByTestId('studio-gridlines')
  await expect(grid).toBeVisible()
  await studio.getByTestId('btn-grid').click()
  await expect(grid).toHaveCount(0)
  await studio.getByTestId('btn-grid').click()
  await expect(grid).toBeVisible()
})

test('Mehrfachauswahl, Gruppieren, Gruppe öffnen, Auflösen und Kollisionsschutz', async () => {
  const items = studio.locator('[data-testid^="layout-item-"]')
  await items.nth(0).click()
  await items.nth(1).click({ modifiers: ['Shift'] })
  await expect(studio.getByTestId('multi-selection')).toBeVisible()
  await studio.getByTestId('btn-group').click()
  await expect(items).toHaveCount(1)
  await expect(items.first()).toContainText('Gruppe 1')

  // Gruppe umbenennen und separat öffnen
  await studio.getByTestId('group-name').getByRole('textbox').fill('Kopf und Uhr')
  await items.first().dblclick()
  await expect(studio.getByTestId('studio-breadcrumb')).toContainText('Kopf und Uhr')
  await expect(items).toHaveCount(2)
  // Innerhalb der Gruppe: Elemente überlappen nie (Verschieben auf das andere Element wird verweigert)
  const a = await items.nth(0).boundingBox()
  const b = await items.nth(1).boundingBox()
  await studio.mouse.move(a!.x + a!.width / 2, a!.y + a!.height / 2)
  await studio.mouse.down()
  await studio.mouse.move(b!.x + b!.width / 2, b!.y + b!.height / 2, { steps: 10 })
  await studio.mouse.up()
  const a2 = await items.nth(0).boundingBox()
  const b2 = await items.nth(1).boundingBox()
  const overlap = !(a2!.x + a2!.width <= b2!.x + 1 || b2!.x + b2!.width <= a2!.x + 1 || a2!.y + a2!.height <= b2!.y + 1 || b2!.y + b2!.height <= a2!.y + 1)
  expect(overlap).toBe(false)

  // Zurück zur Gesamtanzeige, Gruppe auflösen: keine Inhalte gehen verloren
  await studio.getByTestId('btn-exit-edit').click()
  await items.first().click()
  await studio.getByTestId('btn-ungroup').click()
  await expect(items).toHaveCount(2)
  // Undo stellt die Gruppe wieder her
  await studio.getByTestId('btn-undo').click()
  await expect(items).toHaveCount(1)
})

test('Eigener Baustein: speichern, einfügen (unabhängige Kopie), umbenennen, löschen', async () => {
  const items = studio.locator('[data-testid^="layout-item-"]')
  const blockName = `Kopfbaustein ${Date.now()}`
  // Gruppe auflösen und die Kopfzeile (12×1) als eigenen Baustein speichern.
  await items.first().click()
  await studio.getByTestId('btn-ungroup').click()
  await expect(items).toHaveCount(2)
  await studio.locator('[data-testid^="layout-item-"]').filter({ hasText: 'Kopfzeile' }).first().click()
  await studio.getByTestId('btn-save-block').click()
  await studio.getByTestId('block-name').getByRole('textbox').fill(blockName)
  await studio.getByTestId('block-save').click()
  const custom = studio.getByTestId('library-category-custom')
  await expect(custom).toContainText(blockName)

  // Einfügen erzeugt eine unabhängige Kopie.
  await studio.locator('[data-testid^="layout-item-"]').filter({ hasText: 'Kopfzeile' }).first().click()
  await studio.keyboard.press('Delete')
  await expect(items).toHaveCount(1)
  const entry = custom.locator('li.lib__row').filter({ hasText: blockName })
  await entry.locator('.lib__item').click()
  await expect(items).toHaveCount(2)

  // Der Baustein gehört dem Grossanlass: per API nur dort sichtbar, fremde Departments erhalten 403.
  const api = await studio.request.get(`/api/departments/${gaDept}/display-blocks`)
  expect(((await api.json()) as { blocks: { name: string }[] }).blocks.map((x) => x.name)).toContain(blockName)
  const foreign = process.env.E2E_FOREIGN_DEPT
  if (foreign) expect((await studio.request.get(`/api/departments/${foreign}/display-blocks`)).status()).toBe(403)

  await entry.getByLabel('Umbenennen').click()
  await studio.getByTestId('block-name').getByRole('textbox').fill(`${blockName} neu`)
  await studio.getByTestId('block-save').click()
  await expect(custom).toContainText(`${blockName} neu`)

  // Löschen entfernt nur die Vorlage; die bereits eingefügte Kopie bleibt im Layout.
  await custom.locator('li.lib__row').filter({ hasText: `${blockName} neu` }).getByLabel('Löschen').click()
  await studio.getByRole('button', { name: 'Löschen', exact: true }).last().click()
  await expect(custom).not.toContainText(`${blockName} neu`)
  await expect(items).toHaveCount(2)
})

test('Demo/Live-Umschalter betrifft nur die Vorschau; Live zeigt echte GA-Daten', async () => {
  const saved = studio.getByTestId('btn-save')
  await saved.click()
  await expect(studio.getByText('Entwurf gespeichert.').first()).toBeVisible()
  const published = studio.getByText('Noch nicht veröffentlicht').first()
  await expect(published).toBeVisible()

  // Aufgabenliste einfügen (Platz schaffen: alles andere entfernen)
  await studio.keyboard.press('Control+a')
  await studio.keyboard.press('Delete')
  await studio.getByTestId('library-search').fill('Aufgaben')
  await studio.getByTestId('palette-ga_tasks').click()
  await studio.getByTestId('library-search').fill('')
  await expect(studio.getByTestId('ga-live-widget')).toHaveCount(0) // Demo: Beispieldaten, kein Live-Widget

  await studio.getByTestId('mode-live').click()
  await expect(studio.getByTestId('mode-badge')).toContainText('Live')
  await expect(studio.getByTestId('ga-live-widget').first()).toBeVisible()
  // Echte Aufgaben des Demo-Grossanlasses (oder verständlicher Leerzustand), nie Demo-Beispielwerte.
  await expect(studio.getByTestId('ga-live-widget').first()).not.toContainText('Daten werden geladen', { timeout: 20_000 })

  // Nichts wurde veröffentlicht, Geräte sind unberührt.
  await expect(published).toBeVisible()
  await studio.getByTestId('mode-demo').click()
  await expect(studio.getByTestId('ga-live-widget')).toHaveCount(0)
})

test('Karte in einer Widget-Gruppe passt sich der Grösse an, separates Bearbeiten', async () => {
  await studio.keyboard.press('Control+a')
  await studio.keyboard.press('Delete')
  await studio.getByTestId('library-search').fill('Holzbau')
  await studio.locator('[data-testid^="block-official:region_holzbau"]').click()
  await studio.getByTestId('library-search').fill('')
  const items = studio.locator('[data-testid^="layout-item-"]')
  await expect(items).toHaveCount(1)
  // Gruppe öffnen: Karte (Leaflet) ist innerhalb der Gruppe sichtbar
  await items.first().dblclick()
  const map = studio.locator('.leaflet-container').first()
  await expect(map).toBeVisible({ timeout: 20_000 })
  const before = await map.boundingBox()
  // Karte verkleinern: die Karte folgt der Grösse
  await studio.locator('[data-testid^="layout-item-"]').filter({ hasText: 'Lageplan' }).first().click()
  const handle = studio.getByTestId('layout-resize-handle')
  const hb = await handle.boundingBox()
  await studio.mouse.move(hb!.x + 4, hb!.y + 4)
  await studio.mouse.down()
  await studio.mouse.move(hb!.x + 4, hb!.y - 40, { steps: 6 })
  await studio.mouse.up()
  await expect.poll(async () => (await map.boundingBox())!.height).toBeLessThan(before!.height)
  // Separat bearbeiten: das Widget wird einzeln dargestellt
  await studio.getByTestId('btn-isolate').click()
  await expect(studio.getByText('Einzelbearbeitung')).toBeVisible()
  await expect(studio.locator('.leaflet-container').first()).toBeVisible()
  await studio.getByTestId('btn-exit-edit').click()
})

test('Demo und Live nutzen dieselbe Darstellung; Ressort-/Bereichsfilter wirken auch live', async () => {
  await studio.keyboard.press('Control+a')
  await studio.keyboard.press('Delete')
  await studio.getByTestId('library-search').fill('Materialübersicht')
  await studio.getByTestId('palette-ga_material_overview').click()
  await studio.getByTestId('library-search').fill('')
  const widget = studio.locator('[data-testid^="layout-item-"]').first()

  // Demo: Darstellung der Material-Anzeige (KPI-Leiste mit 5 Kennzahlen)
  await studio.getByTestId('mode-demo').click()
  await expect(widget.locator('.mat .kpis .kpi')).toHaveCount(5)
  // Live: dieselbe Komponente, nur andere Daten
  await studio.getByTestId('mode-live').click()
  await expect(widget.getByTestId('ga-live-widget')).toBeVisible()
  await expect(widget.locator('.mat .kpis .kpi')).toHaveCount(5)
  await expect(widget.locator('.ga-w__demo')).toHaveCount(0)
  const rows = () => widget.locator('.mat .rows .row').count()
  const before = await rows()
  expect(before).toBeGreaterThan(1)

  // Ressort-Filter mit einem echten Ressort des Grossanlasses
  await widget.click()
  await studio.getByLabel('Ressort', { exact: false }).first().click({ force: true })
  await studio.locator('.v-overlay-container').getByRole('option').first().click()
  await expect.poll(rows).toBeLessThan(before)
  expect(await rows()).toBeGreaterThan(0)
  await studio.getByTestId('mode-demo').click()
})
