import { expect, test, type Page } from '@playwright/test'

/**
 * Karten-Widget «Lageplan / Karte» des Infoscreens (zentrale Karte, passiv). Braucht nur das Frontend (Vite), kein
 * Backend und keine Anmeldung: die Entwicklungsansicht /dev/display-template liefert Testdaten aus
 * src/test-fixtures/displayMapData.json (nur Test, nie auf einem echten Infoscreen).
 *
 *   E2E_DISPLAY_MAP=1 E2E_BASE_URL=http://localhost:5173 npx playwright test e2e/display-map.spec.ts
 */
test.skip(process.env.E2E_DISPLAY_MAP !== '1', 'E2E_DISPLAY_MAP=1 erforderlich')
test.use({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1 })

const widgetUrl = (cfg: Record<string, unknown> = {}, extra = 'map=fixture&w=12&h=12') =>
  `/dev/display-template/ga_baubuero?focus=ga_site_map&cfg=${encodeURIComponent(JSON.stringify(cfg))}&${extra}`

async function passive(page: Page) {
  await expect(page.locator('.dl button, .dl input, .dl select, .dl textarea, .dl form, .dl [role="button"], .dl [tabindex]')).toHaveCount(0)
  await expect(page.locator('.dl a[href]')).toHaveCount(0)
  await expect(page.locator('.dl .leaflet-control-zoom, .dl .leaflet-control-layers, .dl .leaflet-popup')).toHaveCount(0)
}

/** Pixelposition eines Punktes in der Karte (Leaflet-Marker bzw. Polygonpfad) relativ zum Kartencontainer. */
async function markerCenters(page: Page) {
  return page.locator('.dl .leaflet-marker-icon').evaluateAll((els) => els.map((el) => {
    const r = (el as HTMLElement).getBoundingClientRect()
    return { x: r.x + r.width / 2, y: r.y + r.height / 2 }
  }))
}

test('Punkte, Fläche, Geländeplan und Beschriftungen erscheinen passiv auf der OSM-Karte', async ({ page }) => {
  await page.goto(widgetUrl())
  const map = page.locator('.dl .leaflet-container')
  await expect(map).toBeVisible()
  await expect(page.locator('.dl .leaflet-marker-icon')).toHaveCount(2)
  await expect(page.locator('.dl path.leaflet-interactive, .dl svg path').first()).toBeVisible()
  await expect(page.locator('.dl img.leaflet-image-layer')).toHaveCount(1)
  // OSM-Grundkarte mit Quellenangabe als Text
  await expect(page.locator('.dl .leaflet-control-attribution')).toContainText('OpenStreetMap')
  // Beschriftungen
  await expect(page.locator('.dl .leaflet-tooltip')).toContainText(['Eingang Nord', 'Baustelle Holzbau', 'Festgelände'])
  await passive(page)

  // Koordinaten: Nord-/Ost-Reihenfolge der Testpunkte stimmt (Baustelle liegt östlich und südlich des Eingangs).
  const [eingang, baustelle] = await markerCenters(page)
  expect(baustelle.x).toBeGreaterThan(eingang.x)
  expect(baustelle.y).toBeGreaterThan(eingang.y)

  // Keine Interaktion: Ziehen und Mausrad ändern den Ausschnitt nicht.
  const before = await markerCenters(page)
  const box = (await map.boundingBox())!
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2)
  await page.mouse.down()
  await page.mouse.move(box.x + box.width / 2 + 150, box.y + box.height / 2 + 80, { steps: 6 })
  await page.mouse.up()
  await page.mouse.wheel(0, -600)
  await page.waitForTimeout(400)
  expect(await markerCenters(page)).toEqual(before)
  await page.mouse.dblclick(box.x + box.width / 2, box.y + box.height / 2)
  await expect(page.locator('.dl .leaflet-popup')).toHaveCount(0)
})

