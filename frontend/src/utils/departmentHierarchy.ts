import type { Department } from '@/api/departments'

export type DepartmentWithLevel = Department & { _level: number }

type DepartmentNode = Pick<Department, 'id' | 'name' | 'parent_id' | 'organisation_id'>

/** Departments hierarchisch: Root zuerst, Kinder eingerückt (alphabetisch pro Ebene). */
export function flattenDepartmentsWithLevel<T extends DepartmentNode>(
  departments: T[],
  locale = 'de',
): (T & { _level: number })[] {
  const all = departments
  const ids = new Set(all.map((d) => d.id))
  const roots = all.filter((d) => !d.parent_id || !ids.has(d.parent_id))

  function flatten(nodes: T[], level: number): (T & { _level: number })[] {
    const result: (T & { _level: number })[] = []
    const sorted = [...nodes].sort((a, b) =>
      a.name.localeCompare(b.name, locale, { sensitivity: 'base' }),
    )
    for (const node of sorted) {
      result.push({ ...node, _level: level })
      const children = all.filter((d) => d.parent_id === node.id)
      if (children.length > 0) {
        result.push(...flatten(children as T[], level + 1))
      }
    }
    return result
  }

  const sortedRoots = [...roots].sort((a, b) =>
    a.name.localeCompare(b.name, locale, { sensitivity: 'base' }),
  )
  return flatten(sortedRoots, 0)
}
