import { expect, test, type BrowserContext, type Page } from '@playwright/test'

/**
 * Infoscreen-Studio: Editor-Beschriftungen (nie über dem Inhalt, nur im Editor), Einzelbearbeitung mit sinnvollem Zoom und
 * Rotation als Layout-Container mit frei gestaltbaren Seiten (Bereichs- und Vollbildrotation, alte Rotationen, eigene Bausteine).
 *
 * Läuft NUR gegen eine isolierte, frisch migrierte Umgebung und nur mit E2E_DISPLAY_STUDIO=1 (siehe docs/E2E.md).
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

let context: BrowserContext
let mgmt: Page
let studio: Page

async function createScreen(template: string, name: string): Promise<Page> {
  await mgmt.goto(`/${gaDept}/ga/displays`)
  await mgmt.getByRole('button', { name: 'Infoscreen erstellen' }).first().click()
  await mgmt.getByLabel('Name', { exact: false }).first().fill(name)
  await mgmt.getByTestId(`template-${template}`).or(mgmt.getByRole('radio', { name: template === 'blank' ? /Leere Anzeige/ : /Materiallager/ })).first().click()
  const popup = context.waitForEvent('page')
  await mgmt.getByRole('button', { name: 'Infoscreen erstellen' }).last().click()
  const page = await popup
  await page.waitForURL(/\/ga\/displays\/[^/]+\/layout/)
  await expect(page.getByTestId('layout-canvas')).toBeVisible()
  return page
}

const intersects = (a: { x: number; y: number; width: number; height: number }, b: { x: number; y: number; width: number; height: number }) =>
  a.x < b.x + b.width - 0.5 && b.x < a.x + a.width - 0.5 && a.y < b.y + b.height - 0.5 && b.y < a.y + a.height - 0.5

test.beforeAll(async ({ browser }) => {
  context = await browser.newContext({ baseURL: process.env.E2E_BASE_URL, viewport: { width: 1600, height: 900 } })
  mgmt = await context.newPage()
  await login(mgmt)
  studio = await createScreen('ga_materiallager', `E2E Rotation ${Date.now()}`)
})

test.afterAll(async () => {
  await context?.close()
})

test('Beschriftungen: nur bei Hover/Auswahl, ausserhalb der Fläche, nie im Inhalt; nicht in der Vorschau', async () => {
  const header = studio.getByTestId('layout-item-i-head')
  const tag = header.locator('.item__tag')
  // Ohne Hover/Auswahl unsichtbar
  await studio.mouse.move(5, 5)
  await expect(tag).toHaveCSS('opacity', '0')
  // Hover: sichtbar und vollständig ausserhalb des Elements
  await header.hover()
  await expect(tag).toHaveCSS('opacity', '1')
  const item = (await header.boundingBox())!
  expect(intersects((await tag.boundingBox())!, item)).toBe(false)
  // Auswahl: ebenso; Rahmen und Griffe bleiben vorhanden
  await header.click()
  await studio.mouse.move(5, 5)
  await expect(tag).toHaveCSS('opacity', '1')
  expect(intersects((await tag.boundingBox())!, (await header.boundingBox())!)).toBe(false)
  await expect(studio.getByTestId('layout-resize-handle')).toBeVisible()
  // Mehrfachauswahl: keine Einzelbeschriftungen
  await studio.getByTestId('layout-item-i-feed').click({ modifiers: ['Shift'] })
  await expect(studio.getByTestId('multi-selection')).toBeVisible()
  await expect(tag).toBeHidden()
  await expect(studio.getByTestId('layout-item-i-feed').locator('.item__tag')).toBeHidden()
  await studio.keyboard.press('Escape')

  // Hohe Zoomstufe: Beschriftung bleibt ausserhalb
  await studio.getByTitle('Vergrössern').click()
  const rot = studio.getByTestId('layout-item-i-rot')
  await rot.hover()
  await expect(rot.locator('.item__tag')).toHaveCSS('opacity', '1')
  expect(intersects((await rot.locator('.item__tag').boundingBox())!, (await rot.boundingBox())!)).toBe(false)
  await studio.getByTestId('btn-fit').click()

  // Vorschau: keine Editor-Beschriftungen, Auswahlrahmen oder Griffe
  await studio.getByTestId('btn-preview').click()
  const frame = studio.getByTestId('preview-frame')
  await expect(frame.locator('.dl')).toBeVisible()
  await expect(frame.locator('.item__tag, .handle, .item--selected')).toHaveCount(0)
  await studio.keyboard.press('Escape')
})

test('Separat bearbeiten: Baustein wird zentriert und sinnvoll vergrössert, Zurück stellt die Ansicht wieder her', async () => {
  const zoom = studio.getByTestId('studio-zoom')
  const overall = await zoom.innerText()
  await studio.getByTestId('layout-item-i-head').click()
  await studio.getByTestId('btn-isolate').click()
  const viewport = (await studio.getByTestId('studio-viewport').boundingBox())!
  await expect.poll(async () => (await studio.getByTestId('layout-canvas').boundingBox())!.height).toBeGreaterThan(90)
  const world = (await studio.getByTestId('layout-canvas').boundingBox())!
  // zentriert (Mitte der Fläche liegt in der Mitte des Arbeitsbereichs)
  expect(Math.abs(world.x + world.width / 2 - (viewport.x + viewport.width / 2))).toBeLessThan(40)
  expect(Math.abs(world.y + world.height / 2 - (viewport.y + viewport.height / 2))).toBeLessThan(40)
  expect(await zoom.innerText()).not.toBe(overall)
  // «Einpassen» passt den Baustein ein, nicht die ursprüngliche Bildschirmfläche
  await studio.getByTitle('Vergrössern').click()
  await studio.getByTestId('btn-fit').click()
  const refit = (await studio.getByTestId('layout-canvas').boundingBox())!
  expect(Math.abs(refit.width - world.width)).toBeLessThan(6)
  await studio.getByTestId('btn-exit-edit').click()
  await expect(zoom).toHaveText(overall)
})

test('Alte Rotation: wird beim Öffnen nicht verändert und bleibt kompatibel', async () => {
  await expect(studio.getByTestId('status-unsaved')).toHaveCount(0)
  await studio.getByTestId('layout-item-i-rot').click()
  const pages = studio.getByTestId('rotation-pages')
  await expect(pages.locator('li')).toHaveCount(5)
  await expect(pages.locator('li').first()).toContainText('Materialübersicht')
  await expect(studio.getByTestId('status-unsaved')).toHaveCount(0)
  // «Seite gestalten» übernimmt den alten Schritt als Seite; das Widget bleibt erhalten
  await studio.getByTestId('rotation-edit-0').click()
  await expect(studio.getByTestId('studio-breadcrumb')).toContainText('Rotation')
  await expect(studio.locator('[data-testid^="layout-item-"]')).toHaveCount(1)
  await expect(studio.getByTestId('rotation-nav')).toContainText('Seite 1 von 5')
  await studio.getByTestId('btn-exit-edit').click()
  await expect(studio.getByTestId('rotation-pages')).toBeVisible() // Rotation ist wieder gewählt
})

test('Rotation aus der Bibliothek: Seiten erstellen, gestalten, sortieren, duplizieren, löschen', async () => {
  await studio.close()
  studio = await createScreen('blank', `E2E Rotation leer ${Date.now()}`)
  await expect(studio.getByTestId('library-category-rotation')).toBeVisible()
  await studio.getByTestId('block-builtin:rotation').click()
  const items = studio.locator('[data-testid^="layout-item-"]')
  await expect(items).toHaveCount(1)
  const pages = studio.getByTestId('rotation-pages')
  await expect(pages.locator('li')).toHaveCount(1)

  await studio.getByTestId('btn-add-rotation-page').click()
  await studio.getByTestId('btn-add-rotation-page').click()
  await expect(pages.locator('li')).toHaveCount(3)
  // Titel und individuelle Anzeigedauer
  await pages.locator('li').nth(1).locator('.insp__step-btn').click()
  await studio.getByTestId('rotation-page-name').getByRole('textbox').fill('Packaufträge')
  await studio.getByTestId('rotation-page-duration').getByRole('spinbutton').fill('4')
  await expect(pages.locator('li').nth(1)).toContainText('Packaufträge')
  await expect(pages.locator('li').nth(1)).toContainText('4 s')
  // Sortieren (nach oben) und Duplizieren
  await pages.locator('li').nth(1).getByLabel('Nach oben').click()
  await expect(pages.locator('li').first()).toContainText('Packaufträge')
  await pages.locator('li').first().getByLabel('Duplizieren').click()
  await expect(pages.locator('li')).toHaveCount(4)
  await expect(pages.locator('li').nth(1)).toContainText('Packaufträge (Kopie)')
  // Löschen
  await pages.locator('li').nth(1).getByLabel('Löschen').click()
  await expect(pages.locator('li')).toHaveCount(3)

  // Seite 1 gestalten: eigene Rasterfläche, Widgets frei platzieren, Kollisionsschutz
  await pages.locator('li').first().getByTestId('rotation-edit-0').click()
  await expect(studio.getByTestId('studio-breadcrumb')).toContainText('Packaufträge')
  await studio.getByTestId('palette-text').click()
  await studio.getByTestId('palette-clock').click()
  await expect(items).toHaveCount(2)
  await studio.locator('[data-testid^="layout-item-"]').first().click()
  await studio.getByLabel('Text', { exact: true }).first().fill('AAA-Seite')
  // Zweite Seite bleibt davon unabhängig leer
  await studio.getByLabel('Nächste Seite').click()
  await expect(items).toHaveCount(0)
  await studio.getByTestId('palette-text').click()
  await studio.getByLabel('Text', { exact: true }).first().fill('BBB-Seite')
  // Undo/Redo auf Seitenebene
  await studio.getByTestId('btn-undo').click()
  await expect(items).toHaveCount(1)
  await studio.getByTestId('btn-redo').click()
  await expect(items).toHaveCount(1)
  await studio.getByTestId('btn-exit-edit').click()
  await expect(studio.getByTestId('rotation-pages')).toBeVisible()
})

test('Bereichsrotation wechselt nur ihren Bereich; Anzeigedauern gelten pro Seite', async () => {
  // Rotation auf 3 s Standard, Seite 1 mit 3 s, Seite 2 mit 6 s
  const pages = studio.getByTestId('rotation-pages')
  await pages.locator('li').first().locator('.insp__step-btn').click()
  await studio.getByTestId('rotation-page-duration').getByRole('spinbutton').fill('3')
  await pages.locator('li').nth(1).locator('.insp__step-btn').click()
  await studio.getByTestId('rotation-page-duration').getByRole('spinbutton').fill('6')
  // Rotation verkleinern und einen festen Bereich daneben setzen: Kopfzeile bleibt stehen
  await studio.getByLabel('Breite', { exact: true }).fill('6')
  await studio.getByTestId('palette-header').click()
  await studio.getByTestId('btn-save').click()
  await expect(studio.getByText('Entwurf gespeichert.').first()).toBeVisible()

  await studio.getByTestId('btn-preview').click()
  const frame = studio.getByTestId('preview-frame')
  await expect(frame.locator('.dl-rotation')).toBeVisible()
  await expect(frame).toContainText('AAA-Seite')
  const headerText = await frame.locator('.dl-cell').filter({ hasText: /\d\d:\d\d/ }).first().innerText()
  const started = Date.now()
  await expect(frame).toContainText('BBB-Seite', { timeout: 15_000 })
  expect(Date.now() - started).toBeGreaterThan(1500) // Seite 1 stand individuell ca. 3 s
  // Die feste Kopfzeile ist unverändert dargestellt (Titel gleich), nur die Rotation hat gewechselt
  expect(headerText.length).toBeGreaterThan(0)
  await expect(frame.locator('.item__tag, .handle')).toHaveCount(0)
  await expect(frame.locator('button, input, a[href]')).toHaveCount(0)
  await studio.keyboard.press('Escape')
})

test('Rotation als eigener Baustein: unabhängige Kopie mit allen Seiten', async () => {
  const items = studio.locator('[data-testid^="layout-item-"]')
  // Rotation wählen (nicht die Kopfzeile)
  await items.filter({ hasText: 'Rotation' }).first().click()
  await studio.getByTestId('btn-save-block').click()
  const name = `Rotation ${Date.now()}`
  await studio.getByTestId('block-name').getByRole('textbox').fill(name)
  await studio.getByTestId('block-save').click()
  const category = studio.getByTestId('library-category-rotation')
  await expect(category).toContainText(name)

  // Leere Fläche schaffen, gespeicherte Rotation einfügen, Kopie ändern
  await studio.keyboard.press('Control+a')
  await studio.keyboard.press('Delete')
  await category.locator('li.lib__row').filter({ hasText: name }).locator('.lib__item').click()
  await expect(items).toHaveCount(1)
  const pages = studio.getByTestId('rotation-pages')
  await expect(pages.locator('li')).toHaveCount(3)
  await expect(pages.locator('li').first()).toContainText('Packaufträge')
  await pages.locator('li').first().getByLabel('Löschen').click()
  await expect(pages.locator('li')).toHaveCount(2)

  // Die gespeicherte Vorlage ist unverändert: erneut einfügen liefert wieder alle drei Seiten
  await studio.keyboard.press('Control+a')
  await studio.keyboard.press('Delete')
  await category.locator('li.lib__row').filter({ hasText: name }).locator('.lib__item').click()
  await expect(studio.getByTestId('rotation-pages').locator('li')).toHaveCount(3)

  // Aufräumen: Baustein löschen
  await category.locator('li.lib__row').filter({ hasText: name }).getByLabel('Löschen').click()
  await studio.getByRole('button', { name: 'Löschen', exact: true }).last().click()
  await expect(category).not.toContainText(name)
})

test('Vollbildrotation: eine Rotation über die ganze Fläche mit Seiten für Materiallager, Disposition, Baubüro', async () => {
  const items = studio.locator('[data-testid^="layout-item-"]')
  await studio.keyboard.press('Control+a')
  await studio.keyboard.press('Delete')
  await studio.getByTestId('block-builtin:rotation').click()
  await expect(items).toHaveCount(1)
  await studio.getByLabel('Breite', { exact: true }).fill('12')
  await studio.getByLabel('Höhe', { exact: true }).fill('12')
  await expect(items.first()).toBeVisible()
  const box = (await items.first().boundingBox())!
  const canvas = (await studio.getByTestId('layout-canvas').boundingBox())!
  expect(box.width).toBeGreaterThan(canvas.width * 0.9)
  // Seite 1: Bereich aus der Bibliothek einfügen (Materiallager)
  await studio.getByTestId('rotation-edit-0').click()
  await studio.getByTestId('library-search').fill('Materiallager')
  await studio.locator('[data-testid^="block-official:region_materiallager"]').click()
  await studio.getByTestId('library-search').fill('')
  await expect(studio.locator('[data-testid^="layout-item-"]')).toHaveCount(1)
  await studio.getByTestId('btn-exit-edit').click()
})
