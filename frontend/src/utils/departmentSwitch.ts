import type { UserDepartmentResponse } from '@/api/auth'

export function isGrossanlassDepartment(dept: UserDepartmentResponse): boolean {
  return Boolean(dept.department?.is_grossanlass)
}

type GrossanlassDepartmentRef = { id: string; is_grossanlass?: boolean }

/** Erkennt Grossanlass-Departments auch ausserhalb der Auth-Mitgliedschaft (z. B. Admin-Modal). */
export function resolveIsGrossanlassDepartmentId(
  departmentId: string | null | undefined,
  options?: {
    forced?: boolean | null
    departments?: GrossanlassDepartmentRef[]
    fromAuth?: (id: string) => boolean
  },
): boolean {
  if (options?.forced === true) return true
  if (options?.forced === false) return false
  if (!departmentId) return false
  if (options?.fromAuth?.(departmentId)) return true
  const match = options?.departments?.find((dept) => dept.id === departmentId)
  return Boolean(match?.is_grossanlass)
}

export function departmentDisplayName(
  dept: UserDepartmentResponse,
  grossanlassLabel: string,
): string {
  const base = dept.department?.name || dept.department_id
  return isGrossanlassDepartment(dept) ? `${base} (${grossanlassLabel})` : base
}

/** Ziel nach Dept-Wechsel: Grossanlass- und Pfadi-Home ist /{deptId} (Dashboard). */
export function departmentHomePath(departmentId: string): string {
  return `/${departmentId}`
}
