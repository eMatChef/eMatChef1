import { test, expect, type Page } from '@playwright/test'

/**
 * GA-Aufgaben: Fahraufträge als Logistik-Kacheln (`/ga/tasks/all`) gegenüber `/ga/planning/transports`.
 * Rein lesend. Aktiv nur mit E2E_GA_TASKS=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD (Demo-Konto `ga-mw`).
 */
const email = process.env.E2E_USER_EMAIL
const password = process.env.E2E_USER_PASSWORD
const DEPT = process.env.E2E_DEPARTMENT_ID || '2d42e87c5197'

type ApiEinsatz = { id: string; kind: string; delivery: string; status: string; object_name: string; released?: boolean; operable?: boolean }

test.describe('GA Aufgaben: Fahrten', () => {
  test.skip(process.env.E2E_GA_TASKS !== '1' || !email || !password, 'E2E_GA_TASKS=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD nötig')
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

  test('Fahrten-Filter zeigt dieselben Fahraufträge wie die Transportansicht', async ({ page }) => {
    await login(page)
    const problems: string[] = []
    page.on('console', (m) => m.type() === 'error' && console.log('CONSOLE', m.text().slice(0, 200)))
    page.on('requestfailed', (r) => console.log('FAILED', r.url().slice(0, 150), r.failure()?.errorText))
    page.on('pageerror', (err) => problems.push(`pageerror: ${err.message}`))
    page.on('response', (res) => res.url().includes('/api/') && res.status() >= 400 && problems.push(`${res.status()} ${res.url()}`))

    const uebersicht = page.waitForResponse((res) => /grossanlass\/uebersicht$/.test(res.url()) && res.request().method() === 'GET')
    await page.goto(`/${DEPT}/ga/tasks/all`)
    const payload = (await (await uebersicht).json()) as { einsaetze: ApiEinsatz[] }
    const open = payload.einsaetze.filter((row) => row.kind === 'einsatz' && row.delivery === 'trip' && row.status !== 'returned')
    // Die operative Übersicht zeigt nur tatsächlich freigegebene Fahraufträge (Backend-Feld `released`).
    const trips = open.filter((row) => row.released === true)
    const unreleased = open.filter((row) => row.released !== true)
    expect(trips.length).toBeGreaterThan(0)

    await page.getByTestId('kind-filter').getByRole('button', { name: 'Fahrten' }).click()
    const cards = page.locator('.ga-card')
    await expect(cards.first()).toBeVisible()
    await expect(cards).toHaveCount(trips.length)
    await page.screenshot({ path: 'test-results/ga-tasks-trips.png', fullPage: true })

    // Geplante und noch nicht freigegebene Fahraufträge erscheinen nicht
    for (const row of unreleased) await expect(page.getByText(row.object_name, { exact: true })).toHaveCount(
      trips.filter((released) => released.object_name === row.object_name).length,
    )

    // Status-Chip = echter API-Status
    for (const status of ['planned', 'pending_approval', 'issued']) {
      await expect(page.locator(`[data-testid="trip-status-${status}"]`)).toHaveCount(trips.filter((row) => row.status === status).length)
    }

    // Arbeiten-Filter enthält keine Fahrten
    await page.getByTestId('kind-filter').getByRole('button', { name: 'Arbeiten' }).click()
    await expect(page.locator('[data-testid^="trip-status-"]')).toHaveCount(0)

    // Dialog aus der Aufgabenübersicht
    await page.getByTestId('kind-filter').getByRole('button', { name: 'Fahrten' }).click()
    const firstCard = (await cards.first().innerText()).replace(/\s+/g, ' ')
    await cards.first().getByRole('button', { name: /Fahrauftrag öffnen/ }).click()
    const dialog = page.locator('.v-dialog .v-overlay__content')
    await expect(dialog).toContainText('Fahrauftrag')
    const fromTasks = (await dialog.innerText()).replace(/\s+/g, ' ')
    await page.screenshot({ path: 'test-results/ga-tasks-trip-dialog.png' })
    // Kachel und Dialog nennen dieselbe Uhrzeit (Wandzeit des Einsatzes)
    const dialogClock = fromTasks.match(/(\d{2}:\d{2}) – (\d{2}:\d{2})/)
    expect(dialogClock).not.toBeNull()
    expect(firstCard).toContain(`${dialogClock![1]}–${dialogClock![2]}`)
    await dialog.getByRole('button', { name: /Schlie(ss|ß)en|Close/ }).first().click()

    // Transportansicht: derselbe Datensatz, gleicher Dialog
    await page.goto(`/${DEPT}/ga/planning/transports`)
    await expect(page.locator('.ga-fahrauftraege')).toBeVisible({ timeout: 45_000 })
    // Transportansicht zeigt standardmässig nur anstehende Fahrten; vergangene erscheinen dort nicht, in den Aufgaben als «Überfällig».
    const upcoming = trips.filter((row) => new Date(row.to.slice(0, 19)).getTime() >= Date.now())
    if (upcoming.length) await expect(page.locator('.ga-fahrauftraege')).toContainText(upcoming[0]!.object_name)
    else await expect(page.locator('.ga-fahrauftraege')).toContainText(/Nichts steht an|Keine Fahraufträge/)
    expect(problems).toEqual([])
    console.log('TASKS-DIALOG:', fromTasks)
  })
})
