import { test, expect, type Page } from '@playwright/test'

/**
 * GA-Helferpool: Helfer erfassen/bearbeiten (Accordions, Fähigkeiten, Führerausweise), Konto-Helfer, Helfer-Unterreiter,
 * Fähigkeiten im Personalbedarf und Hinweis bei der Zuteilung.
 * Legt Helfer, Fähigkeiten und einen Personalbedarf an: läuft nur gegen eine isolierte, migrierte Kopie (nie `mvdb`),
 * aktiv mit E2E_GA_HELFERPOOL=1, E2E_USER_EMAIL / E2E_USER_PASSWORD (GA-HV der Demo-GA).
 */
const email = process.env.E2E_USER_EMAIL
const password = process.env.E2E_USER_PASSWORD
const DEPT = process.env.E2E_DEPARTMENT_ID || '2d42e87c5197'
const RUN = Date.now().toString(36)
const SKILL = `E2E-Schweissen-${RUN}`
const LAST = `Extern-${RUN}`

test.describe('GA Helferpool', () => {
  test.skip(process.env.E2E_GA_HELFERPOOL !== '1' || !email || !password, 'E2E_GA_HELFERPOOL=1 sowie E2E_USER_EMAIL / E2E_USER_PASSWORD nötig')
  test.setTimeout(120_000)
  test.describe.configure({ retries: 1 }) // Vite-Dev-Server liefert beim ersten Laden gelegentlich Module neu aus
  test.use({ ignoreHTTPSErrors: true })

  async function login(page: Page) {
    await page.goto('/login')
    await page.getByRole('textbox', { name: /E-Mail/i }).fill(email!)
    await page.locator('input[type="password"]').first().fill(password!)
    await page.getByRole('button', { name: /Anmelden/i }).click()
    await page.waitForURL((url) => !/\/login(?:\?|$)/.test(url.pathname), { timeout: 60_000 })
    await page.waitForLoadState('networkidle')
  }

  test('Konto-Helfer, externer Helfer, Fähigkeiten, Führerausweis, beide Ansichten', async ({ page }) => {
    const problems: string[] = []
    page.on('pageerror', (err) => !/WebSocket/.test(err.message) && problems.push(`pageerror: ${err.message}`)) // Vite-HMR-Socket im isolierten Stack ignorieren
    page.on('response', (res) => res.url().includes('/api/') && res.status() >= 500 && problems.push(`${res.status()} ${res.url()}`))
    await login(page)

    // ── Helferpool: Konto-Helfer erscheinen, externe ohne Konto
    await page.goto(`/${DEPT}/ga/helper-pool`)
    await expect(page.locator('article.helper', { hasText: 'Logistikwart' })).toBeVisible()
    await expect(page.locator('article.helper', { hasText: 'GA · Helfer' })).toBeVisible()

    // ── Externen Helfer erfassen (Accordions)
    await page.getByRole('button', { name: 'Helfer erfassen' }).click()
    const dialog = page.getByRole('dialog')
    await expect(dialog.getByTestId('ga-helper-accordions')).toBeVisible()
    await expect(dialog.getByLabel('Vorname')).toBeVisible() // «Persönliche Angaben» ist offen
    await dialog.getByLabel('Vorname').fill('Erika')
    await dialog.getByLabel('Nachname').fill(LAST)
    await dialog.getByLabel('Telefon').fill('079 000 11 22')
    await dialog.getByRole('button', { name: 'Berufe & Fähigkeiten' }).click()
    await dialog.getByLabel('Beruf / Ausbildung').fill('Schreinerin')
    const picker = dialog.getByTestId('ga-skill-picker').locator('input')
    await picker.fill(SKILL)
    await picker.press('Enter')
    await expect(dialog.locator('.v-chip', { hasText: SKILL })).toBeVisible()
    await dialog.getByRole('button', { name: 'Führerausweise & Berechtigungen' }).click()
    await dialog.locator('.drive-item', { hasText: /^\s*B\b/ }).first().locator('input').check()
    await dialog.getByRole('button', { name: 'Qualifikation hinzufügen' }).click()
    await dialog.getByLabel('Qualifikation / Nachweis').fill('Schweisszertifikat')
    await dialog.getByRole('button', { name: 'Verfügbarkeit' }).click()
    await dialog.getByRole('button', { name: 'Verfügbarkeit hinzufügen' }).click()
    await page.screenshot({ path: 'test-results/ga-helferpool-dialog.png' })
    const saved = page.waitForResponse((r) => /grossanlass\/helpers$/.test(r.url()) && r.request().method() === 'POST')
    await dialog.getByRole('button', { name: 'Speichern' }).click()
    expect((await saved).status()).toBe(201)
    await expect(dialog).toBeHidden()
    const card = page.locator('article.helper', { hasText: LAST })
    await expect(card).toBeVisible()
    await expect(card.getByTestId('helper-card-profile')).toContainText(SKILL)

    // ── Neu laden, Suche nach Fähigkeit, Bearbeiten lädt gespeicherte Werte
    await page.reload()
    await page.locator('.hp__filters input').first().fill(SKILL)
    await expect(page.locator('article.helper', { hasText: LAST })).toBeVisible()
    await page.locator('article.helper', { hasText: LAST }).getByRole('button', { name: 'Details' }).click()
    await expect(page.getByTestId('ga-helper-profile')).toContainText(SKILL)
    await expect(page.getByTestId('ga-helper-profile')).toContainText('Schweisszertifikat')
    await page.getByRole('button', { name: 'Bearbeiten' }).click()
    const edit = page.getByRole('dialog')
    await edit.getByRole('button', { name: 'Berufe & Fähigkeiten' }).click()
    await expect(edit.getByLabel('Beruf / Ausbildung')).toHaveValue('Schreinerin')
    await expect(edit.locator('.v-chip', { hasText: SKILL })).toBeVisible()
    await edit.getByRole('button', { name: 'Führerausweise & Berechtigungen' }).click()
    await expect(edit.locator('.drive-item', { hasText: /^\s*B\b/ }).first().locator('input')).toBeChecked()
    await expect(edit.getByLabel('Qualifikation / Nachweis')).toHaveValue('Schweisszertifikat')
    await edit.getByRole('button', { name: 'Abbrechen' }).click()

    // ── Unterreiter «Helfer»: dieselben Daten, kein Duplikat
    await page.goto(`/${DEPT}/ga/activity-settings/users`)
    await page.getByRole('tab', { name: /Helfer/ }).click()
    await expect(page.getByTestId('helper-dir-row').first()).toBeVisible({ timeout: 60_000 }) // Benutzerverwaltung lädt im Dev-Server langsam
    await expect(page.getByTestId('helper-dir-row').filter({ hasText: LAST })).toHaveCount(1)
    await expect(page.getByTestId('helper-dir-row').filter({ hasText: LAST }).getByTestId('helper-dir-profile')).toContainText(SKILL)
    await page.getByLabel('Helfer suchen').fill(SKILL)
    await expect(page.getByTestId('helper-dir-row')).toHaveCount(1)
    await page.getByRole('button', { name: 'Ohne Konto' }).click()
    await expect(page.getByTestId('helper-dir-row')).toHaveCount(1)
    await page.getByRole('button', { name: 'Mit Konto' }).first().click()
    await expect(page.getByTestId('helper-dir-row')).toHaveCount(0)
    await page.screenshot({ path: 'test-results/ga-helferpool-directory.png' })

    // ── Bestehenden Benutzer als Helfer hinzufügen (Verknüpfung, Profil bleibt Quelle)
    await page.goto(`/${DEPT}/ga/helper-pool`)
    await page.getByRole('button', { name: 'Helfer erfassen' }).click()
    const link = page.getByRole('dialog')
    await link.getByTestId('ga-helper-user').click()
    const option = page.getByRole('option').filter({ hasText: /@demo\.ematchef\.ch/ }).first() // erstes noch nicht verknüpftes Demo-Konto
    const accountEmail = ((await option.innerText()).split(' · ').pop() ?? '').trim()
    await option.click()
    await expect(link.getByLabel('Vorname')).toBeDisabled()
    await link.getByRole('button', { name: 'Berufe & Fähigkeiten' }).click()
    await link.getByLabel('Beruf / Ausbildung').fill('Koch')
    await link.getByRole('button', { name: 'Speichern' }).click()
    await expect(link.getByRole('alert')).toHaveCount(0)
    await expect(link).toBeHidden()
    const linked = page.locator('article.helper', { hasText: accountEmail })
    await expect(linked).toHaveCount(1)
    await page.reload()
    await expect(page.locator('article.helper', { hasText: accountEmail })).toHaveCount(1) // kein Duplikat nach Konten-Abgleich

    // ── Personalbedarf mit Funktionswunsch = Fähigkeit; passende Helfer bei der Zuteilung
    const overview = await (await page.request.get(`/api/departments/${DEPT}/grossanlass/staffing/overview`)).json()
    const groupId = overview.needs[0].group_id as string
    const created = await page.request.post(`/api/departments/${DEPT}/grossanlass/groups/${groupId}/staffing-needs`, {
      data: { label: SKILL, required_count: 1, starts_at: '2027-05-31T08:00:00', ends_at: '2027-05-31T12:00:00' },
    })
    expect(created.status()).toBe(201)
    const skills = await (await page.request.get(`/api/departments/${DEPT}/grossanlass/helper-skills`)).json()
    expect(skills).toContain(SKILL) // Katalog enthält Funktionswunsch und Helfer-Fähigkeit
    await page.reload()
    await page.getByRole('tab', { name: 'Fähigkeiten' }).click()
    await expect(page.getByRole('row', { name: new RegExp(SKILL) })).toBeVisible()
    await page.getByRole('tab', { name: 'Personalbedarf' }).click()
    const needRow = page.locator('li, article, tr', { hasText: SKILL }).filter({ has: page.getByRole('button', { name: 'Helfer zuteilen' }) }).last()
    await needRow.getByRole('button', { name: 'Helfer zuteilen' }).click()
    const assign = page.getByRole('dialog')
    await assign.getByRole('combobox').first().click()
    await expect(page.getByRole('option').first()).toContainText(`✓ ${SKILL}`)
    await page.screenshot({ path: 'test-results/ga-helferpool-assign.png' })
    await page.keyboard.press('Escape')
    expect(problems, problems.join('\n')).toEqual([])
  })
  test('Personalbedarf im Auftragsdialog: Funktion aus dem Fähigkeitenkatalog wählen (rein lesend)', async ({ page }) => {
    await login(page)
    await page.locator('a[href$="/ga/planning"]').first().click()
    await page.locator('article', { hasText: 'Bar-Tresen West' }).first().locator('button:has-text("Öffnen")').click()
    await expect(page.locator('.v-dialog .v-overlay__content')).toBeVisible()
    await page.getByRole('tab', { name: /Aufgaben/ }).click()
    await page.locator('.ga-block__add').first().click()
    const helpers = page.locator('.ga-block__helpers').last()
    await helpers.scrollIntoViewIfNeeded()
    await helpers.locator('[data-testid=task-helper-total] input').fill('3')
    await helpers.getByText('Funktion hinzufügen').click()
    const picker = helpers.getByTestId('ga-skill-picker').locator('input').first()
    await picker.click()
    const item = page.locator('.v-overlay--active .v-list-item', { hasText: /E2E-Schweissen-/ }).first()
    await expect(item).toBeVisible() // Katalog enthält die im Helferprofil erfassten Fähigkeiten
    const wanted = (await item.innerText()).trim()
    await item.click()
    await expect(picker).toHaveValue(wanted)
    await page.screenshot({ path: 'test-results/ga-helferpool-need-picker.png' })
    // Nichts speichern: Entwurf verwerfen
    await page.keyboard.press('Escape')
  })
})
