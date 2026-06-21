import type { GrossanlassGroup } from '@/api/grossanlassGroups'

export type GrossanlassGroupWithLevel = GrossanlassGroup & { _level: number }

function compareGroups(a: GrossanlassGroup, b: GrossanlassGroup): number {
  const orderDiff = (a.sort_order ?? 0) - (b.sort_order ?? 0)
  if (orderDiff !== 0) return orderDiff
  return a.name.localeCompare(b.name, 'de')
}

function sortGroups(groups: GrossanlassGroup[]): GrossanlassGroup[] {
  return [...groups].sort(compareGroups)
}

/** Ressorts hierarchisch sortiert (Root zuerst, Kinder eingerückt). */
export function flattenGrossanlassGroupsWithLevel(groups: GrossanlassGroup[]): GrossanlassGroupWithLevel[] {
  const all = groups
  const ids = new Set(all.map((g) => g.id))
  const rootGroups = sortGroups(all.filter((g) => !g.parent_id || !ids.has(g.parent_id)))

  function flatten(nodes: GrossanlassGroup[], level: number): GrossanlassGroupWithLevel[] {
    const result: GrossanlassGroupWithLevel[] = []
    for (const node of nodes) {
      result.push({ ...node, _level: level })
      const children = sortGroups(all.filter((g) => g.parent_id === node.id))
      if (children.length > 0) {
        result.push(...flatten(children, level + 1))
      }
    }
    return result
  }

  return flatten(rootGroups, 0)
}

export function grossanlassGroupIndentTitle(
  group: GrossanlassGroup | GrossanlassGroupWithLevel,
  level?: number,
): string {
  const lvl =
    level ??
    ('_level' in group && typeof group._level === 'number' ? group._level : group.level ?? 0)
  return `${'↳ '.repeat(Math.max(0, lvl))}${group.name}`
}

export function isBauprojektGroup(group: GrossanlassGroup): boolean {
  return group.node_type === 'bauprojekt' || group.kind === 'teilbereich'
}

export function isRessortNodeGroup(group: GrossanlassGroup): boolean {
  return !isBauprojektGroup(group)
}

/** Anzeigepfad z. B. «Infrastruktur › BL Wasser und Sanitär» */
export function buildGrossanlassGroupPathLabel(
  groupId: string | null | undefined,
  groups: GrossanlassGroup[],
  separator = ' › ',
): string {
  if (!groupId) return ''
  const byId = new Map(groups.map((g) => [g.id, g]))
  const chain: string[] = []
  let current = byId.get(groupId)
  const seen = new Set<string>()
  while (current) {
    if (seen.has(current.id)) break
    seen.add(current.id)
    chain.unshift(current.name)
    const parentId = current.parent_id
    current = parentId ? byId.get(parentId) : undefined
  }
  return chain.join(separator)
}

/** Ressort-Pfad zum Bauprojekt (ohne Bauprojektname). */
export function ressortPathForBauprojekt(group: GrossanlassGroup, groups: GrossanlassGroup[]): string {
  if (!group.parent_id) return buildGrossanlassGroupPathLabel(group.id, groups)
  return buildGrossanlassGroupPathLabel(group.parent_id, groups)
}

/** Baumreihenfolge beibehalten, nur erlaubte Knoten. */
export function flattenGrossanlassGroupsFiltered(
  groups: GrossanlassGroup[],
  predicate: (group: GrossanlassGroup) => boolean,
): GrossanlassGroupWithLevel[] {
  return flattenGrossanlassGroupsWithLevel(groups).filter(predicate)
}

/** Dropdown-Titel inkl. Bauprojekt-Kennzeichnung. */
export function grossanlassGroupSelectTitle(
  group: GrossanlassGroup | GrossanlassGroupWithLevel,
  bauprojektLabel: string,
): string {
  const base = grossanlassGroupIndentTitle(group)
  if (isBauprojektGroup(group)) {
    return `${base} · ${bauprojektLabel}`
  }
  return base
}
