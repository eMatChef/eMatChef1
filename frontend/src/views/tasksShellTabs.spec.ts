import { describe, expect, it } from 'vitest'
import { isGaTasksRoute, tasksShellTabs } from './tasksShellTabs'

describe('tasksShellTabs', () => {
  it('zeigt im GA-Aufgabenbereich nur «Aufgaben» und «Meine Aufgaben» (auch für Leitungsrollen)', () => {
    for (const canManagePrintTasks of [true, false]) {
      expect(tasksShellTabs({ isGrossanlass: true, isGaTasksArea: true, canManagePrintTasks })).toEqual({
        gaMaster: true,
        gaMine: true,
        general: false,
        inventory: false,
        print: false,
      })
    }
  })

  it('lässt die normale Department-Aufgabenverwaltung unverändert', () => {
    expect(tasksShellTabs({ isGrossanlass: false, isGaTasksArea: false, canManagePrintTasks: true })).toEqual({
      gaMaster: false,
      gaMine: false,
      general: true,
      inventory: true,
      print: true,
    })
    // einfache Mitglieder: nur «Allgemein» (die Tab-Leiste selbst entscheidet `showTasksTabs`)
    expect(tasksShellTabs({ isGrossanlass: false, isGaTasksArea: false, canManagePrintTasks: false })).toMatchObject({ general: true, inventory: true, print: true })
  })

  it('erkennt die GA-Aufgaben-Routen am Namen, nicht die Department-Routen', () => {
    expect(isGaTasksRoute('TasksGaMaster')).toBe(true)
    expect(isGaTasksRoute('TasksGaMine')).toBe(true)
    for (const name of ['TasksGeneral', 'TasksInventory', 'TasksPrint', undefined, null]) expect(isGaTasksRoute(name)).toBe(false)
  })
})
