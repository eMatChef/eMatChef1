import type { UserDepartmentResponse } from '@/api/auth'

export function isGrossanlassDepartment(dept: UserDepartmentResponse): boolean {
  return Boolean(dept.department?.is_grossanlass)
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
