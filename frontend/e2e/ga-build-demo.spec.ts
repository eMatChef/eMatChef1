import { expect, test, type Page } from '@playwright/test'

/**
 * Aufbau-Demo des Szenarios grossanlass-event (Preset «Aufbau», 31.05.2027 14:00): echte Aufgaben und Fahraufträge über die
 * bestehenden Aktionen bearbeiten und die Änderung auf einem gekoppelten Infoscreen ohne Neuladen sehen (Fachzeit und Feed).
 *
 * SCHREIBT (Aufgabenstatus, Freigabe, Infoscreen samt Gerät, Uhr) und läuft NUR gegen eine isolierte, frisch mit
 * `app:demo:sync --scenario=grossanlass-event` befüllte Umgebung (nie mvdb), mit eigenem Upload-Verzeichnis. Aktiv mit E2E_GA_BUILD=1,
 * Demo-Konto ga-mw (E2E_USER_EMAIL / E2E_USER_PASSWORD), E2E_DISPLAY_URL (display.…) und optional E2E_DEPARTMENT_ID.
 */
const email = process.env.E2E_USER_EMAIL
const password = process.env.E2E_USER_PASSWORD
const displayUrl = (process.env.E2E_DISPLAY_URL || '').replace(/\/$/, '')

test.describe.configure({ mode: 'serial' })
test.skip(process.env.E2E_GA_BUILD !== '1' || !email || !password || !displayUrl, 'E2E_GA_BUILD=1, E2E_USER_EMAIL / E2E_USER_PASSWORD und E2E_DISPLAY_URL nötig')
test.setTimeout(240_000)

let dept = process.env.E2E_DEPARTMENT_ID || ''

async function login(page: Page): Promise<void> {
  await page.goto('/login')
  await page.getByRole('textbox', { name: /E-Mail/i }).fill(email!)
  await page.locator('input[type="password"]').first().fill(password!)
  await page.getByRole('button', { name: /Anmelden/i }).click()
  await page.waitForURL((url) => !/\/login(?:\?|$)/.test(url.pathname), { timeout: 60_000 })
  await page.waitForLoadState('networkidle')
  if (!dept) dept = /\/([0-9a-f]{12})\//.exec(page.url())![1]
}

async function jumpTo(page: Page, preset: string, text: string): Promise<void> {
  await page.keyboard.press('Escape') // offene Dialoge schliessen
  const clock = page.getByTestId('clock-control')
  await expect(clock).toBeVisible({ timeout: 60_000 })
  await clock.click()
  await page.getByTestId(`clock-preset-${preset}`).click()
  await expect(clock).toContainText(text, { timeout: 30_000 })
  await page.keyboard.press('Escape')
}

