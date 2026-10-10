import { describe, expect, it } from 'vitest'
import { buildGrossanlassEinstellungenTabs } from './grossanlassEinstellungenTabs'

const ids = (tabs: { id: string }[]) => tabs.map((tab) => tab.id)

describe('GA-Einstellungen: Reiter', () => {
  it('shows the full GA administration to MW, Co-MW and OK-Leitung', () => {
    for (const role of ['mw', 'cmw', 'dc']) {
      const tabs = buildGrossanlassEinstellungenTabs({ role, setupPending: false, guestTabsVisible: true })
      expect(ids(tabs), role).toEqual(expect.arrayContaining(['general', 'units', 'users', 'locations', 'inquiry-email', 'participants', 'approval']))
      expect(tabs.some((tab) => tab.locked), role).toBe(false)
    }
  })

  it('keeps every tab visible during setup, but only master data, ressorts and users usable', () => {
    for (const role of ['mw', 'cmw', 'dc']) {
      const open = buildGrossanlassEinstellungenTabs({ role, setupPending: false, guestTabsVisible: true })
      const pending = buildGrossanlassEinstellungenTabs({ role, setupPending: true, guestTabsVisible: false })
      expect(ids(pending).filter((id) => !['participants', 'approval'].includes(id)), role).toEqual(ids(open).filter((id) => !['participants', 'approval'].includes(id)))
      expect(pending.filter((tab) => !tab.locked).map((tab) => tab.id), role).toEqual(['general', 'units', 'users'])
      expect(pending.filter((tab) => tab.locked).length, role).toBeGreaterThanOrEqual(3)
    }
  })

  it('shows the guest tabs (locked) during setup even without guest departments', () => {
    const pending = buildGrossanlassEinstellungenTabs({ role: 'mw', setupPending: true, guestTabsVisible: false })
    expect(pending.find((tab) => tab.id === 'participants')?.locked).toBe(true)
    expect(pending.find((tab) => tab.id === 'approval')?.locked).toBe(true)
  })

  it('hides guest tabs after release when no guest departments exist', () => {
    const tabs = buildGrossanlassEinstellungenTabs({ role: 'mw', setupPending: false, guestTabsVisible: false })
    expect(ids(tabs)).not.toContain('participants')
  })

  it('gives management tabs only to MW, Co-MW and OK-Leitung', () => {
    const tabs = buildGrossanlassEinstellungenTabs({ role: 'bl', setupPending: false, guestTabsVisible: true })
    expect(ids(tabs)).not.toContain('users')
    // allgemeine Einstellungen sind kein Teil der GA-Verwaltung
    for (const id of ['abteilung', 'zeit', 'drucken', 'user-karten', 'fixe-daten']) {
      expect(ids(tabs)).not.toContain(id)
    }
  })

  it('leaves mailbox-only roles with the e-mail tab', () => {
    const tabs = buildGrossanlassEinstellungenTabs({ role: 'komm', setupPending: false, guestTabsVisible: true })
    expect(ids(tabs)).toEqual(['inquiry-email'])
  })
})
