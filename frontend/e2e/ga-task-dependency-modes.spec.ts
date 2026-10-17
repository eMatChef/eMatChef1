import { test, expect, type Page } from '@playwright/test'
import { cleanupE2eInbox } from './helpers/inboxCleanup'

/**
 * GA-Abhängigkeiten, Art «gleichzeitig» (Demo-Konto ga-mw): kein Startsperre, Zeitkonflikt und Anzeige an beiden Aufgaben,
 * Art-Wechsel, Zyklusprüfung beim Wechsel zu «nacheinander», Button «Abhängigkeit hinzufügen» im Bauauftrag-Dialog mit Auswahl
 * aus einem anderen Bauauftrag. Legt zwei Demo-Bauaufträge an und löscht sie samt Verknüpfungen am Ende. Aktiv mit E2E_GA_TASKS=1.
 */
const email = process.env.E2E_USER_EMAIL
const password = process.env.E2E_USER_PASSWORD
const DEPT = process.env.E2E_DEPARTMENT_ID || '2d42e87c5197'
const STAMP = Date.now()
const NAME_A = `E2E Modus A ${STAMP}`
const NAME_B = `E2E Modus B ${STAMP}`

type Dep = { id: string; kind: string; mode: string; incoming?: boolean; open?: boolean; schedule?: { conflict: string } | null }
type Task = { id: string; group_id: string; waiting?: boolean; dependencies?: Dep[] }
type Group = { id: string; name: string; parent_id?: string | null; node_type?: string }

