import { test, expect, type Browser, type Page } from '@playwright/test'

/**
 * GA Logistik Ende-zu-Ende auf dem Demo-Seed «Grossanlass Event»: Disposition, Überführung eines Transportwunsches, beide
 * Aufgabenansichten (nur Freigegebenes) und der QR-Ablauf (Pack übernehmen, am Ziel-Standort abliefern).
 *
 * Es gibt keine implizite Datenabhängigkeit: alle Bedarfe stammen aus dem Seed (`app:demo:sync`) einer frisch migrierten
 * Wegwerf-Datenbank (docs/E2E.md, `scripts/e2e-ga-logistics.sh`). SCHREIBT — nie gegen mvdb oder die Dev-Datenbank.
 * Aktiv mit E2E_GA_FLOW=1, E2E_USER_EMAIL (ga-mw), E2E_DRIVER_EMAIL (ga-lw) und E2E_USER_PASSWORD (gemeinsames Demo-Passwort).
 */
const mwEmail = process.env.E2E_USER_EMAIL
const driverEmail = process.env.E2E_DRIVER_EMAIL
const password = process.env.E2E_USER_PASSWORD
const DEPT = process.env.E2E_DEPARTMENT_ID || ''

type Need = {
  id: string
  title: string
  cargo: string
  status: string
  released: boolean
  einsatz_id: string | null
  purpose?: string
  tour_id: string | null
  chauffeur_user_id: string | null
  pack_ids: string[]
  pack_status: string
  converted_from_vehicle_need_id?: string | null
}
type Overview = { can_manage: boolean; needs: Need[]; tours: Array<{ id: string; name: string; status: string }> }

