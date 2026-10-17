import { test, expect, type Page } from '@playwright/test'
import { cleanupE2eInbox } from './helpers/inboxCleanup'

/**
 * GA: Bauaufträge archivieren statt löschen (Demo-Konto ga-mw). Ein Bauauftrag ohne Arbeit lässt sich löschen; sobald eine Aufgabe
 * begonnen oder abgenommen wurde, ist das Löschen verboten und er wird mit Begründung archiviert: aus der Liste ausgeblendet, über
 * «Archivierte anzeigen» auffindbar, nicht mehr bearbeitbar, wiederherstellbar. Legt eigene Demo-Aufträge an und räumt sie wieder auf.
 * Aktiv nur mit E2E_GA_TASKS=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD.
 */
const email = process.env.E2E_USER_EMAIL
const password = process.env.E2E_USER_PASSWORD
const DEPT = process.env.E2E_DEPARTMENT_ID || '2d42e87c5197'
const PARENT = process.env.E2E_ORDER_PARENT_ID || 'grpfc7ea0fde' // Bereich «Bauten»
const STAMP = Date.now()
const NAME = `E2E Archiv ${STAMP}`

type Api<T> = { status: number; data: T }

test.describe('GA Bauauftrag archivieren', () => {
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

  test('delete only without work, otherwise archive, hide, find, restore', async ({ page }) => {
    await login(page)
    const base = `/api/departments/${DEPT}/grossanlass/groups`
    const ids: string[] = []
    try {
      // ohne Arbeit: physisches Löschen erlaubt
      const empty = await api<{ id: string }>(page, 'POST', base, { name: `${NAME} leer`, parent_id: PARENT, kind: 'auftrag' })
      ids.push(empty.data.id)
      expect((await api(page, 'DELETE', `${base}/${empty.data.id}`)).status).toBe(200)
      ids.pop()

      // mit Arbeit: Löschen verboten, Archivieren möglich
      const order = await api<{ id: string }>(page, 'POST', base, { name: NAME, parent_id: PARENT, kind: 'auftrag' })
      const groupId = order.data.id
      ids.push(groupId)
      const task = await api<{ id: string }>(page, 'POST', `${base}/${groupId}/tasks`, { title: 'E2E Archiv Aufgabe' })
      expect((await api(page, 'PATCH', `${base}/${groupId}/tasks/${task.data.id}`, { status: 'progress' })).status).toBe(200)
      const refused = await api<{ error: string }>(page, 'DELETE', `${base}/${groupId}`)
      expect(refused.status).toBe(409)
      expect(refused.data.error).toContain('archivieren')

      // UI: in der Auftragsliste → Dialog → Archivieren mit Begründung
      await page.goto(`/${DEPT}/ga/planning/jobs`)
      const card = page.locator('article', { hasText: NAME }).first()
      await card.locator('button:has-text("Öffnen")').click()
      const dialog = page.locator('.v-dialog .v-overlay__content').first()
      await expect(dialog).toBeVisible()
      await expect(dialog.getByRole('button', { name: 'Löschen' })).toHaveCount(0) // hat Arbeit: nur archivieren
      await dialog.getByTestId('order-archive').click()
      const confirm = page.getByTestId('order-archive-confirm')
      await expect(confirm).toBeDisabled()
      await page.getByTestId('order-archive-reason').locator('input').fill('Auftrag abgebrochen')
      await confirm.click()
      await expect(page.locator('article', { hasText: NAME })).toHaveCount(0) // aus der aktiven Liste ausgeblendet

      // Filter «Archivierte anzeigen»: auffindbar, gekennzeichnet, nicht mehr bearbeitbar
      await page.getByTestId('show-archived').click()
      const archived = page.locator('article', { hasText: NAME }).first()
      await expect(archived.getByTestId('order-archived-chip')).toBeVisible()
      const state = await api<{ archived: boolean; archive_reason: string; log: { type: string }[] }>(page, 'GET', `${base}/${groupId}/archive`)
      expect(state.data.archived).toBe(true)
      expect(state.data.archive_reason).toBe('Auftrag abgebrochen')
      expect(state.data.log.map((l) => l.type)).toEqual(['archived'])
      expect((await api(page, 'POST', `${base}/${groupId}/tasks`, { title: 'E2E Spät' })).status).toBe(400)
      expect((await api(page, 'PATCH', `${base}/${groupId}/tasks/${task.data.id}`, { status: 'done' })).status).toBe(400)
      expect((await api(page, 'DELETE', `${base}/${groupId}`)).status).toBe(409)
      await page.screenshot({ path: 'test-results/ga-order-archived.png' })

      // wiederherstellen
      await archived.locator('button:has-text("Öffnen")').click()
      const archivedDialog = page.locator('.v-dialog .v-overlay__content').first()
      await expect(archivedDialog.getByTestId('order-archived-banner')).toContainText('Auftrag abgebrochen')
      await archivedDialog.getByTestId('order-restore').click()
      await page.getByTestId('show-archived').click() // Filter wieder aus
      await expect(page.locator('article', { hasText: NAME }).first()).toBeVisible()
      expect((await api(page, 'PATCH', `${base}/${groupId}/tasks/${task.data.id}`, { status: 'open' })).status).toBe(200)
    } finally {
      for (const id of ids) {
        // Aufgaben-Historie schützt vor Löschen: in dieser Demo-Umgebung bleibt die Testgruppe notfalls archiviert statt gelöscht
        const tasks = (await api<{ id: string; group_id: string }[]>(page, 'GET', `${base}/tasks`)).data ?? []
        for (const t of tasks.filter((x) => x.group_id === id)) await api(page, 'DELETE', `${base}/${id}/tasks/${t.id}`)
        const del = await api(page, 'DELETE', `${base}/${id}`)
        if (del.status !== 200) await api(page, 'POST', `${base}/${id}/archive`, { reason: 'E2E Aufräumen' })
      }
    }
  })
})
