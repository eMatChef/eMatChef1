import { test, expect, type Page } from '@playwright/test'

/**
 * Beschaffung vom Materialwunsch bis zum Wareneingang (Demo-Konto ga-mw, Demo «Grossanlass Event 2027»):
 * Wunsch bündeln, mehrere Teil-Bestellungen (Offerte und telefonische Absprache), Leihe plus Kauf ohne Doppeldeckung,
 * bestätigte Überdeckung, Storno mit Ersatzbestellung, Teillieferung mit Gegenbuchung, Material/Fahrzeuge getrennt und
 * konsistente Mengen in Beschaffung, Bauprojekt-Fortschritt und Displays.
 * SCHREIBT (Positionen, Bestellungen, Eingänge) — nur gegen die isolierte Wegwerf-Umgebung (docs/E2E.md), nie mvdb.
 * Aktiv nur mit E2E_GA_PROCUREMENT=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD.
 */
const email = process.env.E2E_USER_EMAIL
const password = process.env.E2E_USER_PASSWORD
const WISH = 'Dachlatten 30×50'

test.describe.serial('GA Beschaffung Wunsch → Bestellungen → Wareneingang', () => {
  test.skip(process.env.E2E_GA_PROCUREMENT !== '1' || !email || !password, 'E2E_GA_PROCUREMENT=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD nötig')
  test.setTimeout(150_000)

  let departmentId = ''
  let lineId = ''
  let lineLabel = ''
  let wishQuantity = 0
  const base = () => `/api/departments/${departmentId}/grossanlass/beschaffung`

  async function login(page: Page): Promise<string> {
    await page.goto('/login')
    await page.getByRole('textbox', { name: /E-Mail/i }).fill(email!)
    await page.locator('input[type="password"]').first().fill(password!)
    await page.getByRole('button', { name: /Anmelden/i }).click()
    await page.waitForURL((url) => !/\/login(?:\?|$)/.test(url.pathname), { timeout: 60_000 })
    return /\/([0-9a-f]{12})\//.exec(page.url())![1]
  }

  async function line(page: Page): Promise<Record<string, any>> {
    const res = await page.request.get(`${base()}/lines`)
    expect(res.ok(), await res.text()).toBe(true)
    return (await res.json()).find((l: { id: string }) => l.id === lineId)
  }

  /** Einzeln gestartete Tests: Position des ersten Tests über den Wunsch wiederfinden. */
  async function ensureLine(page: Page) {
    if (lineId) return
    departmentId = departmentId || (/\/([0-9a-f]{12})\//.exec(page.url())![1])
    const overview = await (await page.request.get(`${base()}/bedarf`)).json()
    const found = overview.lines.find((l: { source_wishes: Array<{ label: string }> }) => l.source_wishes.some((w) => w.label === WISH))
    expect(found, 'Position aus dem ersten Test').toBeTruthy()
    lineId = found.id
    lineLabel = found.label
  }

  const panel = (page: Page) => page.getByTestId(`orders-panel-${lineId}`)

  async function openOrders(page: Page) {
    await page.goto(`/${departmentId}/ga/procurement/orders?line=${lineId}`)
    await expect(panel(page)).toBeVisible({ timeout: 90_000 })
  }

  async function fillOrder(page: Page, values: Record<string, string>) {
    for (const [label, value] of Object.entries(values)) {
      await panel(page).getByLabel(label, { exact: true }).fill(value)
    }
  }

  test('wish → demand → bundled line with a quote', async ({ page }) => {
    departmentId = await login(page)
    await page.goto(`/${departmentId}/ga/procurement/demand`)
    const rows = page.locator('[data-testid="pool-list"] .pool-row:not(.pool-row--vehicle)')
    await expect(rows.first()).toBeVisible({ timeout: 90_000 })
    const overview = await (await page.request.get(`${base()}/bedarf`)).json()
    const wish = overview.pool.find((w: { label: string }) => w.label === WISH)
    if (!wish) {
      // Wiederholter Lauf auf derselben Umgebung: Position aus dem früheren Lauf weiterverwenden (noch ohne Bestellungen)
      const existing = overview.lines.find((l: { source_wishes: Array<{ label: string }> }) => l.source_wishes.some((w) => w.label === WISH))
      expect(existing, `Demo-Wunsch «${WISH}» fehlt`).toBeTruthy()
      lineId = existing.id
      lineLabel = existing.label
      wishQuantity = existing.quantity
      const current = (await (await page.request.get(`${base()}/lines`)).json()).find((l: { id: string }) => l.id === lineId)
      expect(current.orders ?? []).toHaveLength(0)
      if (!current.quotes.length) {
        expect((await page.request.post(`${base()}/lines/${lineId}/quotes`, { data: { supplier: 'Holzhandel Offerte AG', amount_chf: 300 } })).ok()).toBe(true)
      }
      return
    }
    wishQuantity = wish.quantity

    // Zuständigkeit zentral und Position anlegen (UI: Pool-Auswahl → Kategorisieren → Position erstellen)
    await page.locator('.pool-search input').fill(WISH)
    await rows.filter({ has: page.locator('.pool-row__main strong', { hasText: WISH }) }).locator('input[type="checkbox"]').check()
    await page.getByTestId('pool-categorize').click()
    const input = page.locator('.v-dialog .category-picker input').first()
    await input.click()
    await page.locator('.v-overlay--active .v-list-item').first().click()
    await page.getByRole('combobox', { name: /Beschaffungszuständigkeit/ }).click({ force: true })
    await page.locator('.v-overlay--active .v-list-item', { hasText: 'Zentrale Beschaffung' }).click()
    await page.getByTestId('categorize-confirm').click()
    // Ähnliche Positionen werden nur vorgeschlagen; ohne passenden Treffer öffnet direkt «Bündeln prüfen»
    const bundle = page.locator('.v-dialog', { hasText: 'Bündeln prüfen' })
    const createNew = page.getByTestId('similar-create-new')
    await expect(bundle.or(createNew)).toBeVisible({ timeout: 60_000 })
    if (await createNew.isVisible()) {
      await createNew.click()
    }
    await expect(bundle).toBeVisible({ timeout: 30_000 })
    await bundle.getByRole('button', { name: /bündeln/i }).last().click()
    await expect.poll(async () => {
      const after = await (await page.request.get(`${base()}/bedarf`)).json()
      return after.lines.some((l: { source_wishes: Array<{ id: string }> }) => l.source_wishes.some((w) => w.id === wish.id))
    }, { timeout: 30_000 }).toBe(true)
    const after = await (await page.request.get(`${base()}/bedarf`)).json()
    const created = after.lines.find((l: { source_wishes: Array<{ id: string }> }) => l.source_wishes.some((w) => w.id === wish.id))
    lineId = created.id
    lineLabel = created.label
    expect(created.quantity).toBe(wishQuantity)

    // Offerte (Anfrage-Antwort) an der Position; sie deckt noch keinen Bedarf
    const quote = await page.request.post(`${base()}/lines/${lineId}/quotes`, { data: { supplier: 'Holzhandel Offerte AG', amount_chf: 300 } })
    expect(quote.ok(), await quote.text()).toBe(true)
    const l = await line(page)
    expect(l.quantity_covered).toBe(0)
    expect(l.quantity_open).toBe(wishQuantity)
  })

  test('two partial orders (quote + phone agreement), over-coverage needs confirmation, cancel and replace', async ({ page }) => {
    await login(page)
    await openOrders(page)
    const total = (await line(page)).quantity as number
    const partA = Math.floor(total / 2)

    // 1. Teilmenge aus der Offerte
    await fillOrder(page, { 'Bestellte Menge': String(partA), 'Kosten CHF': '300' })
    await panel(page).locator('form button[type="submit"]').click()
    await expect.poll(async () => (await line(page)).quantity_ordered, { timeout: 30_000 }).toBe(partA)

    // 2. Rest als telefonische Absprache ohne Offerte (Menge leer = offener Rest; weitere Bestellungen starten ohne Offerte-Grundlage)
    await openOrders(page)
    await fillOrder(page, { Lieferant: 'Zimmerei Muri (telefonisch)', 'Kosten CHF': '150' })
    await panel(page).locator('form button[type="submit"]').click()
    await expect.poll(async () => (await line(page)).quantity_ordered, { timeout: 30_000 }).toBe(total)
    const afterTwo = await line(page)
    expect(afterTwo.orders.filter((o: { status: string }) => o.status !== 'cancelled')).toHaveLength(2)
    expect(afterTwo.orders.map((o: { agreement_kind: string }) => o.agreement_kind).sort()).toEqual(['phone', 'quote'])
    expect(afterTwo.quantity_open).toBe(0)
    expect(afterTwo.quantity_over).toBe(0)

    // 3. Überdeckung: erst nach ausdrücklicher Bestätigung
    await openOrders(page)
    await fillOrder(page, { Lieferant: 'Dritte AG', 'Bestellte Menge': '2', 'Kosten CHF': '20' })
    await panel(page).locator('form button[type="submit"]').click()
    await expect(page.getByTestId('overcoverage-text')).toContainText('2 zu viel')
    expect((await line(page)).quantity_over).toBe(0)
    await page.getByTestId('overcoverage-confirm').click()
    await expect.poll(async () => (await line(page)).quantity_over, { timeout: 30_000 }).toBe(2)

    // 4. Storno mit Grund, Bedarf ist wieder genau gedeckt; Bestellung bleibt als Historie
    await openOrders(page)
    await panel(page).locator('[data-testid^="order-"]', { hasText: 'Dritte AG' }).getByRole('button', { name: 'Stornieren' }).click()
    await page.getByLabel('Grund', { exact: true }).fill('Doppelt bestellt')
    await page.locator('.dialog, .v-dialog').getByRole('button', { name: 'Stornieren' }).last().click()
    await expect.poll(async () => (await line(page)).quantity_over, { timeout: 30_000 }).toBe(0)
    const cancelled = (await line(page)).orders.find((o: { status: string }) => o.status === 'cancelled')
    expect(cancelled.cancel_reason).toBe('Doppelt bestellt')

    // 5. Ersatzbestellung nach Storno der Absprache ohne Eingang
    await openOrders(page)
    await panel(page).locator('[data-testid^="order-"]', { hasText: 'Zimmerei Muri' }).getByRole('button', { name: 'Stornieren' }).click()
    await page.getByLabel('Grund', { exact: true }).fill('Lieferant fällt aus')
    await page.locator('.dialog, .v-dialog').getByRole('button', { name: 'Stornieren' }).last().click()
    await expect.poll(async () => (await line(page)).quantity_open, { timeout: 30_000 }).toBe(total - partA)
    await openOrders(page)
    await fillOrder(page, { Lieferant: 'Dachmaterial Rothenburg (telefonisch)', 'Kosten CHF': '160' })
    await panel(page).locator('form button[type="submit"]').click()
    await expect.poll(async () => (await line(page)).quantity_ordered, { timeout: 30_000 }).toBe(total)
    expect((await line(page)).quantity_over).toBe(0)
  })

  test('partial delivery and reversal in the goods receipt, reflected in the procurement line', async ({ page }) => {
    departmentId = await login(page)
    await ensureLine(page)
    const l = await line(page)
    const receivedBefore = l.quantity_received as number
    const order = l.orders.find((o: { status: string; supplier: string }) => o.status === 'confirmed' && /Rothenburg/.test(o.supplier))
    expect(order.ordered_quantity).toBeGreaterThan(2)
    await page.goto(`/${departmentId}/ga/material/goods-receipt`)
    const shipment = page.locator('.shipment-row', { hasText: 'Dachmaterial Rothenburg' })
    await expect(shipment).toBeVisible({ timeout: 90_000 })
    await shipment.getByRole('button', { name: 'Öffnen' }).click()
    const card = page.locator('.inbound-card', { hasText: 'Dachmaterial Rothenburg' }).filter({ hasText: lineLabel })
    await expect(card).toBeVisible({ timeout: 30_000 })
    await card.getByLabel('Menge', { exact: true }).fill('3')
    await page.getByRole('button', { name: 'Wareneingang bestätigen' }).click()
    await expect.poll(async () => (await line(page)).quantity_received, { timeout: 30_000 }).toBe(receivedBefore + 3)
    expect((await line(page)).status).toBe('teilweise_erhalten')
    // Eingang ist nicht automatisch einem Wunsch zugeteilt
    expect((await line(page)).received_quantity_sum).toBe(0)

    // Gegenbuchung: Grund ist Pflicht, die Historie bleibt
    await shipment.getByRole('button', { name: 'Öffnen' }).click()
    await expect(card).toBeVisible({ timeout: 30_000 })
    await card.locator('summary', { hasText: 'Eingang korrigieren' }).click()
    await card.getByLabel('Zurückbuchen', { exact: true }).fill('1')
    const submit = card.getByRole('button', { name: 'Gegenbuchung erfassen' })
    await expect(submit).toBeDisabled()
    await card.getByLabel('Grund', { exact: true }).fill('Falsch gezählt')
    await submit.click()
    await expect.poll(async () => (await line(page)).quantity_received, { timeout: 30_000 }).toBe(receivedBefore + 2)
    const movements = await (await page.request.get(`${base()}/zusagen/${order.commitment_id}/movements`)).json()
    // Die ursprüngliche Buchung bleibt, die Gegenbuchung kommt mit Grund dazu
    expect(movements.slice(-2).map((m: { kind: string }) => m.kind)).toEqual(['received', 'receipt_reversed'])
    expect(movements.at(-1).note).toBe('Falsch gezählt')
  })

  test('material and vehicles are separated and quantities agree across procurement, site progress and displays', async ({ page }) => {
    departmentId = await login(page)
    await ensureLine(page)
    await page.goto(`/${departmentId}/ga/procurement/orders`)
    await expect(page.getByTestId('orders-kind-filter')).toBeVisible({ timeout: 90_000 })
    const filter = page.getByTestId('orders-kind-filter')
    await filter.click()
    await page.locator('.v-overlay--active .v-list-item', { hasText: 'Fahrzeuge' }).click()
    await expect(page.getByText('Teleskoplader').first()).toBeVisible()
    await expect(page.getByText(lineLabel)).toHaveCount(0)
    await filter.click()
    await page.locator('.v-overlay--active .v-list-item', { hasText: 'Material' }).click()
    await expect(page.getByText(lineLabel).first()).toBeVisible()
    await expect(page.getByText('Teleskoplader')).toHaveCount(0)

    // Menge der Position = Summe ihrer Quellen; Eingang je Bestellung = Eingang der Position
    const l = await line(page)
    const active = l.orders.filter((o: { status: string }) => o.status !== 'cancelled')
    expect(l.quantity_ordered).toBe(active.reduce((s: number, o: { ordered_quantity: number }) => s + o.ordered_quantity, 0))
    expect(l.quantity_received).toBe(active.reduce((s: number, o: { received_quantity: number }) => s + o.received_quantity, 0))
    expect(l.quantity_open).toBe(Math.max(0, l.quantity - l.quantity_covered))

    // Bauprojekt-Fortschritt des Wunsches: gedeckt wie die Position, eingegangen nur nach bewusster Zuteilung (hier keine)
    const progress = await (await page.request.get(`/api/departments/${departmentId}/grossanlass/uebersicht/progress`)).json()
    const item = progress.items.find((i: { procurement_line_id: string | null }) => i.procurement_line_id === lineId)
    expect(item, 'Fortschritt zur Position').toBeTruthy()
    expect(item.covered).toBe(Math.min(item.required, l.quantity_covered))
    expect(item.received).toBe(0)

    // Displays und Material-Leitstand laden ohne Fehler
    const errors: string[] = []
    page.on('pageerror', (e) => errors.push(e.message))
    await page.goto(`/${departmentId}/ga/displays`)
    await expect(page.locator('main')).toBeVisible({ timeout: 90_000 })
    await page.goto(`/${departmentId}/ga/material`)
    await expect(page.locator('main')).toBeVisible({ timeout: 90_000 })
    expect(errors).toEqual([])
  })

  test('seeded demo flow: loan plus purchase covers the demand exactly and a cancelled order stays in the history', async ({ page }) => {
    departmentId = await login(page)
    const lines: Array<Record<string, any>> = await (await page.request.get(`${base()}/lines`)).json()
    const bitumen = lines.find((l) => l.label === 'Bitumenschindeln anthrazit')
    expect(bitumen, 'Demo-Position «Bitumenschindeln anthrazit» fehlt (app:demo:sync)').toBeTruthy()
    // 8 benötigt = 3 Leihe + 5 Kauf, nichts doppelt gedeckt; die erste Bestellung ist storniert
    expect([bitumen.quantity, bitumen.quantity_loaned, bitumen.quantity_ordered, bitumen.quantity_open, bitumen.quantity_over]).toEqual([8, 3, 5, 0, 0])
    expect(bitumen.orders.map((o: { status: string }) => o.status)).toEqual(['cancelled', 'confirmed'])
    await page.goto(`/${departmentId}/ga/procurement/orders?line=${bitumen.id}`)
    const card = page.getByTestId(`orders-panel-${bitumen.id}`)
    await expect(card).toBeVisible({ timeout: 90_000 })
    await expect(card.getByTestId('orders-over')).toHaveCount(0)
    await card.locator('summary', { hasText: 'stornierte Bestellung' }).click()
    await expect(card).toContainText('Lieferant kann den Termin nicht halten')
  })
})
