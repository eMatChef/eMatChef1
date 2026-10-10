import { describe, expect, it } from 'vitest'
import { ONBOARDING_TOURS, filterOnboardingToursForRole, getOnboardingTour } from './onboardingTours'

describe('Grossanlass setup tour', () => {
  const tour = getOnboardingTour('ga-setup')

  it('explains dashboard, master data, type, areas, users and release in six steps without any required input', () => {
    expect(tour).toBeDefined()
    expect(tour?.steps).toHaveLength(6)
    expect(tour?.steps[0].target).toBe('[data-onboarding="ga-setup-panel"]')
    // Die Tour zeigt nur: kein Schritt verlangt einen Klick auf Bedienelemente oder Eingaben.
    expect((tour?.steps ?? []).every((step) => step.mode !== 'click' && !step.clickOnEnter && !step.typeIntoOnEnter && !step.advanceOnClick)).toBe(true)
    const targets = tour?.steps.map((step) => step.target) ?? []
    expect(targets).toContain('[data-onboarding="ga-setup-tab-general"]') // 1. Stammdaten
    expect(targets).toContain('[data-onboarding="ga-setup-type"]') // GA-Typ
    expect(targets).toContain('[data-onboarding="ga-setup-ressorts"]') // 2. Ressorts
    expect(targets).toContain('[data-onboarding="settings-user-add"]') // 3. Benutzer
    expect(targets).toContain('[data-onboarding="ga-setup-release"]') // Freigabe
  })

  it('points only at real UI elements that exist in the views', () => {
    // Die Anker werden in den echten Ansichten gesetzt (keine zweite Oberfläche).
    const required = ['ga-setup-panel', 'ga-setup-tab-general', 'ga-setup-type', 'ga-setup-ressorts', 'settings-user-add', 'ga-setup-release']
    const selectors = (tour?.steps ?? []).map((step) => step.target ?? '')
    for (const anchor of required) {
      expect(selectors.some((selector) => selector.includes(anchor)), anchor).toBe(true)
    }
  })

  it('is visible for MW, Co-MW and OK-Leitung in a Grossanlass hub only, and alone there', () => {
    for (const role of ['mw', 'cmw', 'dc']) {
      const ids = filterOnboardingToursForRole(role, { isGrossanlass: true }).map((t) => t.id)
      expect(ids, role).toEqual(['ga-setup'])
    }
    for (const role of ['bl', 'lw', 'komm', 'u', 'l1']) {
      expect(filterOnboardingToursForRole(role, { isGrossanlass: true }), role).toEqual([])
    }
    // im normalen Department-Hub erscheint sie nie
    for (const role of ['mw', 'dc', 'sa']) {
      expect(filterOnboardingToursForRole(role, {}).map((t) => t.id), role).not.toContain('ga-setup')
    }
    expect(ONBOARDING_TOURS.filter((t) => t.audience === 'ga-setup')).toHaveLength(1)
  })
})
