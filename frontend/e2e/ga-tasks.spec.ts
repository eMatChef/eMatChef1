import { test, expect, type Page } from '@playwright/test'
import { cleanupE2eInbox } from './helpers/inboxCleanup'

/**
 * GA-Aufgaben (Demo-Konto ga-mw): /ga/tasks und /ga/tasks/mine mit echten Daten, Aufgabe anlegen, starten, erledigen, Zuständige ändern.
 * Schreibt eine Demo-Aufgabe und löscht sie am Ende wieder. Aktiv nur mit E2E_GA_TASKS=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD.
 */
const email = process.env.E2E_USER_EMAIL
const password = process.env.E2E_USER_PASSWORD
const DEPT = process.env.E2E_DEPARTMENT_ID || '2d42e87c5197'
const TITLE = `E2E Aufgabe ${Date.now()}`

test.describe('GA Aufgaben', () => {
  test.skip(process.env.E2E_GA_TASKS !== '1' || !email || !password, 'E2E_GA_TASKS=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD nötig')
  test.setTimeout(60_000)
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

  test('list, create, assign, start, report, accept', async ({ page }) => {
    await login(page)
    const problems: string[] = []
    page.on('console', (msg) => msg.type() === 'error' && problems.push(`console: ${msg.text()}`))
    page.on('pageerror', (err) => problems.push(`pageerror: ${err.message}`))
    page.on('response', (res) => res.url().includes('/api/') && res.status() >= 400 && problems.push(`${res.status()} ${res.request().method()} ${res.url()}`))

    const tasksResponse = page.waitForResponse((res) => res.url().endsWith('/grossanlass/groups/tasks'))
    await page.goto(`/${DEPT}/ga/tasks`)
    const apiTasks = (await (await tasksResponse).json()) as { id: string; status: string; group_id: string }[]
    expect(apiTasks.length).toBeGreaterThan(0)
    expect(apiTasks.every((task) => ['open', 'progress', 'review', 'done'].includes(task.status))).toBe(true)
    await expect(page.locator('.ga-card').first()).toBeVisible()
    await expect(page.locator('.ga-card')).not.toHaveCount(0)
    await expect(page.getByText('Prototyp')).toHaveCount(0)
    expect(await page.locator('.ga-card').count()).toBeLessThanOrEqual(apiTasks.length)
    await page.screenshot({ path: 'test-results/ga-tasks-list.png', fullPage: true })

    // Navigation: im GA-Aufgabenbereich nur «Aufgaben» und «Meine Aufgaben»
    const tabs = page.locator('.tasks-shell-tabs .v-tab')
    await expect(tabs).toHaveText([/^Aufgaben$/, /^Meine Aufgaben$/])
    await tabs.nth(1).click()
    await expect(page).toHaveURL(new RegExp(`/${DEPT}/ga/tasks/mine$`))
    await tabs.nth(0).click()
    await expect(page).toHaveURL(new RegExp(`/${DEPT}/ga/tasks$`))
    await expect(page.locator('.ga-card').first()).toBeVisible()

    // anlegen
    await page.getByRole('button', { name: /Neue Aufgabe/i }).click()
    const dialog = page.locator('.v-dialog .v-overlay__content')
    await dialog.locator('.v-select').first().click()
    await page.locator('.v-overlay--active .v-list-item').first().click()
    await dialog.getByLabel('Aufgabe', { exact: true }).fill(TITLE)
    await dialog.getByRole('button', { name: /Speichern/i }).click()
    await expect(dialog).toHaveCount(0)
    const card = page.locator('.ga-card', { hasText: TITLE })
    await expect(card).toBeVisible()

    // Zuständige setzen, starten, erledigen
    await card.getByRole('button', { name: /Zuweisen|Öffnen/i }).first().click()
    const detail = page.locator('.v-dialog .v-overlay__content')
    await detail.locator('.aufgabe-detail__assign .v-select').click()
    await page.locator('.v-overlay--active .v-list-item').first().click()
    await detail.getByRole('button', { name: /Zuweisen/i }).click()
    await expect(detail.locator('.v-chip', { hasText: /\w/ }).nth(2)).toBeVisible()
    await detail.getByRole('button', { name: /Starten/i }).click()
    await expect(detail.getByText('In Arbeit').first()).toBeVisible()
    await detail.getByRole('button', { name: /Fertig melden/i }).click()
    await expect(detail.getByTestId('task-review')).toBeVisible()
    await detail.getByRole('button', { name: 'Abnehmen' }).click()
    await expect(detail.getByText('100 %')).toBeVisible()
    await page.screenshot({ path: 'test-results/ga-tasks-detail.png' })
    await detail.page().keyboard.press('Escape')

    // eigene Aufgaben
    await page.goto(`/${DEPT}/ga/tasks/mine`)
    await expect(page.locator('.meine')).toBeVisible()
    await expect(page.locator('.v-progress-circular')).toHaveCount(0)
    await page.screenshot({ path: 'test-results/ga-tasks-mine.png', fullPage: true })

    // Aufräumen: Demo-Aufgabe wieder löschen
    const created = (await page.evaluate(async (dept) => (await fetch(`/api/departments/${dept}/grossanlass/groups/tasks`, { credentials: 'include' })).json(), DEPT)) as { id: string; title: string; group_id: string }[]
    const mine = created.find((task) => task.title === TITLE)
    if (mine) {
      await page.evaluate(async ({ dept, group, id }) => {
        await fetch(`/api/departments/${dept}/grossanlass/groups/${group}/tasks/${id}`, { method: 'DELETE', credentials: 'include' })
      }, { dept: DEPT, group: mine.group_id, id: mine.id })
    }
    expect(problems).toEqual([])
  })
})