test('Aufbau: Aufgaben und Fahraufträge sind echt, bearbeitbar und gehören zum Preset', async ({ page }) => {
  const problems: string[] = []
  page.on('pageerror', (err) => !/WebSocket/.test(err.message) && problems.push(`pageerror: ${err.message}`)) // HMR-WebSocket des Dev-Servers ist kein App-Fehler
  page.on('response', (res) => res.url().includes('/api/') && res.status() >= 400 && !res.url().endsWith('/api/auth/session') && problems.push(`${res.status()} ${res.request().method()} ${res.url()}`))
  await login(page)
  await jumpTo(page, 'build', '31.05.2027 14:')

  // Alle Aufgaben: freigegebene Aufgaben aus der API, nicht freigegebene fehlen
  const tasksResponse = page.waitForResponse((res) => res.url().endsWith('/grossanlass/groups/tasks'))
  await page.goto(`/${dept}/ga/tasks/all`)
  const apiTasks = (await (await tasksResponse).json()) as { title: string; released: boolean; status: string }[]
  const released = apiTasks.filter((t) => t.released)
  expect(released.length).toBeGreaterThanOrEqual(27)
  await expect(page.locator('.ga-card', { hasText: 'Tresenrahmen bauen' })).toBeVisible({ timeout: 30_000 })
  await expect(page.locator('.ga-card', { hasText: 'Wandelemente stellen' })).toBeVisible()

  // Aufgabe öffnen: starten, Checklistenpunkt abhaken (bestehende Aktionen)
  const card = page.locator('.ga-card', { hasText: 'Tresenrahmen bauen' })
  await card.getByRole('button', { name: /Öffnen/i }).first().click()
  const detail = page.locator('.v-dialog .v-overlay__content')
  const start = detail.getByRole('button', { name: /Starten/i })
  if (await start.isVisible()) await start.click() // bei einem Wiederholungslauf läuft die Aufgabe bereits
  await expect(detail.getByText('In Arbeit').first()).toBeVisible()
  await page.keyboard.press('Escape')

  // Fahrten: freigegebene Fahraufträge als Kacheln, Dialog mit Fahrzeug
  await page.getByTestId('kind-filter').getByRole('button', { name: 'Fahrten' }).click()
  const trips = page.locator('.ga-card')
  await expect(trips.first()).toBeVisible()
  expect(await trips.count()).toBeGreaterThanOrEqual(2)
  await trips.first().getByRole('button', { name: /Fahrauftrag öffnen/ }).click()
  await expect(page.locator('.v-dialog .v-overlay__content')).toContainText('Fahrauftrag')
  await page.keyboard.press('Escape')

  // Disposition: Freigabe der Verteilfahrt zurücknehmen und erneut erteilen (erlaubter Statusschritt, Pack «Fahrt frei»)
  const overview = (await (await page.request.get(`/api/departments/${dept}/grossanlass/logistics/dispatch`)).json()) as {
    now: string
    needs: { id: string; einsatz_id: string | null; released: boolean; tour_id: string | null; starts_at: string }[]
  }
  expect(overview.now.slice(0, 13)).toBe('2027-05-31T14')
  const verteilung = overview.needs.find((n) => n.einsatz_id && n.released && n.starts_at.startsWith('2027-05-31T16'))!
  expect(verteilung).toBeTruthy()
  await page.goto(`/${dept}/ga/logistics/dispatch?need=${verteilung.id}`)
  const exec = page.getByTestId('dispo-need-exec')
  await exec.getByTestId('dispo-need-revoke').click()
  await expect(exec.getByTestId('dispo-need-released')).toContainText('Nicht freigegeben')
  await exec.getByTestId('dispo-need-release').click()
  await expect(exec.getByTestId('dispo-need-released')).toContainText('Zur Ausführung freigegeben')
  await expect(exec.getByTestId('dispo-need-pack-status')).toContainText('Fahrt frei')
  expect(problems).toEqual([])
})

