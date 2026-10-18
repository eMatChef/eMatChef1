import { test, expect, type Page } from '@playwright/test'

/**
 * GA Logistik-Disposition (`/ga/logistics/dispatch`): echte Backend-Daten statt Prototyp. Rein lesend.
 * Aktiv nur mit E2E_GA_DISPATCH=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD (Demo-Konto `ga-mw`).
 */
const email = process.env.E2E_USER_EMAIL
const password = process.env.E2E_USER_PASSWORD
const DEPT = process.env.E2E_DEPARTMENT_ID || '2d42e87c5197'

type Need = { id: string; title: string; cargo: string; starts_at: string; status: 'open' | 'planned' | 'underway' | 'done' }

test.describe('GA Logistik-Disposition', () => {
  test.skip(process.env.E2E_GA_DISPATCH !== '1' || !email || !password, 'E2E_GA_DISPATCH=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD nötig')
  test.setTimeout(90_000)
  test.describe.configure({ retries: 2 })
  test.use({ ignoreHTTPSErrors: true })

  async function login(page: Page) {
    await page.goto('/login')
    await page.getByRole('textbox', { name: /E-Mail/i }).fill(email!)
    await page.locator('input[type="password"]').first().fill(password!)
    await page.getByRole('button', { name: /Anmelden/i }).click()
    await page.waitForURL((url) => !/\/login(?:\?|$)/.test(url.pathname), { timeout: 60_000 })
    await page.waitForLoadState('networkidle')
  }

  test('zeigt die Bedarfe aus der API, ohne Prototyp-Hinweis', async ({ page }) => {
    await login(page)
    const problems: string[] = []
    // Der Vite-HMR-WebSocket schlägt hinter der Host-Abbildung der isolierten Umgebung fehl; das ist kein App-Fehler.
    page.on('pageerror', (err) => !/WebSocket/.test(err.message) && problems.push(`pageerror: ${err.message}`))
    page.on('response', (res) => res.url().includes('/api/') && res.status() >= 400 && problems.push(`${res.status()} ${res.url()}`))

    const dispatch = page.waitForResponse((res) => /grossanlass\/logistics\/dispatch$/.test(res.url()) && res.request().method() === 'GET')
    await page.goto(`/${DEPT}/ga/logistics/dispatch`)
    const payload = (await (await dispatch).json()) as { needs: Need[] }
    expect(payload.needs.length).toBeGreaterThan(0)

    await expect(page.getByText(/Prototyp · Demo-Daten/)).toHaveCount(0)
    await expect(page.getByTestId('dispo-error')).toHaveCount(0)

    const open = payload.needs.filter((need) => need.status === 'open').length
    await expect(page.locator('.kpi--open strong')).toHaveText(String(open))

    const first = payload.needs.find((need) => need.status === 'open')
    expect(first, 'mindestens ein offener Bedarf in den Demo-Daten').toBeTruthy()
    const card = page.locator('.need', { hasText: first!.cargo }).first()
    await expect(card).toBeVisible()
    await card.getByRole('button').first().click()
    await expect(page.getByRole('dialog')).toContainText(first!.title)

    expect(problems).toEqual([])
  })

  test('Tour zusammenstellen, Stopps prüfen, Konflikt erkennen, bestätigen ohne Freigabe und wieder löschen', async ({ page }) => {
    await login(page)
    const problems: string[] = []
    page.on('pageerror', (err) => !/WebSocket/.test(err.message) && problems.push(`pageerror: ${err.message}`))

    type Overview = {
      can_manage: boolean
      needs: Array<Need & { released: boolean; tour_id: string | null; cargo: string }>
      tours: Array<{ id: string; name: string; status: string }>
      drivers: Array<{ id: string }>
      vehicles: Array<{ id: string }>
    }
    const base = `/api/departments/${DEPT}/grossanlass/logistics`
    const overview = async (): Promise<Overview> => (await (await page.request.get(`${base}/dispatch`)).json()) as Overview

    const before = await overview()
    expect(before.can_manage).toBe(true)
    const need = before.needs.find((row) => row.status === 'open' && row.id.startsWith('vn:'))
    expect(need, 'offener Fahrzeugwunsch in den Demo-Daten').toBeTruthy()

    await page.goto(`/${DEPT}/ga/logistics/dispatch`)
    // Bedarf → Tourentwurf
    const card = page.locator('.need', { hasText: need!.cargo }).first()
    await expect(card).toBeVisible()
    await card.getByRole('button').first().click()
    const created = page.waitForResponse((res) => /logistics\/tours$/.test(res.url()) && res.request().method() === 'POST')
    await page.getByTestId('dispo-need-assign-action').click()
    expect((await created).status()).toBe(201)
    const tourId = ((await (await created).json()) as { id: string }).id
    await page.keyboard.press('Escape')

    // Bedarf ist nicht mehr offen, sondern disponiert (Tourentwurf)
    expect((await overview()).needs.find((row) => row.id === need!.id)).toMatchObject({ tour_id: tourId, status: 'planned', released: false })

    // Touren-Ansicht und Tour-Dialog
    await page.getByRole('button', { name: /^Touren/ }).click()
    await page.getByTestId(`dispo-tour-${tourId}`).getByRole('button').first().click()
    const dialog = page.getByTestId('dispo-tour-dialog')
    await expect(dialog).toBeVisible()
    await expect(dialog.getByTestId('dispo-tour-stops').locator('li')).toHaveCount(2)

    // Ungültige Reihenfolge (Abladen vor Aufladen) wird vom Server abgelehnt und angezeigt
    await dialog.getByRole('button', { name: 'Nach oben' }).nth(1).click()
    await page.getByTestId('dispo-tour-save-stops').click()
    await expect(page.getByTestId('dispo-tour-error')).toContainText('Aufladen muss vor dem Abladen')
    // Eingaben bleiben nach dem Fehler erhalten und lassen sich verwerfen
    await page.getByRole('button', { name: 'Abbrechen' }).click()

    // Ohne Fahrzeug/Chauffeur nicht bestätigbar
    await page.getByTestId('dispo-tour-confirm').click()
    await expect(page.getByTestId('dispo-tour-error')).toContainText('Fahrzeug fehlt')

    // Fahrzeug und Chauffeur zuweisen (API), dann bestätigen
    const patched = await page.request.patch(`${base}/tours/${tourId}`, { data: { vehicle_id: before.vehicles[0]!.id, chauffeur_user_id: before.drivers[0]!.id } })
    expect(patched.ok(), await patched.text()).toBe(true)
    await page.reload()
    await page.getByRole('button', { name: /^Touren/ }).click()
    await page.getByTestId(`dispo-tour-${tourId}`).getByRole('button').first().click()
    await page.getByTestId('dispo-tour-confirm').click()
    await expect(page.getByTestId('dispo-tour-dialog')).toContainText('Bestätigt, noch nicht zur Ausführung freigegeben')

    // Bestätigt ist nicht freigegeben: Fahrauftrag unverändert, nichts in der operativen Übersicht
    const confirmed = await overview()
    expect(confirmed.tours.find((row) => row.id === tourId)?.status).toBe('confirmed')
    expect(confirmed.needs.find((row) => row.id === need!.id)?.released).toBe(false)

    // Zweite Tour mit demselben Chauffeur und Fahrzeug im selben Zeitfenster → Konflikt, nicht bestätigbar
    const other = before.needs.find((row) => row.id !== need!.id && row.status === 'open' && row.id.startsWith('vn:') && row.starts_at === need!.starts_at)
    if (other) {
      const clash = await page.request.post(`${base}/tours`, { data: { need_ids: [other.id], vehicle_id: before.vehicles[0]!.id, chauffeur_user_id: before.drivers[0]!.id } })
      const body = (await clash.json()) as { id: string; conflicts: unknown[] }
      expect(body.conflicts.length).toBeGreaterThan(0)
      expect((await page.request.post(`${base}/tours/${body.id}/confirm`)).status()).toBe(400)
      await page.request.delete(`${base}/tours/${body.id}`)
    }

    // Aufräumen: Tour löschen, Bedarf ist wieder offen
    await page.getByTestId('dispo-tour-delete').click()
    await expect(page.getByTestId(`dispo-tour-${tourId}`)).toHaveCount(0)
    expect((await overview()).needs.find((row) => row.id === need!.id)).toMatchObject({ tour_id: null, status: 'open' })
    expect(problems).toEqual([])
  })
})
