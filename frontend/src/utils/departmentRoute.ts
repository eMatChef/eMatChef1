import type { Router } from 'vue-router'
import { isValidEntityId } from '@/utils/entityId'
import { departmentHomePath } from '@/utils/departmentSwitch'

/** Query-Parameter, die auf jeder Zielroute desselben Department-Typs sinnvoll bleiben. */
const CARRIED_QUERY_KEYS = ['tab']

type RouteProbe = { matched: unknown[]; name?: unknown; params: Record<string, unknown> }

export type DepartmentSwitchContext = {
  oldIsGrossanlass: boolean
  newIsGrossanlass: boolean
  /** `router.resolve` — liefert die Treffer der Zielroute. */
  resolve: (path: string) => RouteProbe
}

function withCarriedQuery(path: string, query: Record<string, unknown>): string {
  const params = new URLSearchParams()
  for (const key of CARRIED_QUERY_KEYS) {
    const value = query[key]
    if (typeof value === 'string' && value) params.set(key, value)
  }
  const qs = params.toString()
  return qs ? `${path}?${qs}` : path
}

/**
 * Zielpfad nach einem Department-Wechsel.
 * - Anderer Department-Typ (GA ↔ normal): Dashboard des neuen Departments.
 * - Gleicher Typ: Unterpfad nur, wenn die Zielroute existiert und keine Objekt-IDs des alten Departments enthält.
 * - Sonst: Dashboard. Zugriffsrechte prüfen die Router-Guards (siehe assignPathAfterDepartmentSwitch).
 * Gibt null zurück, wenn IDs oder Pfad nicht vertrauenswürdig sind.
 */
export function pathAfterDepartmentSwitch(
  currentPath: string,
  currentQuery: Record<string, unknown>,
  oldDepartmentId: string | undefined,
  newDepartmentId: string,
  ctx: DepartmentSwitchContext,
): string | null {
  if (!isValidEntityId(newDepartmentId)) {
    return null
  }
  const dashboard = departmentHomePath(newDepartmentId, ctx.newIsGrossanlass)
  if (!oldDepartmentId) {
    return dashboard
  }
  if (oldDepartmentId === newDepartmentId) {
    return currentPath.startsWith('/') ? currentPath : null
  }
  if (!isValidEntityId(oldDepartmentId)) {
    return null
  }
  const prefix = `/${oldDepartmentId}`
  if (!currentPath.startsWith(`${prefix}/`)) {
    return null
  }
  if (ctx.oldIsGrossanlass !== ctx.newIsGrossanlass) {
    return dashboard
  }
  const target = `/${newDepartmentId}${currentPath.slice(prefix.length)}`
  const probe = ctx.resolve(target)
  const hasObjectParams = Object.keys(probe.params).some((key) => key !== 'departmentId')
  if (probe.matched.length === 0 || probe.name === 'DepartmentEntry' || hasObjectParams) {
    return dashboard
  }
  return withCarriedQuery(target, currentQuery)
}

/**
 * Voller Reload nach Department-Wechsel. Das Ziel läuft zuerst durch die Router-Guards (aktives Department
 * ist bereits gesetzt); landet die Navigation woanders als geplant, wird das Dashboard geöffnet.
 */
export async function assignPathAfterDepartmentSwitch(
  router: Router,
  currentPath: string,
  currentQuery: Record<string, unknown>,
  oldDepartmentId: string | undefined,
  newDepartmentId: string,
  ctx: Omit<DepartmentSwitchContext, 'resolve'>,
): Promise<void> {
  const next = pathAfterDepartmentSwitch(currentPath, currentQuery, oldDepartmentId, newDepartmentId, {
    ...ctx,
    resolve: (path) => router.resolve(path),
  })
  if (!next) {
    if (isValidEntityId(newDepartmentId)) {
      window.location.assign(departmentHomePath(newDepartmentId, ctx.newIsGrossanlass))
    } else {
      window.location.reload()
    }
    return
  }
  const dashboard = departmentHomePath(newDepartmentId, ctx.newIsGrossanlass)
  await router.push(next)
  const landed = router.currentRoute.value
  const wantedPath = next.split('?')[0]
  if (landed.path !== wantedPath && landed.path !== dashboard) {
    await router.replace(dashboard)
  }
  window.location.assign(router.currentRoute.value.fullPath)
}