test.describe('GA Abhängigkeiten gleichzeitig', () => {
  test.skip(process.env.E2E_GA_TASKS !== '1' || !email || !password, 'E2E_GA_TASKS=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD nötig')
  test.setTimeout(120_000)
  test.describe.configure({ retries: 2 })
  test.use({ ignoreHTTPSErrors: true })
  test.afterAll(() => cleanupE2eInbox())

  async function login(page: Page) {
    await page.goto('/login')
    await page.getByRole('textbox', { name: /E-Mail/i }).fill(email!)
    await page.locator('input[type="password"]').first().fill(password!)
    await page.getByRole('button', { name: /Anmelden/i }).click()
    await page.waitForURL((url) => !/\/login(?:\?|$)/.test(url.pathname), { timeout: 60_000 })
    await page.waitForLoadState('networkidle')
  }
  async function api<T>(page: Page, method: string, path: string, body?: unknown): Promise<{ status: number; data: T }> {
    return page.evaluate(async ({ method, path, body }) => {
      const res = await fetch(path, { method, credentials: 'include', headers: { 'Content-Type': 'application/json' }, body: body === undefined ? undefined : JSON.stringify(body) })
      return { status: res.status, data: await res.json().catch(() => null) }
    }, { method, path, body }) as Promise<{ status: number; data: T }>
  }

  test('simultaneous links: no lock, conflict, both sides, mode switch, dialog button', async ({ page }) => {
    await login(page)
    const problems: string[] = []
    page.on('pageerror', (err) => problems.push(`pageerror: ${err.message}`))
    const base = `/api/departments/${DEPT}/grossanlass/groups`
    const created: Group[] = []
    try {
      const parent = process.env.E2E_ORDER_PARENT_ID || 'grpfc7ea0fde'
      for (const name of [NAME_A, NAME_B]) {
        const g = await api<Group>(page, 'POST', base, { name, parent_id: parent, kind: 'auftrag' })
        expect(g.status).toBeLessThan(300)
        created.push(g.data)
      }
      const [ga, gb] = created as [Group, Group]
      const a = (await api<Task>(page, 'POST', `${base}/${ga.id}/tasks`, { title: 'E2E Transport', starts_at: '2027-06-10T08:00:00', duration_minutes: 60 })).data
      const b = (await api<Task>(page, 'POST', `${base}/${gb.id}/tasks`, { title: 'E2E Montage', starts_at: '2027-06-10T11:00:00', duration_minutes: 60 })).data
      const url = (g: Group, t: Task) => `${base}/${g.id}/tasks/${t.id}/dependencies`

      // Gleichzeitig anlegen (ressortübergreifend/anderer Bauauftrag): keine Sperre, Zeitkonflikt, ungültige Art / Duplikat
      expect((await api(page, 'POST', url(gb, b), { predecessor_task_id: a.id, mode: 'bogus' })).status).toBe(400)
      const added = await api<Task>(page, 'POST', url(gb, b), { predecessor_task_id: a.id, mode: 'simultaneous' })
      expect(added.status).toBe(201)
      expect(added.data.waiting).toBe(false)
      expect(added.data.dependencies?.[0]).toMatchObject({ mode: 'simultaneous', open: false, schedule: { conflict: 'no_overlap' } })
      expect((await api(page, 'POST', url(ga, a), { predecessor_task_id: b.id, mode: 'simultaneous' })).status).toBe(400) // symmetrisch: nur eine Verknüpfung
      expect((await api(page, 'PATCH', `${base}/${gb.id}/tasks/${b.id}`, { status: 'progress' })).status).toBe(200) // keine Startsperre

      // Anzeige an beiden Aufgaben
      const other = (await api<Task>(page, 'GET', url(ga, a))).data
      expect(other.dependencies).toHaveLength(1)
      expect(other.dependencies![0]).toMatchObject({ mode: 'simultaneous', incoming: true, schedule: { conflict: 'no_overlap' } })

      // Art-Wechsel: nacheinander (Startsperre für b), zurück, ungültig
      const depId = added.data.dependencies![0]!.id
      const seq = await api<Task>(page, 'PATCH', `${base}/${gb.id}/tasks/${b.id}/dependencies/${depId}`, { mode: 'sequential' })
      expect(seq.status).toBe(200)
      expect(seq.data.waiting).toBe(true)
      expect((await api(page, 'PATCH', `${base}/${gb.id}/tasks/${b.id}/dependencies/${depId}`, { mode: 'x' })).status).toBe(400)
      // Zyklus: a wartet auf b, während b auf a wartet → abgelehnt
      expect((await api(page, 'POST', url(ga, a), { predecessor_task_id: b.id })).status).toBe(400)
      const back = await api<Task>(page, 'PATCH', `${base}/${gb.id}/tasks/${b.id}/dependencies/${depId}`, { mode: 'simultaneous' })
      expect(back.data.waiting).toBe(false)
      expect((await api(page, 'POST', `${url(gb, b)}/${depId}/release`, { reason: 'x' })).status).toBe(400) // gleichzeitig: keine Freigabe

      // Browser: Dialog von Bauauftrag B, Aufgabenblock zeigt Verknüpfung samt Konflikt, Button vorhanden
      await page.goto(`/${DEPT}/ga/planning`)
      await page.locator('article', { hasText: NAME_B }).first().locator('button:has-text("Öffnen")').click()
      const dialog = page.locator('.v-dialog .v-overlay__content')
      await dialog.getByTestId('order-tab-tasks').click()
      const block = dialog.getByTestId('task-dependencies-block').first()
      await expect(block.getByTestId('dep-mode-simultaneous')).toBeVisible()
      await expect(block.getByTestId('dep-schedule')).toContainText('Zeitkonflikt')
      await expect(block.getByTestId('dep-trace')).toContainText('Angelegt von')
      await expect(block.getByTestId('task-dependency-add')).toBeVisible()
      await page.screenshot({ path: 'test-results/ga-dialog-simultaneous.png' })

      // Neue Verknüpfung über den Button: Kandidaten aus anderem Bauauftrag (A) auswählbar
      await block.getByTestId('task-dependency-add').click()
      await block.getByTestId('dep-candidate-select').click()
      await expect(page.locator('.v-overlay--active .v-list-item', { hasText: NAME_A })).toHaveCount(0) // bereits verknüpft
      await expect(page.locator('.v-overlay--active .v-list-item').first()).toBeVisible()
      await page.keyboard.press('Escape')
    } finally {
      await page.keyboard.press('Escape').catch(() => {})
      for (const g of created) {
        const tasks = (await api<Task[]>(page, 'GET', `${base}/tasks`)).data ?? []
        for (const t of tasks.filter((x) => x.group_id === g.id)) {
          for (const d of t.dependencies ?? []) if (d.kind === 'task') await api(page, 'DELETE', `${base}/${g.id}/tasks/${t.id}/dependencies/${d.id}`)
        }
      }
      for (const g of created) {
        const tasks = (await api<Task[]>(page, 'GET', `${base}/tasks`)).data ?? []
        for (const t of tasks.filter((x) => x.group_id === g.id)) await api(page, 'DELETE', `${base}/${g.id}/tasks/${t.id}`)
        await api(page, 'DELETE', `${base}/${g.id}`)
      }
    }
    expect(problems).toEqual([])
  })
})
