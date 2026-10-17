import { test, expect, type Page } from '@playwright/test'
import { cleanupE2eInbox } from './helpers/inboxCleanup'

/**
 * GA Phase 2.4 (Demo-Konto ga-mw): formelle Gesamtabnahme eines Bauauftrags im Bauauftrag-Dialog (Reiter Aufgaben):
 * melden, zurückweisen (Pflicht-Begründung, Einzelaufgaben bleiben abgenommen), erneut melden, abnehmen; danach sind Aufgaben
 * geschützt, bis die Abnahme widerrufen wird. Legt einen eigenen Demo-Auftrag mit zwei Aufgaben an und löscht ihn am Ende wieder.
 * Aktiv nur mit E2E_GA_TASKS=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD.
 */
const email = process.env.E2E_USER_EMAIL
const password = process.env.E2E_USER_PASSWORD
const DEPT = process.env.E2E_DEPARTMENT_ID || '2d42e87c5197'
const PARENT = process.env.E2E_ORDER_PARENT_ID || 'grpfc7ea0fde' // Bereich «Bauten»
const NAME = `E2E Abnahme ${Date.now()}`

type Api<T> = { status: number; data: T }

test.describe('GA Gesamtabnahme Bauauftrag', () => {
  test.skip(process.env.E2E_GA_TASKS !== '1' || !email || !password, 'E2E_GA_TASKS=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD nötig')
  test.setTimeout(120_000)
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

  async function api<T>(page: Page, method: string, path: string, body?: unknown): Promise<Api<T>> {
    return page.evaluate(async ({ method, path, body }) => {
      const res = await fetch(path, { method, credentials: 'include', headers: { 'Content-Type': 'application/json' }, body: body === undefined ? undefined : JSON.stringify(body) })
      return { status: res.status, data: await res.json().catch(() => null) }
    }, { method, path, body }) as Promise<Api<T>>
  }

  test('report, reject, report, accept, guard, revoke', async ({ page }) => {
    await login(page)
    const base = `/api/departments/${DEPT}/grossanlass/groups`
    let groupId = ''
    try {
      // Bauauftrag mit zwei abgenommenen Aufgaben vorbereiten
      const group = await api<{ id: string }>(page, 'POST', base, { name: NAME, parent_id: PARENT, kind: 'auftrag' })
      expect(group.status).toBeLessThan(300)
      groupId = group.data.id
      const taskIds: string[] = []
      for (const title of ['E2E Teil 1', 'E2E Teil 2']) {
        const task = await api<{ id: string }>(page, 'POST', `${base}/${groupId}/tasks`, { title })
        taskIds.push(task.data.id)
        expect((await api(page, 'POST', `${base}/${groupId}/tasks/${task.data.id}/report`, {})).status).toBe(200)
        expect((await api(page, 'POST', `${base}/${groupId}/tasks/${task.data.id}/accept`)).status).toBe(200)
      }

      // Dialog öffnen → Reiter Aufgaben
      await page.goto(`/${DEPT}/ga/planning`)
      await page.locator('article', { hasText: NAME }).first().locator('button:has-text("Öffnen")').click()
      const dialog = page.locator('.v-dialog .v-overlay__content')
      await expect(dialog).toBeVisible()
      await dialog.getByTestId('order-tab-tasks').click()
      const acceptance = dialog.getByTestId('order-acceptance')
      await expect(acceptance).toContainText('2 von 2 Aufgaben abgenommen')

      // melden → zurückweisen (Pflicht-Begründung) → Einzelaufgaben bleiben erledigt
      await acceptance.getByRole('button', { name: 'Zur Abnahme melden' }).click()
      await expect(acceptance.getByTestId('order-acceptance-status')).toContainText('Zur Abnahme gemeldet')
      await acceptance.getByRole('button', { name: 'Zurückweisen' }).click()
      await expect(acceptance.getByRole('button', { name: 'Bestätigen' })).toBeDisabled()
      await acceptance.getByLabel('Begründung der Rückweisung').fill('Kante fehlt')
      await acceptance.getByRole('button', { name: 'Bestätigen' }).click()
      await expect(acceptance.getByTestId('order-acceptance-status')).toContainText('Offen')
      await expect(acceptance.getByTestId('order-acceptance-history')).toContainText('Kante fehlt')
      const tasks = (await api<{ id: string; status: string }[]>(page, 'GET', `${base}/tasks`)).data.filter((t) => taskIds.includes(t.id))
      expect(tasks.map((t) => t.status)).toEqual(['done', 'done'])

      // Nachbesserung gezielt anlegen: verknüpft mit der Rückweisung, nichts wird automatisch zugewiesen
      const rework = acceptance.getByTestId('order-rework')
      await expect(rework).toContainText('Noch keine Nachbesserungsaufgaben')
      await rework.getByTestId('order-rework-create').click()
      await expect(acceptance).toContainText('nichts wird automatisch zugewiesen')
      await acceptance.getByLabel('Aufgabe', { exact: true }).fill('E2E Kante nachbessern')
      await acceptance.getByRole('button', { name: 'Anlegen', exact: true }).click()
      await expect(rework).toContainText('E2E Kante nachbessern')
      await expect(acceptance.getByTestId('order-rework-open')).toBeVisible()
      await expect(acceptance.getByRole('button', { name: 'Zur Abnahme melden' })).toHaveCount(0)
      const created = (await api<{ id: string; title: string; assignee_user_id: string | null; rework: { reason: string } | null; status: string }[]>(page, 'GET', `${base}/tasks`)).data.find((t) => t.title === 'E2E Kante nachbessern')!
      expect(created.assignee_user_id).toBeNull()
      expect(created.rework?.reason).toBe('Kante fehlt')
      expect((await api(page, 'POST', `${base}/${groupId}/acceptance/report`, {})).status).toBe(400)
      expect((await api(page, 'DELETE', `${base}/${groupId}/tasks/${created.id}`)).status).toBe(400)
      // Nachbesserung durchläuft den normalen Workflow (Fertigmeldung, Einzelabnahme)
      expect((await api(page, 'POST', `${base}/${groupId}/tasks/${created.id}/report`, {})).status).toBe(200)
      expect((await api(page, 'POST', `${base}/${groupId}/tasks/${created.id}/accept`)).status).toBe(200)
      await dialog.getByTestId('order-tab-basics').click()
      await dialog.getByTestId('order-tab-tasks').click()
      await expect(acceptance.getByTestId('order-rework-open')).toHaveCount(0)
      const stillDone = (await api<{ id: string; status: string }[]>(page, 'GET', `${base}/tasks`)).data.filter((t) => taskIds.includes(t.id))
      expect(stillDone.map((t) => t.status)).toEqual(['done', 'done'])

      // erneut melden → abnehmen
      await acceptance.getByRole('button', { name: 'Zur Abnahme melden' }).click()
      await acceptance.getByRole('button', { name: 'Bauauftrag abnehmen' }).click()
      await expect(acceptance.getByTestId('order-acceptance-status')).toContainText('Abgenommen')
      await page.screenshot({ path: 'test-results/ga-order-accepted.png' })

      // Abnahme ist geschützt: Aufgaben anlegen, löschen, wieder öffnen sind gesperrt
      expect((await api(page, 'POST', `${base}/${groupId}/tasks`, { title: 'E2E Spät' })).status).toBe(400)
      expect((await api(page, 'DELETE', `${base}/${groupId}/tasks/${taskIds[0]}`)).status).toBe(400)
      expect((await api(page, 'PATCH', `${base}/${groupId}/tasks/${taskIds[0]}`, { status: 'progress' })).status).toBe(400)

      // widerrufen (Begründung) → Aufgaben wieder änderbar
      await acceptance.getByRole('button', { name: 'Abnahme widerrufen' }).click()
      await acceptance.getByLabel('Begründung des Widerrufs').fill('Nachmessung nötig')
      await acceptance.getByRole('button', { name: 'Bestätigen' }).click()
      await expect(acceptance.getByTestId('order-acceptance-status')).toContainText('Offen')
      expect((await api(page, 'PATCH', `${base}/${groupId}/tasks/${taskIds[0]}`, { status: 'progress' })).status).toBe(200)
      await page.keyboard.press('Escape')
    } finally {
      if (groupId) {
        const tasks = (await api<{ id: string; group_id: string }[]>(page, 'GET', `${base}/tasks`)).data ?? []
        for (const task of tasks.filter((t) => t.group_id === groupId)) await api(page, 'DELETE', `${base}/${groupId}/tasks/${task.id}`)
        await api(page, 'DELETE', `${base}/${groupId}`)
      }
    }
  })
})
