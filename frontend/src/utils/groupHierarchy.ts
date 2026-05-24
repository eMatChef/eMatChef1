import type { Group } from '@/api/groups'

export type GroupWithLevel = Group & { _level: number }

/** Gruppen hierarchisch sortiert (Root zuerst, Kinder eingerückt) */
export function flattenGroupsWithLevel(groups: Group[]): GroupWithLevel[] {
  const all = groups
  const ids = new Set(all.map((g) => g.id))
  /** Auch Untergruppen ohne Parent in der Liste als Root (z. B. Member-Picker). */
  const rootGroups = all.filter((g) => !g.parent_id || !ids.has(g.parent_id))

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

/**
 * Gruppen für Member-Picker: eigene Gruppe(n) plus alle Untergruppen.
 */
export function expandGroupsForMemberPicker(allGroups: Group[], userId: string): Group[] {
  if (!userId || allGroups.length === 0) return []

  const memberRootIds = allGroups
    .filter((g) => g.members?.some((m) => m.user_id === userId))
    .map((g) => g.id)

  if (memberRootIds.length === 0) return []

  const idSet = new Set<string>()
  const addWithDescendants = (groupId: string) => {
    if (idSet.has(groupId)) return
    idSet.add(groupId)
    for (const g of allGroups) {
      if (g.parent_id === groupId) addWithDescendants(g.id)
    }
  }
  for (const id of memberRootIds) addWithDescendants(id)

  return allGroups.filter((g) => idSet.has(g.id))
}

/**
 * Heimatgruppe: direkte Mitgliedschaft (Primärgruppe bevorzugt), keine Untergruppe ohne Membership.
 */
export function pickUserHomeGroupId(groups: Group[], userId: string | null): string | null {
  if (!userId || !groups.length) return null

  type Row = { g: Group; isPrimary: boolean; sortOrder: number }
  const rows: Row[] = []
  for (const g of groups) {
    const mem = g.members?.find((m) => m.user_id === userId)
    if (mem) {
      rows.push({
        g,
        isPrimary: !!mem.is_primary,
        sortOrder: g.sort_order ?? 0,
      })
    }
  }
  if (rows.length === 0) return null

  rows.sort((a, b) => {
    if (a.isPrimary !== b.isPrimary) return a.isPrimary ? -1 : 1
    return a.sortOrder - b.sortOrder || a.g.name.localeCompare(b.g.name, 'de')
  })
  return rows[0].g.id
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