test('Infoscreen folgt der GA-Fachzeit und zeigt Änderungen ohne Neuladen', async ({ page, browser }) => {
  await login(page)
  await jumpTo(page, 'build', '31.05.2027 14:')

  // Infoscreen aus Vorlage erstellen und veröffentlichen
  await page.goto(`/${dept}/ga/displays`)
  const name = `E2E Aufbau ${Date.now()}`
  await page.getByRole('button', { name: 'Infoscreen erstellen' }).first().click()
  await page.getByLabel('Name', { exact: false }).first().fill(name)
  await page.getByTestId('template-ga_baubuero').or(page.getByRole('radio', { name: /Baub/ })).first().click()
  const studioPromise = page.context().waitForEvent('page')
  await page.getByRole('button', { name: 'Infoscreen erstellen' }).last().click()
  const editor = await studioPromise
  await editor.waitForURL(/\/ga\/displays\/[^/]+\/layout/)
  const screenId = editor.url().split('/displays/')[1]!.split('/')[0]!
  await editor.getByTestId('btn-publish').click()
  await editor.getByTestId('btn-publish-confirm').click()
  await expect(editor.getByText(/Veröffentlicht: Revision 1/).first()).toBeVisible()
  await editor.close()

  // Fernseher koppeln (eigener Kontext, Display-Domain)
  const tvContext = await browser.newContext()
  const tv = await tvContext.newPage()
  let pairUrl = ''
  tv.on('response', async (res) => {
    if (res.url().endsWith('/api/public/display-pairing') && res.request().method() === 'POST') pairUrl = ((await res.json()) as { pair_url: string }).pair_url
  })
  await tv.goto(`${displayUrl}/display/connect`)
  await expect.poll(() => pairUrl, { timeout: 20_000 }).not.toBe('')
  await page.goto(`/${dept}/ga/displays`)
  await page.getByRole('button', { name }).click()
  await page.getByRole('button', { name: 'Bildschirm hinzufügen' }).first().click()
  await page.getByLabel(/Kopplungs-Link/).fill(pairUrl)
  await page.getByRole('button', { name: 'Weiter' }).click()
  await page.getByLabel('Gerätename').fill('Fernseher Aufbau')
  await page.getByRole('button', { name: 'Verbinden', exact: true }).click()
  await expect(page.getByText('Der Bildschirm ist verbunden.')).toBeVisible()
  await page.getByRole('button', { name: 'Fertig' }).click()
  await tv.waitForURL(/\/display\/device/, { timeout: 20_000 })
  await expect(tv.locator('.dl')).toBeVisible({ timeout: 30_000 })

  // Das Gerät erhält nur Anzeigedaten: Fachzeit des Departments, keine Tokens
  const dataBody = await (await tv.request.get(`${displayUrl}/api/public/display-device/data`)).text()
  expect(dataBody).toContain('"mode":"demo"')
  expect(dataBody.toLowerCase()).not.toContain('token')

  // Uhr: dieselbe Fachzeit wie das GA-Control (31.05.2027, 14 Uhr), nicht die echte Zeit
  await expect(tv.locator('.dl')).toContainText(/14:\d\d/, { timeout: 30_000 })

  // Aufgabe bearbeiten (GA-Fenster): Fertig melden erscheint im Feed des Infoscreens, ohne dass dieser neu lädt
  await page.goto(`/${dept}/ga/tasks/all`)
  const card = page.locator('.ga-card', { hasText: 'Wandelemente stellen' })
  await card.getByRole('button', { name: /Öffnen/i }).first().click()
  const detail = page.locator('.v-dialog .v-overlay__content')
  for (const step of [/Starten/i, /Fertig melden/i]) {
    const button = detail.getByRole('button', { name: step })
    if (await button.isVisible()) await button.click() // bei einem Wiederholungslauf ist der Schritt schon erfolgt
  }
  await expect(detail.getByTestId('task-review')).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(tv.locator('.dl')).toContainText('Wandelemente stellen', { timeout: 40_000 })

  // Zeitsprung im GA-Control: der Infoscreen übernimmt die neue Fachzeit automatisch (Durchführung, 05.06.2027)
  await jumpTo(page, 'during_event', '05.06.2027 17:')
  await expect(tv.locator('.dl')).toContainText(/17:\d\d/, { timeout: 40_000 })

  // Fahrt bearbeiten: Freigabe der Nachschubfahrt zurücknehmen (bestehende Aktion), der Feed des Infoscreens zeigt es ohne Neuladen
  const dispatch = (await (await page.request.get(`/api/departments/${dept}/grossanlass/logistics/dispatch`)).json()) as { needs: { id: string; einsatz_id: string | null; released: boolean; starts_at: string }[] }
  const supply = dispatch.needs.find((n) => n.einsatz_id && n.starts_at.startsWith('2027-06-05T18:30'))!
  expect(supply).toBeTruthy()
  if (!supply.released) await page.request.post(`/api/departments/${dept}/grossanlass/logistics/needs/${supply.id}/release`) // nach einem abgebrochenen Lauf
  const revoked = await page.request.post(`/api/departments/${dept}/grossanlass/logistics/needs/${supply.id}/revoke-release`)
  expect(revoked.ok(), await revoked.text()).toBe(true)
  await expect(tv.locator('.dl')).toContainText('Freigabe zurückgenommen', { timeout: 40_000 })
  const released = await page.request.post(`/api/departments/${dept}/grossanlass/logistics/needs/${supply.id}/release`) // Ausgangszustand wiederherstellen
  expect(released.ok(), await released.text()).toBe(true)

  // Aufräumen: Uhr zurück auf den Aufbau
  await jumpTo(page, 'build', '31.05.2027 14:')
  await tvContext.close()
  void screenId
})