test('Beschriftungen, Objektarten, Plan und Bauprojekt-Filter sind konfigurierbar', async ({ page }) => {
  await page.goto(widgetUrl({ show_labels: false, show_plans: false, kinds: ['poi', 'bauprojekt'] }))
  await expect(page.locator('.dl .leaflet-marker-icon')).toHaveCount(2)
  await expect(page.locator('.dl .leaflet-tooltip')).toHaveCount(0)
  await expect(page.locator('.dl img.leaflet-image-layer')).toHaveCount(0)
  await expect(page.locator('.dl svg path')).toHaveCount(0)

  // Nur Objekte des gewählten Bauprojekts/Bereichs (grfixture002)
  await page.goto(widgetUrl({ group_id: 'grfixture002' }))
  await expect(page.locator('.dl .leaflet-marker-icon')).toHaveCount(1)
  await expect(page.locator('.dl .leaflet-tooltip')).toContainText('Baustelle Holzbau')
})

test('fester Ausschnitt und automatisches Einpassen', async ({ page }) => {
  await page.goto(widgetUrl({ view: 'fixed', center_lat: 47.0015, center_lng: 8.0025, zoom: 17 }))
  await expect(page.locator('.dl .leaflet-marker-icon')).toHaveCount(2)
  const fixed = await markerCenters(page)
  await page.goto(widgetUrl({ view: 'auto' }))
  await expect(page.locator('.dl .leaflet-marker-icon')).toHaveCount(2)
  const auto = await markerCenters(page)
  // Fester Zoom 17 ist deutlich näher als das automatische Einpassen aller Objekte inkl. Plan.
  const spread = (rows: { x: number }[]) => Math.abs(rows[0].x - rows[1].x)
  expect(spread(fixed)).not.toBeCloseTo(spread(auto), 0)
})

test('Ohne Kartendaten erscheint ein neutraler Leerzustand ohne erfundene Objekte', async ({ page }) => {
  await page.goto(widgetUrl({}, 'w=12&h=12'))
  await expect(page.locator('.dl [role="status"]')).toContainText('Keine Kartendaten')
  await expect(page.locator('.dl .leaflet-container')).toHaveCount(0)
  await passive(page)
})

test('Karte passt sich der Layoutzone an (TV, LED-Wall, kleine Zone, Grössenänderung)', async ({ page }) => {
  for (const viewport of [{ width: 1920, height: 1080 }, { width: 2880, height: 1920 }, { width: 1280, height: 720 }]) {
    await page.setViewportSize(viewport)
    await page.goto(widgetUrl({}, 'map=fixture&w=6&h=6'))
    const map = page.locator('.dl .leaflet-container')
    await expect(map).toBeVisible()
    const zone = (await page.locator('.dl').boundingBox())!
    const box = (await map.boundingBox())!
    expect(box.width).toBeGreaterThan(zone.width * 0.45)
    expect(box.width).toBeLessThanOrEqual(zone.width * 0.5 + 2)
    expect(box.height).toBeGreaterThan(zone.height * 0.45)
    // alle Marker liegen innerhalb der Karte
    for (const c of await markerCenters(page)) {
      expect(c.x).toBeGreaterThan(box.x)
      expect(c.x).toBeLessThan(box.x + box.width)
      expect(c.y).toBeGreaterThan(box.y)
      expect(c.y).toBeLessThan(box.y + box.height)
    }
  }
  // Grössenänderung zur Laufzeit: Karte rechnet neu und behält alle Objekte im Ausschnitt.
  await page.setViewportSize({ width: 1920, height: 1080 })
  await page.goto(widgetUrl({}, 'map=fixture&w=12&h=12'))
  await expect(page.locator('.dl .leaflet-marker-icon')).toHaveCount(2)
  await page.setViewportSize({ width: 960, height: 540 })
  await page.waitForTimeout(500)
  const box = (await page.locator('.dl .leaflet-container').boundingBox())!
  expect(box.width).toBeLessThanOrEqual(960)
  for (const c of await markerCenters(page)) {
    expect(c.x).toBeGreaterThan(0)
    expect(c.x).toBeLessThan(960)
    expect(c.y).toBeLessThan(540)
  }
})

test('Baubüro-Layout zeigt die Karte im Wechsel mit der Gesamtübersicht', async ({ page }) => {
  await page.goto('/dev/display-template/ga_baubuero?map=fixture')
  await expect(page.locator('.dl')).toBeVisible()
  await expect(page.locator('.dl .leaflet-container')).toBeVisible({ timeout: 25_000 })
  await expect(page.locator('.dl .leaflet-marker-icon')).toHaveCount(2)
  await expect(page.locator('.dl .leaflet-control-zoom')).toHaveCount(0)
})
