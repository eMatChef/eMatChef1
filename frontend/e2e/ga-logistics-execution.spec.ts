import { test, expect, type Page } from '@playwright/test'

/**
 * GA Logistik Phase 3: Freigabe, Auslieferung ohne QR (begründete Ausnahme), Tourabschluss, Änderungswünsche und die beiden
 * Aufgabenansichten gegen die echten Demo-Daten (`ga-mw`).
 * SCHREIBT (Freigabe, Ablieferung, Tourabschluss, Änderungswunsch) — nur gegen die isolierte Wegwerf-Umgebung auf frisch
 * synchronisierten Demo-Daten (docs/E2E.md), nie mvdb. Aktiv nur mit E2E_GA_EXECUTION=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD.
 */
const email = process.env.E2E_USER_EMAIL
const password = process.env.E2E_USER_PASSWORD
const DEPT = process.env.E2E_DEPARTMENT_ID || '2d42e87c5197'

type Need = { id: string; title: string; cargo: string; status: string; released: boolean; tour_id: string | null; pack_ids: string[]; pack_status: string; einsatz_id: string | null }
type Overview = { can_manage: boolean; needs: Need[]; tours: Array<{ id: string; name: string; status: string }> }

test.describe.serial('GA Logistik: Freigabe und Auslieferung', () => {
  test.skip(process.env.E2E_GA_EXECUTION !== '1' || !email || !password, 'E2E_GA_EXECUTION=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD nötig')
  test.setTimeout(180_000)
  test.use({ ignoreHTTPSErrors: true })

  const base = `/api/departments/${DEPT}/grossanlass/logistics`
  const problems: string[] = []

  async function login(page: Page) {
    await page.goto('/login')
    await page.getByRole('textbox', { name: /E-Mail/i }).fill(email!)
    await page.locator('input[type="password"]').first().fill(password!)
    await page.getByRole('button', { name: /Anmelden/i }).click()
    await page.waitForURL((url) => !/\/login(?:\?|$)/.test(url.pathname), { timeout: 60_000 })
    await page.waitForLoadState('networkidle')
    page.on('pageerror', (err) => !/WebSocket/.test(err.message) && problems.push(`pageerror: ${err.message}`))
  }
  const overview = async (page: Page): Promise<Overview> => (await (await page.request.get(`${base}/dispatch`)).json()) as Overview

  test('Demo enthält freigegebene und nicht freigegebene Fahrten; Aufgabenansichten zeigen nur Freigegebenes', async ({ page }) => {
    await login(page)
    const data = await overview(page)
    const trips = data.needs.filter((need) => need.einsatz_id)
    const released = trips.filter((need) => need.released)
    const unreleased = trips.filter((need) => !need.released)
    expect(released.length).toBeGreaterThan(0)
    expect(unreleased.length).toBeGreaterThan(0)

    await page.goto(`/${DEPT}/ga/tasks/all`)
    await page.getByTestId('kind-filter').getByRole('button', { name: 'Fahrten' }).click()
    await expect(page.locator('.ga-card')).toHaveCount(released.length)
    for (const need of unreleased) await expect(page.getByText(need.title, { exact: true })).toHaveCount(0)

    // «Meine Einsätze»: nur Fahrten, bei denen der angemeldete Benutzer Chauffeur ist (ga-mw fährt nicht)
    await page.goto(`/${DEPT}/ga/tasks`)
    await page.getByTestId('kind-filter').getByRole('button', { name: 'Fahrten' }).click()
    await expect(page.locator('.ga-card')).toHaveCount(0)
  })

  test('Planung zeigt Tour und Link, hat keine Freigabeaktion; Ressort-Wunsch wird in der Disposition begründet abgelehnt', async ({ page }) => {
    await login(page)
    const data = await overview(page)
    const inTour = data.needs.find((need) => need.einsatz_id && need.tour_id)!
    await page.goto(`/${DEPT}/ga/planning/transports`)
    const tile = page.getByTestId(`trip-tile-${inTour.einsatz_id}`)
    await expect(tile).toBeVisible()
    await expect(tile.getByTestId('trip-tile-dispo')).toContainText('Anlieferung Teleskoplader · Bestätigt')
    await expect(page.locator('.ga-tile__actions').getByRole('button', { name: 'Fahrt frei' })).toHaveCount(0)
    await expect(page.getByTestId(`trip-tile-dispatch-${inTour.einsatz_id}`)).toBeVisible()

    await page.getByTestId(`trip-tile-change-${inTour.einsatz_id}`).click()
    await page.getByTestId('trip-change-note').fill('Bitte eine Stunde früher anliefern')
    const created = page.waitForResponse((res) => /change-requests$/.test(res.url()) && res.request().method() === 'POST')
    await page.getByTestId('trip-change-submit').click()
    expect((await created).status()).toBe(201)
    const before = await overview(page)
    expect(before.needs.find((need) => need.id === inTour.id)?.released, 'Planung bleibt gültig').toBe(true)

    // Logistik entscheidet: Ablehnung braucht eine Begründung
    await page.goto(`/${DEPT}/ga/logistics/dispatch?need=${inTour.einsatz_id}`)
    const request = page.locator('[data-testid^="dispo-request-"]:not([data-testid*="note"]):not([data-testid*="accept"]):not([data-testid*="reject"])').first()
    await expect(request).toBeVisible()
    await expect(request.getByRole('button', { name: 'Ablehnen' })).toBeDisabled()
    await request.locator('input[type="text"]').fill('Ziel vorher nicht zugänglich')
    await request.getByRole('button', { name: 'Ablehnen' }).click()
    await expect(request).toContainText('Abgelehnt')
    await expect(request).toContainText('Ziel vorher nicht zugänglich')
  })

  test('Freigabe zurücknehmen und erneut erteilen; Ablieferung ohne QR mit Begründung; Tour abschliessen', async ({ page }) => {
    await login(page)
    const data = await overview(page)
    const trip = data.needs.find((need) => need.einsatz_id && need.tour_id && need.released)!
    const tour = data.tours.find((row) => row.id === trip.tour_id)!

    // Rücknahme und erneute Freigabe in der zentralen Disposition (kein zweiter Weg)
    await page.goto(`/${DEPT}/ga/logistics/dispatch?need=${trip.id}`)
    const exec = page.getByTestId('dispo-need-exec')
    await expect(exec.getByTestId('dispo-need-released')).toContainText('Zur Ausführung freigegeben')
    await exec.getByTestId('dispo-need-revoke').click()
    await expect(exec.getByTestId('dispo-need-released')).toContainText('Nicht freigegeben')
    await expect(exec.getByTestId('dispo-need-pack-status')).toContainText('Bereitgestellt')
    await exec.getByTestId('dispo-need-release').click()
    await expect(exec.getByTestId('dispo-need-released')).toContainText('Zur Ausführung freigegeben')
    await expect(exec.getByTestId('dispo-need-pack-status')).toContainText('Fahrt frei')

    // Der Teleskoplader ist bestellt, aber noch nicht eingegangen (Demo-Regel: der Seed bucht keine Warenbewegung): Wareneingang buchen
    const commitments = (await (await page.request.get(`/api/departments/${DEPT}/grossanlass/beschaffung/zusagen`)).json()) as Array<{ id: string; name: string }>
    const lader = commitments.find((row) => row.name === 'Teleskoplader')!
    const received = await page.request.post(`/api/departments/${DEPT}/grossanlass/beschaffung/zusagen/${lader.id}/movements`, { data: { kind: 'received', quantity: 1 } })
    // (bei einem erneuten Lauf ist der Eingang schon gebucht: die Überlieferung wird abgelehnt, der Scan unten entscheidet)
    void received

    // Material übernehmen (bestehender QR-Ablauf: Pack scannen → unterwegs)
    for (const packId of trip.pack_ids) {
      const started = await page.request.post(`/api/departments/${DEPT}/grossanlass/packs/${packId}/scan-start`)
      expect(started.ok(), await started.text()).toBe(true)
    }
    await page.reload()
    const manual = page.getByTestId('dispo-need-exec').getByTestId('dispo-need-manual')
    await expect(manual).toBeVisible()
    await expect(manual.getByTestId('dispo-need-manual-action')).toBeDisabled()
    await manual.getByTestId('dispo-need-manual-reason').fill('QR am Ziel abgerissen')
    await manual.getByTestId('dispo-need-manual-action').click()
    await expect(page.getByTestId('dispo-need-exec').getByTestId('dispo-need-pack-status')).toContainText('abgeliefert')
    expect((await overview(page)).needs.find((need) => need.id === trip.id)?.status).toBe('done')

    // Tour abschliessen: Auslieferung bleibt dokumentiert
    await page.keyboard.press('Escape')
    await page.getByRole('button', { name: /^Touren/ }).click()
    await page.getByTestId(`dispo-tour-${tour.id}`).getByRole('button').first().click()
    await page.getByTestId('dispo-tour-close').click()
    await page.getByTestId('dispo-tour-close').click()
    await expect(page.getByTestId('dispo-tour-need-' + trip.id)).toContainText('Ausgeliefert')
    expect((await overview(page)).tours.find((row) => row.id === tour.id)?.status).toBe('closed')
    expect(problems).toEqual([])
  })
})
