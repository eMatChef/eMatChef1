import { isValidEntityId } from '@/utils/entityId'

/**
 * Ersetzt das Department-Segment in einer App-Route (/{deptId}/…).
 * Gibt null zurück, wenn IDs oder Pfad nicht vertrauenswürdig sind.
 */
export function pathAfterDepartmentSwitch(
  currentPath: string,
  oldDepartmentId: string | undefined,
  newDepartmentId: string,
): string | null {
  if (!isValidEntityId(newDepartmentId)) {
    return null
  }
  if (!oldDepartmentId || oldDepartmentId === newDepartmentId) {
    return currentPath.startsWith('/') ? currentPath : null
  }
  if (!isValidEntityId(oldDepartmentId)) {
    return null
  }
  const prefix = `/${oldDepartmentId}`
  if (currentPath === prefix) {
    return `/${newDepartmentId}`
  }
  if (!currentPath.startsWith(`${prefix}/`)) {
    return null
  }
  return `/${newDepartmentId}${currentPath.slice(prefix.length)}`
}

/** Voller Reload mit validiertem Pfad nach Department-Wechsel in den Einstellungen. */
export function assignPathAfterDepartmentSwitch(
  currentPath: string,
  oldDepartmentId: string | undefined,
  newDepartmentId: string,
): void {
  const next = pathAfterDepartmentSwitch(currentPath, oldDepartmentId, newDepartmentId)
  if (next) {
    window.location.assign(next)
    return
  }
  if (isValidEntityId(newDepartmentId)) {
    window.location.assign(`/${newDepartmentId}`)
  } else {
    window.location.reload()
  }
}
