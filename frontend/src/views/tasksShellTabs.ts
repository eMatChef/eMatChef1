/**
 * Sichtbare Tabs der Aufgaben-Shell (`TasksShellView`).
 * Im GA-Aufgabenbereich (`/ga/tasks`) gibt es nur «Aufgaben» und «Meine Aufgaben»; «Allgemein», «Inventur» und «Drucken»
 * sind Department-Funktionen unter `/dept/tasks` und erscheinen dort unverändert.
 */
export type TasksShellTabInput = {
  isGrossanlass: boolean
  /** Aktuelle Route liegt im GA-Aufgabenbereich (`TasksGaMaster` / `TasksGaMine`). */
  isGaTasksArea: boolean
  canManagePrintTasks: boolean
}

export type TasksShellTabs = {
  gaMaster: boolean
  gaMine: boolean
  general: boolean
  inventory: boolean
  print: boolean
}

export const GA_TASKS_ROUTE_NAMES = ['TasksGaMaster', 'TasksGaMine'] as const

export function isGaTasksRoute(name: unknown): boolean {
  return typeof name === 'string' && (GA_TASKS_ROUTE_NAMES as readonly string[]).includes(name)
}

export function tasksShellTabs({ isGrossanlass, isGaTasksArea, canManagePrintTasks }: TasksShellTabInput): TasksShellTabs {
  const department = !isGaTasksArea
  return {
    gaMaster: isGrossanlass,
    gaMine: isGrossanlass,
    general: department,
    inventory: department && (!isGrossanlass || canManagePrintTasks),
    print: department && (!isGrossanlass || canManagePrintTasks),
  }
}
