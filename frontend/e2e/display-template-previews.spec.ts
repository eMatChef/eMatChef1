import { expect, test } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'

/**
 * Erzeugt die Vorschaubilder der offiziellen Infoscreen-Vorlagen aus dem echten Renderer (1920 × 1080, feste Uhrzeit,
 * Demo-Daten) und prüft, dass die Anzeige passiv ist. Braucht nur das Frontend (Vite), kein Backend und keine Anmeldung.
 *
 *   E2E_TEMPLATE_PREVIEWS=1 E2E_BASE_URL=http://localhost:5173 npx playwright test e2e/display-template-previews.spec.ts
 *
 * Die Vorlagen kommen aus src/test-fixtures/displayLayoutTemplates.json (php bin/console app:display:export-templates).
 */
const TEMPLATES = ['ga_materiallager', 'ga_baubuero', 'ga_projekt', 'ga_disposition']
const outDir = resolve(process.cwd(), 'public/display-templates')

test.skip(process.env.E2E_TEMPLATE_PREVIEWS !== '1', 'E2E_TEMPLATE_PREVIEWS=1 erforderlich (schreibt Vorschaubilder)')
test.use({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1 })

for (const id of TEMPLATES) {
  test(`Vorschaubild ${id}`, async ({ page }) => {
    mkdirSync(outDir, { recursive: true })
    // Feste Zeit: reproduzierbare Uhr, ETA- und Fristanzeigen der Demo-Daten.
    await page.clock.install({ time: new Date('2026-11-02T09:30:00') })
    await page.goto(`/dev/display-template/${id}`)
    const root = page.locator('.dl')
    await expect(root).toBeVisible()
    // Alle GA-Widgets sind eingebunden und haben gerendert.
    await expect(root.locator('.ga-w').first()).toBeVisible()
    await page.waitForTimeout(3500)

    // Passiv: keine Bedienelemente.
    await expect(page.locator('button, input, textarea, select, a[href], form, [role="button"], [role="tab"], [tabindex]')).toHaveCount(0)
    await expect(page.locator('body')).not.toContainText(/Vollbild|Zur Verwaltung/)

    await page.screenshot({ path: resolve(outDir, `${id}.jpg`), type: 'jpeg', quality: 82 })
  })
}
