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
