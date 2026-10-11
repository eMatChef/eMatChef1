// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import router from './index'

describe('grossanlass routes under /:departmentId/ga', () => {
  it.each([
    ['/dep-1/ga/planning/requests', 'GrossanlassPlanungWuensche'],
    ['/dep-1/ga/planning/jobs', 'GrossanlassPlanungAuftraege'],
    ['/dep-1/ga/planning/transports', 'GrossanlassPlanungTransporte'],
    ['/dep-1/ga/material/stock', undefined],
    ['/dep-1/ga/procurement/inquiries', 'GrossanlassBeschaffungAnfragen'],
  ])('%s resolves inside the grossanlass guard scope', (path, name) => {
    const resolved = router.resolve(path)
    expect(resolved.params.departmentId).toBe('dep-1')
    if (name) expect(resolved.name).toBe(name)
    expect(resolved.matched.some((r) => r.meta.requiresGrossanlassDepartment)).toBe(true)
  })

  it.each([
    'ga/my-unit', 'ga/my-assignments', 'ga/planning/requests', 'ga/planning/jobs',
    'ga/planning/transports', 'ga/planning/occupancy', 'ga/planning/conflicts', 'ga/helper-pool', 'dept/tasks',
    'ga/procurement/demand', 'ga/procurement/inquiries', 'ga/procurement/offers', 'ga/procurement/commitments',
    'ga/procurement/orders', 'ga/procurement/budget', 'ga/material/stock', 'ga/material/goods-receipt',
    'ga/material/issue', 'ga/material/pack', 'ga/material/teardown', 'ga/material/resale',
    'ga/logistics/dispatch', 'ga/vehicles/requests', 'ga/vehicles/fleet', 'ga/workshop', 'ga/costs',
    'ga/displays', 'ga/activity-settings/general', 'ga/settings/my-department', 'ga/help/ga',
  ])('menu target /dep-1/%s is a real route', (suffix) => {
    const resolved = router.resolve(`/dep-1/${suffix}`)
    expect(resolved.matched.length).toBeGreaterThan(0)
    expect(resolved.params.departmentId).toBe('dep-1')
    expect(resolved.name ?? resolved.matched[resolved.matched.length - 1].name).toBeDefined()
  })

  it('has no route for the removed procurement tab «Erhalten» (goods receipt lives under Material)', () => {
    expect(router.resolve('/dep-1/ga/procurement/received').name).toBeUndefined()
  })

  it('keeps no legacy GA routes below /:departmentId', () => {
    for (const legacy of ['/dep-1/planung/transporte', '/dep-1/material/bestand', '/dep-1/mein-ressort']) {
      expect(router.resolve(legacy).matched.some((r) => r.meta.requiresGrossanlassDepartment)).toBe(false)
      expect(router.resolve(legacy).name).toBeUndefined()
    }
  })

  it('uses English path segments for every GA route', () => {
    const german = /einstellungen|planung|beschaffung|fahrzeuge|ressort|einsaetze|kosten|helferpool|werkstatt|logistik|abteilungsmat|helferauftrag|vorschau|stammdaten|standorte|kategorien|teilnehmer|freigabe|benutzer|wuensche|auftraege|belegung|konflikte|bedarf|anfragen|offerten|zusagen|bestellungen|fuhrpark|artikel|bestand|wareneingang|ausgabe|weiterverkauf|rueckbau|disposition|-hilfe/
    const offenders = router.getRoutes().map((r) => r.path).filter((path) => /\/:departmentId\/ga(\/|$)/.test(path) && german.test(path))
    expect(offenders).toEqual([])
  })

  it.each([
    ['/dep-1/ga/activity-settings/general', 'GrossanlassPlanungStammdaten'],
    ['/dep-1/ga/activity-settings/units', 'GrossanlassRessorts'],
    ['/dep-1/ga/activity-settings/users', 'GrossanlassEinstellungenBenutzer'],
    ['/dep-1/ga/activity-settings/locations', 'GrossanlassEinstellungenStandorte'],
    ['/dep-1/ga/activity-settings/participants', 'GrossanlassPlanungStruktur'],
    ['/dep-1/ga/activity-settings/approval', 'GrossanlassPlanungFreigabe'],
  ])('GA administration %s resolves to %s', (path, name) => {
    expect(router.resolve(path).name).toBe(name)
  })

  it('shares one settings surface between /dept/settings and /ga/settings', () => {
    expect(router.resolve('/dep-1/dept/settings/my-department').name).toBe('SettingsMyDepartment')
    expect(router.resolve('/dep-1/ga/settings/my-department').name).toBe('GaSettingsMyDepartment')
    expect(router.resolve('/dep-1/ga/settings/user-karten').name).toBe('GaSettingsGrossanlassUserKarten')
    expect(router.resolve('/dep-1/ga/settings/print').matched.some((r) => r.meta.requiresGrossanlassDepartment)).toBe(true)
    expect(router.resolve('/dep-1/dept/settings/print').matched.some((r) => r.meta.requiresGrossanlassDepartment)).toBe(false)
  })

  it('does not capture global URLs', () => {
    expect(router.resolve('/profile').params.departmentId).toBeUndefined()
    expect(router.resolve('/login').name).toBe('Login')
    expect(router.resolve('/register').name).toBe('Register')
  })

  it.each([
    ['/dep-1/dept/activities', 'Activities'],
    ['/dep-1/dept/materials', 'Materials'],
    ['/dep-1/dept/dashboard', 'Dashboard'],
  ])('department page %s resolves to %s', (path, name) => {
    expect(router.resolve(path).name).toBe(name)
  })

  it('has no old department routes', () => {
    for (const old of ['/dep-1/activities', '/dep-1/materials', '/dep-1/settings', '/dep-1/dashboard']) {
      expect(router.resolve(old).name).toBeUndefined()
    }
  })

  it('keeps /:departmentId as the real entry', () => {
    expect(router.resolve('/dep-1').name).toBe('DepartmentEntry')
  })
})