test.describe.serial('GA Logistik: Disposition bis QR-Ablieferung', () => {
  test.skip(process.env.E2E_GA_FLOW !== '1' || !mwEmail || !driverEmail || !password || !DEPT, 'E2E_GA_FLOW=1, E2E_USER_EMAIL, E2E_DRIVER_EMAIL, E2E_USER_PASSWORD, E2E_DEPARTMENT_ID nötig')
  test.setTimeout(240_000)
  test.use({ ignoreHTTPSErrors: true })

  const base = `/api/departments/${DEPT}/grossanlass`
  const problems: string[] = []
  let mw: Page
  let driver: Page

  async function login(browser: Browser, email: string): Promise<Page> {
    const context = await browser.newContext({ ignoreHTTPSErrors: true })
    const page = await context.newPage()
    await page.goto('/login', { timeout: 120_000 })
    await page.getByRole('textbox', { name: /E-Mail/i }).fill(email)
    await page.locator('input[type="password"]').first().fill(password!)
    await page.getByRole('button', { name: /Anmelden/i }).click()
    await page.waitForURL((url) => !/\/login(?:\?|$)/.test(url.pathname), { timeout: 60_000 })
    page.on('pageerror', (err) => !/WebSocket/.test(err.message) && problems.push(`pageerror: ${err.message}`))
    return page
  }
  const overview = async (): Promise<Overview> => (await (await mw.request.get(`${base}/logistics/dispatch`)).json()) as Overview
  const byCargo = (data: Overview, text: string) => data.needs.find((need) => need.cargo.includes(text))

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(300_000) // Vite kompiliert beim ersten Login-Aufruf
    mw = await login(browser, mwEmail!)
    driver = await login(browser, driverEmail!)
  })
  test.afterAll(async () => {
    await mw?.context().close()
    await driver?.context().close()
  })

  test('Seed enthält alle Stufen: Baustelleneinsatz, Transportwunsch, disponiert, freigegeben', async () => {
    const data = await overview()
    expect(data.can_manage).toBe(true)
    const siteUse = byCargo(data, 'Podest-Elemente entladen')!
    expect(siteUse.purpose).toBe('site_use')
    expect(siteUse.einsatz_id).toBeNull()
    const wish = byCargo(data, 'Zeltmaterial abholen')!
    expect(wish, JSON.stringify(data.needs.map((need) => [need.id, need.cargo, need.status, need.tour_id]))).toBeTruthy()
    expect(wish.purpose).toBe('transport')
    expect(wish.einsatz_id).toBeNull()
    const planned = data.needs.find((need) => need.einsatz_id && need.tour_id && !need.released && need.cargo.includes('Schwartenbretter'))
    expect(planned, 'disponierte, nicht freigegebene Fahrt (Abholung Holz)').toBeTruthy()
    const released = data.needs.filter((need) => need.einsatz_id && need.released)
    const lader = released.find((need) => need.cargo.includes('Teleskoplader'))!
    expect(lader.pack_status).toBe('trip_released')
    expect(lader.tour_id).toBeTruthy()
  })

  test('Baustelleneinsatz wird nicht überführt, der Transportwunsch genau einmal und ohne Freigabe', async () => {
    const before = await overview()
    const siteUse = byCargo(before, 'Podest-Elemente entladen')!
    const refused = await mw.request.post(`${base}/logistics/needs/${encodeURIComponent(siteUse.id)}/convert`)
    expect(refused.status()).toBe(400)

    const wish = byCargo(before, 'Zeltmaterial abholen')!
    await mw.goto(`/${DEPT}/ga/logistics/dispatch`)
    await mw.locator('article.need').filter({ hasText: 'Zeltmaterial abholen' }).first().getByRole('button', { name: /Disponieren/ }).click()
    await mw.getByTestId('dispo-need-convert-action').click()
    await expect(mw.getByTestId('dispo-need-convert-action')).toHaveCount(0)

    const after = await overview()
    expect(after.needs.find((need) => need.id === wish.id)).toBeUndefined()
    const trips = after.needs.filter((need) => need.converted_from_vehicle_need_id === wish.id.slice(3))
    expect(trips).toHaveLength(1)
    expect(trips[0].released).toBe(false)
    expect(trips[0].pack_ids).toHaveLength(1)
    expect((await mw.request.post(`${base}/logistics/needs/${encodeURIComponent(wish.id)}/convert`)).status()).toBe(400)
  })

  test('Beide Aufgabenansichten zeigen nur freigegebene Fahrten', async () => {
    const data = await overview()
    const released = data.needs.filter((need) => need.einsatz_id && need.released)
    const hidden = data.needs.filter((need) => need.einsatz_id && !need.released)
    expect(hidden.length).toBeGreaterThanOrEqual(2) // Holz-Abholung und überführter Fahrauftrag

    await mw.goto(`/${DEPT}/ga/tasks/all`)
    await mw.getByTestId('kind-filter').getByRole('button', { name: 'Fahrten' }).click()
    await expect(mw.locator('.ga-card')).toHaveCount(released.length)

    // Chauffeur ga-lw («Meine Einsätze»): genau die eigenen freigegebenen Fahrten, keine fremden und keine nicht freigegebenen
    const bauzaun = released.find((need) => need.cargo.includes('Bauzaun'))!
    const mine = released.filter((need) => need.chauffeur_user_id === bauzaun.chauffeur_user_id)
    await driver.goto(`/${DEPT}/ga/tasks`)
    await driver.getByRole('tab', { name: 'Meine Einsätze' }).or(driver.getByText('Meine Einsätze', { exact: true })).first().click()
    await driver.getByTestId('kind-filter').getByRole('button', { name: 'Fahrten' }).click()
    await expect(driver.locator('.ga-card')).toHaveCount(mine.length)
  })

  test('QR-Ablauf: Chauffeur übernimmt den gepackten Transport und liefert am Ziel ab', async () => {
    const trip = (await overview()).needs.find((need) => need.einsatz_id && need.released && need.cargo.includes('Bauzaun'))!
    const packs = (await (await mw.request.get(`${base}/einsaetze/${trip.einsatz_id}/packs`)).json()) as Array<{ id: string; public_code: string; status: string; lines: Array<{ qty_packed: number }> }>
    expect(packs).toHaveLength(1)
    expect(packs[0].lines.some((line) => line.qty_packed > 0)).toBe(true)
    const places = (await (await mw.request.get(`${base}/places`)).json()) as Array<{ id: string; name: string; public_code: string }>
    const target = places.find((place) => place.name === 'Bauzaun Perimeter')!

    // Falscher Ziel-QR wird abgewiesen, Status bleibt
    await driver.goto(`/i/k/${packs[0].public_code}`)
    await expect(driver.locator('.status', { hasText: 'Fahrt frei' })).toBeVisible()
    const started = driver.waitForResponse((res) => res.url().includes('/scan-start'))
    await driver.getByRole('button', { name: 'Fahrt starten' }).click()
    const startResponse = await started
    expect(startResponse.status(), await startResponse.text()).toBe(200)
    await expect(driver.locator('.status', { hasText: 'Unterwegs' })).toBeVisible()
    await expect.poll(() => driver.evaluate(() => sessionStorage.getItem('ematchef.ga.activePack'))).not.toBeNull()
    const wrong = places.find((place) => place.id !== target.id)!
    await driver.goto(`/i/p/${wrong.public_code}`)
    await expect(driver.getByText(/Aktives Pack/)).toBeVisible({ timeout: 30_000 })
    await driver.getByRole('button', { name: 'Ankunft buchen' }).click()
    expect((await (await mw.request.get(`${base}/einsaetze/${trip.einsatz_id}/packs`)).json())[0].status).toBe('in_transit')

    // Richtiges Ziel: Pack ist am Ziel, der Bedarf erledigt
    await driver.goto(`/i/p/${target.public_code}`)
    await expect(driver.getByText(/Aktives Pack/)).toBeVisible({ timeout: 30_000 })
    await driver.getByRole('button', { name: 'Ankunft buchen' }).click()
    await expect.poll(async () => ((await (await mw.request.get(`${base}/einsaetze/${trip.einsatz_id}/packs`)).json()) as Array<{ status: string }>)[0].status).toBe('at_place')
    expect((await overview()).needs.find((need) => need.id === trip.id)!.status).toBe('done')
    expect(problems).toEqual([])
  })
})
