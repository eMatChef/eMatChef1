import { test, expect, type Page } from '@playwright/test'
import { cleanupE2eInbox } from './helpers/inboxCleanup'

/**
 * GA-Aufgaben Phase 2.1 (Demo-Konto ga-mw): Checkliste anlegen und abhaken, Aufgabe mit Begründung blockieren,
 * Starten/Fertig melden gesperrt, Blockierung aufheben, Fertigmeldung, Rückgabe mit Begründung, erneute Meldung, Abnahme. Schreibt eine Demo-Aufgabe und löscht sie am Ende wieder.
 * Aktiv nur mit E2E_GA_TASKS=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD.
 */
const email = process.env.E2E_USER_EMAIL
const password = process.env.E2E_USER_PASSWORD
const DEPT = process.env.E2E_DEPARTMENT_ID || '2d42e87c5197'
const TITLE = `E2E Checkliste ${Date.now()}`

test.describe('GA Aufgaben Checkliste und Blockierung', () => {
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

  async function cleanup(page: Page) {
    await page.evaluate(async ({ dept, title }) => {
      const tasks = (await (await fetch(`/api/departments/${dept}/grossanlass/groups/tasks`, { credentials: 'include' })).json()) as { id: string; title: string; group_id: string }[]
      for (const task of tasks.filter((row) => row.title === title)) {
        await fetch(`/api/departments/${dept}/grossanlass/groups/${task.group_id}/tasks/${task.id}`, { method: 'DELETE', credentials: 'include' })
      }
    }, { dept: DEPT, title: TITLE })
  }

  test('checklist, block, release', async ({ page }) => {
    await login(page)
    const problems: string[] = []
    page.on('pageerror', (err) => problems.push(`pageerror: ${err.message}`))
    page.on('response', (res) => res.url().includes('/api/') && res.status() >= 400 && problems.push(`${res.status()} ${res.request().method()} ${res.url()}`))

    try {
      await page.goto(`/${DEPT}/ga/tasks`)
      await expect(page.locator('.ga-card').first()).toBeVisible()

      await page.getByRole('button', { name: /Neue Aufgabe/i }).click()
      const dialog = page.locator('.v-dialog .v-overlay__content')
      await dialog.locator('.v-select').first().click()
      await page.locator('.v-overlay--active .v-list-item').first().click()
      await dialog.getByLabel('Aufgabe', { exact: true }).fill(TITLE)
      await dialog.getByRole('button', { name: /Speichern/i }).click()
      await expect(dialog).toHaveCount(0)
      const card = page.locator('.ga-card', { hasText: TITLE })
      await expect(card).toBeVisible()
      await card.getByRole('button', { name: /Zuweisen|Öffnen/i }).first().click()

      const detail = page.locator('.v-dialog .v-overlay__content')
      const checklist = detail.getByTestId('task-checklist')

      // Checkliste anlegen
      for (const label of ['Zuschnitt', 'Verschrauben']) {
        await checklist.getByLabel('Neuer Punkt').fill(label)
        await checklist.getByRole('button', { name: 'Hinzufügen' }).click()
        await expect(checklist.getByText(label, { exact: true })).toBeVisible()
      }
      // Reihenfolge ändern: «Verschrauben» nach oben
      await checklist.locator('li').nth(1).getByRole('button', { name: 'Nach oben' }).click()
      await expect(checklist.locator('li').first()).toContainText('Verschrauben')

      // Abhaken → 50 %
      await checklist.locator('li').first().getByRole('checkbox').check()
      await expect(detail.getByText('50 %')).toBeVisible()

      // Blockieren mit Begründung
      await detail.getByRole('button', { name: 'Problem melden' }).click()
      await detail.getByLabel('Was blockiert die Aufgabe?').fill('Material fehlt')
      await detail.getByRole('button', { name: 'Blockieren', exact: true }).click()
      const blocked = detail.getByTestId('task-blocked')
      await expect(blocked).toContainText('Material fehlt')
      await expect(detail.getByRole('button', { name: /Starten/i })).toBeDisabled()
      await expect(detail.getByRole('button', { name: /Fertig melden/i })).toBeDisabled()
      await expect(checklist.locator('li').nth(1).getByRole('checkbox')).toBeDisabled()
      await page.screenshot({ path: 'test-results/ga-task-blocked.png' })

      // Freigeben
      await blocked.getByRole('button', { name: 'Blockierung aufheben' }).click()
      await expect(detail.getByTestId('task-blocked')).toHaveCount(0)
      await detail.getByRole('button', { name: /Starten/i }).click()
      await expect(detail.getByText('In Arbeit').first()).toBeVisible()
      await page.screenshot({ path: 'test-results/ga-task-released.png' })

      // Fertigmeldung (ohne Kommentar) → noch nicht erledigt, dann zurückgeben, erneut melden, abnehmen
      await detail.getByRole('button', { name: /Fertig melden/i }).click()
      const review = detail.getByTestId('task-review')
      await expect(review).toBeVisible()
      await expect(detail.getByRole('button', { name: /Starten/i })).toHaveCount(0)
      await review.getByRole('button', { name: 'Zur Nachbearbeitung zurückgeben' }).click()
      await review.getByLabel('Was muss nachgebessert werden?').fill('Kante fehlt')
      await review.getByRole('button', { name: 'Zurückgeben', exact: true }).click()
      await expect(detail.getByTestId('task-review')).toHaveCount(0)
      await expect(detail.getByTestId('task-history')).toContainText('Kante fehlt')
      await detail.getByRole('button', { name: /Fertig melden/i }).click()
      await detail.getByTestId('task-review').getByRole('button', { name: 'Abnehmen' }).click()
      await expect(detail.getByTestId('task-review')).toHaveCount(0)
      await expect(detail.getByText('100 %')).toBeVisible()
      await expect(detail.getByTestId('task-history')).toContainText('Abgenommen')
      await page.screenshot({ path: 'test-results/ga-task-accepted.png' })
    } finally {
      await page.keyboard.press('Escape')
      await cleanup(page)
    }
    expect(problems).toEqual([])
  })
})
