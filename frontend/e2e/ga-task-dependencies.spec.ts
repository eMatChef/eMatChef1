import { test, expect, type Page } from '@playwright/test'
import { cleanupE2eInbox } from './helpers/inboxCleanup'

/**
 * GA-Aufgaben Phase 2.3 (Demo-Konto ga-mw): Abhängigkeit zwischen zwei Aufgaben anlegen, «Wartet auf …», Start gesperrt,
 * vorzeitige Freigabe mit Begründung, Zyklus und Löschen mit Abhängigkeit werden vom Server abgelehnt.
 * Schreibt zwei Demo-Aufgaben und räumt sie (inklusive Abhängigkeiten) am Ende auf. Aktiv nur mit E2E_GA_TASKS=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD.
 */
const email = process.env.E2E_USER_EMAIL
const password = process.env.E2E_USER_PASSWORD
const DEPT = process.env.E2E_DEPARTMENT_ID || '2d42e87c5197'
const STAMP = Date.now()
const FIRST = `E2E Vorgänger ${STAMP}`
const SECOND = `E2E Nachfolger ${STAMP}`

type ApiTask = { id: string; title: string; group_id: string; status: string; dependencies?: { id: string; kind: string }[] }

test.describe('GA Aufgaben Abhängigkeiten', () => {
  test.skip(process.env.E2E_GA_TASKS !== '1' || !email || !password, 'E2E_GA_TASKS=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD nötig')
  test.setTimeout(90_000)
  test.describe.configure({ retries: 3 }) // lokaler Vite-Dev-Server liefert einzelne Module gelegentlich nicht aus (net::ERR_FAILED)
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

  async function cleanup(page: Page) {
    const base = `/api/departments/${DEPT}/grossanlass/groups`
    const all = (await api<ApiTask[]>(page, 'GET', `${base}/tasks`)).data ?? []
    const mine = all.filter((task) => task.title === FIRST || task.title === SECOND)
    for (const task of mine) {
      for (const dep of task.dependencies ?? []) {
        if (dep.kind === 'task') await api(page, 'DELETE', `${base}/${task.group_id}/tasks/${task.id}/dependencies/${dep.id}`)
      }
    }
    for (const task of mine) await api(page, 'DELETE', `${base}/${task.group_id}/tasks/${task.id}`)
  }

  test('dependency waits, release, cycle and delete guards', async ({ page }) => {
    await login(page)
    const problems: string[] = []
    page.on('pageerror', (err) => problems.push(`pageerror: ${err.message}`))
    const base = `/api/departments/${DEPT}/grossanlass/groups`

    try {
      const group = (await api<ApiTask[]>(page, 'GET', `${base}/tasks`)).data[0]!.group_id
      const a = (await api<ApiTask>(page, 'POST', `${base}/${group}/tasks`, { title: FIRST })).data
      const b = (await api<ApiTask>(page, 'POST', `${base}/${group}/tasks`, { title: SECOND })).data

      await page.goto(`/${DEPT}/ga/tasks/all`)
      await expect(page.locator('.ga-card').first()).toBeVisible()
      await page.locator('.ga-card', { hasText: SECOND }).getByRole('button', { name: /Zuweisen|Öffnen/i }).first().click()
      const detail = page.locator('.v-dialog .v-overlay__content')

      // Abhängigkeit anlegen → «Wartet auf», Start und Fertigmeldung gesperrt
      await detail.getByTestId('dep-candidate-select').click()
      await page.locator('.v-overlay--active .v-list-item', { hasText: FIRST }).click()
      await detail.getByTestId('dep-add-submit').click()
      await expect(detail.getByTestId('task-waiting')).toContainText(FIRST)
      await expect(detail.getByRole('button', { name: /Starten/i })).toBeDisabled()
      await expect(detail.getByRole('button', { name: /Fertig melden/i })).toBeDisabled()
      await page.screenshot({ path: 'test-results/ga-task-waiting.png' })

      // Server verweigert Start trotzdem, Zyklus und Löschen mit Abhängigkeit
      expect((await api(page, 'PATCH', `${base}/${group}/tasks/${b.id}`, { status: 'progress' })).status).toBe(400)
      expect((await api(page, 'POST', `${base}/${group}/tasks/${a.id}/dependencies`, { predecessor_task_id: b.id })).status).toBe(400)
      expect((await api(page, 'DELETE', `${base}/${group}/tasks/${a.id}`)).status).toBe(403)

      // Vorzeitig freigeben (Begründung Pflicht, protokolliert)
      await detail.getByRole('button', { name: 'Vorzeitig freigeben' }).click()
      await detail.getByLabel('Begründung der Freigabe').fill('Material ist bereits da')
      await detail.getByRole('button', { name: 'Freigeben', exact: true }).click()
      await expect(detail.getByTestId('task-waiting')).toHaveCount(0)
      await expect(detail.getByTestId('task-dependencies')).toContainText('Material ist bereits da')
      await detail.getByRole('button', { name: /Starten/i }).click()
      await expect(detail.getByText('In Arbeit').first()).toBeVisible()
      await page.screenshot({ path: 'test-results/ga-task-released-dep.png' })

      // Art in der Detailansicht wechseln: gleichzeitig sperrt nichts, Zeitkonflikt-Hinweis und Nachvollziehbarkeit sichtbar
      await detail.getByTestId('dep-switch-mode').click()
      await expect(detail.getByTestId('dep-mode-simultaneous')).toBeVisible()
      await expect(detail.getByTestId('dep-schedule')).toBeVisible()
      await expect(detail.getByTestId('dep-trace')).toContainText('Art geändert')
      await page.screenshot({ path: 'test-results/ga-task-detail-simultaneous.png' })
    } finally {
      await page.keyboard.press('Escape')
      await cleanup(page)
    }
    expect(problems).toEqual([])
  })
})
