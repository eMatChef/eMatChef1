import type { Group } from '@/api/groups'

export type GroupWithLevel = Group & { _level: number }

/** Gruppen hierarchisch sortiert (Root zuerst, Kinder eingerückt) */
export function flattenGroupsWithLevel(groups: Group[]): GroupWithLevel[] {
  const all = groups
  const rootGroups = all.filter((g) => !g.parent_id)

  function flatten(nodes: Group[], level: number): GroupWithLevel[] {
    const result: GroupWithLevel[] = []
    for (const node of nodes) {
      result.push({ ...node, _level: level })
      const children = all.filter((g) => g.parent_id === node.id)
      if (children.length > 0) {
        result.push(...flatten(children, level + 1))
      }
    }
    return result
  }

  return flatten(rootGroups, 0)
}

export type GroupPathLine = { label: string; level: number }

/**
 * Pfad für Listen-Anzeige: Abteilung (Ebene 0), dann Gruppen/Untergruppen eingerückt.
 * Entspricht der Struktur im Gruppen-Dropdown (Abteilung → ↳ Gruppe → ↳ Untergruppe).
 */
export function buildActivityGroupPathLines(
  groupId: string | null | undefined,
  departmentName: string,
  groups: Group[],
  groupNameFallback?: string | null,
): GroupPathLine[] {
  const dept = departmentName.trim() || '–'
  const lines: GroupPathLine[] = [{ label: dept, level: 0 }]
  if (!groupId) return lines

  const flat = flattenGroupsWithLevel(groups)
  const selected = flat.find((g) => g.id === groupId)
  if (!selected) {
    const name = groupNameFallback?.trim()
    if (name) lines.push({ label: name, level: 1 })
    return lines
  }

  const chain: GroupWithLevel[] = []
  let current: GroupWithLevel | undefined = selected
  while (current) {
    chain.unshift(current)
    const parentId: string | null = current.parent_id
    current = parentId ? flat.find((g) => g.id === parentId) : undefined
  }
  for (const g of chain) {
    lines.push({ label: g.name.trim(), level: g._level + 1 })
  }
  return lines
}

/** Anzeigename in Gruppen-Dropdown: ohne group_id = Abteilung (oberste Ebene). */
export function resolveActivityGroupPickerLabel(
  groupId: string | null | undefined,
  departmentName: string,
  groups: Group[],
): string {
  if (!groupId) return departmentName.trim() || '–'
  const g = flattenGroupsWithLevel(groups).find((x) => x.id === groupId)
  return g?.name?.trim() || groupId
}