test('Alle sechs Schnellwahlen zeigen passende Aufgaben und Fahrten, Sprünge ändern nichts', async ({ page }) => {
  await login(page)
  const problems: string[] = []
  page.on('pageerror', (err) => !/WebSocket/.test(err.message) && problems.push(`pageerror: ${err.message}`))
  page.on('response', (res) => res.url().includes('/api/') && res.status() >= 400 && !res.url().endsWith('/api/auth/session') && problems.push(`${res.status()} ${res.request().method()} ${res.url()}`))
  const api = async <T>(path: string): Promise<T> => (await (await page.request.get(`/api/departments/${dept}/grossanlass/${path}`)).json()) as T
  type Task = { id: string; title: string; status: string; released: boolean }
  const fingerprint = async () => JSON.stringify((await api<Task[]>('groups/tasks')).map((t) => [t.id, t.status]).sort())

  const presets: [string, string][] = [
    ['months_before_5', '04.01.2027 16:'],
    ['months_before_2', '04.04.2027 16:'],
    ['build', '31.05.2027 14:'],
    ['during_event', '05.06.2027 17:'],
    ['teardown', '08.06.2027 08:'],
    ['months_after_2', '06.08.2027 16:'],
  ]
  const before = await fingerprint()
  const dispatchBefore = JSON.stringify((await api<{ tours: { id: string; status: string }[] }>('logistics/dispatch')).tours)
  for (const [key, text] of presets) {
    await jumpTo(page, key, text)
    await page.goto(`/${dept}/ga/tasks/all`)
    await expect(page.locator('.ga-card').first()).toBeVisible({ timeout: 30_000 })
  }
  expect(await fingerprint(), 'Sprünge setzen keinen Aufgabenstatus zurück').toBe(before)
  expect(JSON.stringify((await api<{ tours: { id: string; status: string }[] }>('logistics/dispatch')).tours)).toBe(dispatchBefore)

  async function work(title: string, steps: RegExp[]): Promise<void> {
    await page.goto(`/${dept}/ga/tasks/all`)
    const card = page.locator('.ga-card', { hasText: title })
    await expect(card, title).toBeVisible({ timeout: 30_000 })
    await card.getByRole('button', { name: /Öffnen/i }).first().click()
    const detail = page.locator('.v-dialog .v-overlay__content')
    for (const step of steps) {
      const button = detail.getByRole('button', { name: step })
      if (await button.isVisible()) await button.click()
    }
    await page.keyboard.press('Escape')
  }

  // Während Event: Betriebsaufgabe bearbeiten, Nachschubfahrt (freigegeben) als Fahrt-Kachel
  await jumpTo(page, 'during_event', '05.06.2027 17:')
  await work('Zeltabspannung kontrollieren', [/Starten/i, /Fertig melden/i])
  await expect.poll(async () => (await api<Task[]>('groups/tasks')).find((t) => t.title === 'Zeltabspannung kontrollieren')?.status).toBe('review')
  await page.goto(`/${dept}/ga/tasks/all`)
  await page.getByTestId('kind-filter').getByRole('button', { name: 'Fahrten' }).click()
  await expect(page.locator('.ga-card', { hasText: '18:30' })).toHaveCount(1)

  // Abbau: Rückbauaufgabe starten, Rücktransport (offener Fahrauftrag) in eine Tour legen und bestätigen
  await jumpTo(page, 'teardown', '08.06.2027 08:')
  await work('Tresen demontieren', [/Starten/i])
  await expect.poll(async () => (await api<Task[]>('groups/tasks')).find((t) => t.title === 'Tresen demontieren')?.status).toBe('progress')
  type Need = { id: string; einsatz_id: string | null; starts_at: string; tour_id: string | null; vehicle_id: string | null; chauffeur_user_id: string | null }
  const overview = await api<{ needs: Need[]; tours: { chauffeur_user_id: string }[] }>('logistics/dispatch')
  const retour = overview.needs.find((n) => n.einsatz_id && n.starts_at.startsWith('2027-06-08T11'))!
  if (retour.tour_id === null) {
    const created = await page.request.post(`/api/departments/${dept}/grossanlass/logistics/tours`, { data: { name: 'Rücktransport Tresen', need_ids: [retour.id], vehicle_id: retour.vehicle_id, chauffeur_user_id: overview.tours[0].chauffeur_user_id } })
    expect(created.ok(), await created.text()).toBe(true)
    const tour = (await created.json()) as { id: string }
    const confirmed = await page.request.post(`/api/departments/${dept}/grossanlass/logistics/tours/${tour.id}/confirm`)
    expect(confirmed.ok(), await confirmed.text()).toBe(true)
  } // sonst: Wiederholungslauf, die Tour besteht schon
  const after = await api<{ needs: Need[]; tours: { id: string; status: string }[] }>('logistics/dispatch')
  const tourId = after.needs.find((n) => n.id === retour.id)!.tour_id
  expect(tourId).not.toBeNull()
  expect(after.tours.find((t) => t.id === tourId)?.status).toBe('confirmed')
  await page.goto(`/${dept}/ga/logistics/dispatch?need=${retour.id}`)
  await expect(page.getByTestId('dispo-need-exec')).toBeVisible({ timeout: 30_000 })

  // Abschluss: offene Restarbeit bearbeiten, Materialklärung in der Werkstatt sichtbar
  await jumpTo(page, 'months_after_2', '06.08.2027 16:')
  await work('Wegeleuchten einsammeln und zählen', [/Starten/i, /Fertig melden/i])
  await expect.poll(async () => (await api<Task[]>('groups/tasks')).find((t) => t.title === 'Wegeleuchten einsammeln und zählen')?.status).toBe('review')
  await page.goto(`/${dept}/ga/workshop`)
  await expect(page.getByText('Zeltboden beschädigt')).toBeVisible({ timeout: 30_000 })
  await expect(page.getByText('Rampengeländer angeschlagen')).toBeVisible()

  await jumpTo(page, 'build', '31.05.2027 14:')
  expect(problems).toEqual([])
})
