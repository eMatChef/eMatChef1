import { flattenTreeWithLevel, type GroupHierarchyNode } from '@/utils/grossanlassGroupHierarchy'

export const BOOK_PROJECT_UNASSIGNED = '__unassigned__'

export type BookProjectPickerGroup = {
  id: string
  name: string
  parent_id?: string | null
  node_type?: string
  sort_order?: number | null
}

export type BookProjectPickerWish = {
  groupId?: string | null
  ressort?: string
}

export type BookProjectPickerItem = {
  value: string
  title: string
  name: string
  depth: number
  nodeType: string
  wishCount: number
  belowCount: number
  props?: { disabled: true }
}

function toNode(group: BookProjectPickerGroup): GroupHierarchyNode {
  return {
    id: group.id,
    name: group.name,
    parent_id: group.parent_id ?? null,
    sort_order: group.sort_order ?? 0,
  }
}

function pathLabel(
  groupId: string,
  byId: Map<string, BookProjectPickerGroup>,
  separator = ' › ',
): string {
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

function childrenByParent(groups: BookProjectPickerGroup[]): Map<string, string[]> {
  const map = new Map<string, string[]>()
  const ids = new Set(groups.map((group) => group.id))
  for (const group of groups) {
    const parentId = group.parent_id
    if (!parentId || !ids.has(parentId)) continue
    const list = map.get(parentId) ?? []
    list.push(group.id)
    map.set(parentId, list)
  }
  return map
}

function belowCountOf(
  id: string,
  children: Map<string, string[]>,
  wishCount: Map<string, number>,
  memo: Map<string, number>,
): number {
  const cached = memo.get(id)
  if (cached != null) return cached
  let total = 0
  for (const childId of children.get(id) ?? []) {
    total += (wishCount.get(childId) ?? 0) + belowCountOf(childId, children, wishCount, memo)
  }
  memo.set(id, total)
  return total
}

/** Ressort → Unterressort → Bauprojekt, nur Knoten mit Wünschen oder Vorfahren davon. */
export function buildBookProjectPickerItems(
  groups: BookProjectPickerGroup[],
  wishes: BookProjectPickerWish[],
  unassignedLabel = '',
): BookProjectPickerItem[] {
  const byId = new Map<string, BookProjectPickerGroup>()
  for (const group of groups) byId.set(group.id, group)

  const wishCount = new Map<string, number>()
  let unassigned = 0
  for (const wish of wishes) {
    const id = wish.groupId || ''
    if (!id) {
      unassigned += 1
      continue
    }
    wishCount.set(id, (wishCount.get(id) ?? 0) + 1)
    if (!byId.has(id)) {
      byId.set(id, {
        id,
        name: wish.ressort || id,
        parent_id: null,
        node_type: '',
        sort_order: 0,
      })
    }
  }

  const needed = new Set(wishCount.keys())
  for (const id of [...needed]) {
    let current = byId.get(id)
    const seen = new Set<string>()
    while (current?.parent_id) {
      if (seen.has(current.id)) break
      seen.add(current.id)
      const parent = byId.get(current.parent_id)
      if (!parent) break
      needed.add(parent.id)
      current = parent
    }
  }

  const subset = [...needed]
    .map((id) => byId.get(id))
    .filter((group): group is BookProjectPickerGroup => Boolean(group))
  const children = childrenByParent(subset)
  const belowMemo = new Map<string, number>()
  const items = flattenTreeWithLevel(subset.map(toNode)).map((node) => {
    const group = byId.get(node.id) ?? node
    const count = wishCount.get(node.id) ?? 0
    const below = belowCountOf(node.id, children, wishCount, belowMemo)
    return {
      value: node.id,
      title: pathLabel(node.id, byId),
      name: group.name,
      depth: node._level,
      nodeType: ('node_type' in group ? group.node_type : '') || '',
      wishCount: count,
      belowCount: below,
      ...(count === 0 ? { props: { disabled: true as const } } : {}),
    }
  })

  if (unassigned > 0 && unassignedLabel) {
    items.push({
      value: BOOK_PROJECT_UNASSIGNED,
      title: unassignedLabel,
      name: unassignedLabel,
      depth: 0,
      nodeType: '',
      wishCount: unassigned,
      belowCount: 0,
    })
  }

  return items
}
