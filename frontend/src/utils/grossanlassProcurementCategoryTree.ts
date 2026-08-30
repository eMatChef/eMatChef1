import type { GrossanlassProcurementCategory } from '@/api/grossanlassProcurement'

export function sortProcurementCategories(
  a: GrossanlassProcurementCategory,
  b: GrossanlassProcurementCategory,
): number {
  const aLock = a.system_key ? 0 : 1
  const bLock = b.system_key ? 0 : 1
  if (aLock !== bLock) return aLock - bLock
  if (a.sort_order !== b.sort_order) return a.sort_order - b.sort_order
  return a.name.localeCompare(b.name, undefined, { sensitivity: 'base' })
}

export function isProcurementArticle(
  category: GrossanlassProcurementCategory | undefined,
): boolean {
  return category?.kind === 'item'
}

export function childrenOfProcurementCategory(
  categories: GrossanlassProcurementCategory[],
  parentId: string | null,
): GrossanlassProcurementCategory[] {
  return categories
    .filter((c) => (c.parent_id ?? null) === parentId)
    .slice()
    .sort(sortProcurementCategories)
}

export function descendantIdsOfProcurementCategory(
  categories: GrossanlassProcurementCategory[],
  rootId: string,
): Set<string> {
  const ids = new Set<string>([rootId])
  const walk = (parentId: string) => {
    for (const child of childrenOfProcurementCategory(categories, parentId)) {
      ids.add(child.id)
      walk(child.id)
    }
  }
  walk(rootId)
  return ids
}

export function depthOfProcurementCategory(
  categories: GrossanlassProcurementCategory[],
  categoryId: string,
): number {
  let depth = 0
  let current = categories.find((c) => c.id === categoryId)
  const seen = new Set<string>()
  while (current?.parent_id && !seen.has(current.id)) {
    seen.add(current.id)
    depth += 1
    current = categories.find((c) => c.id === current!.parent_id)
  }
  return depth
}

export function pathLabelOfProcurementCategory(
  categories: GrossanlassProcurementCategory[],
  categoryId: string,
): string {
  const parts: string[] = []
  let current = categories.find((c) => c.id === categoryId)
  const seen = new Set<string>()
  while (current && !seen.has(current.id)) {
    seen.add(current.id)
    parts.unshift(current.name)
    current = current.parent_id
      ? categories.find((c) => c.id === current!.parent_id)
      : undefined
  }
  return parts.join(' / ')
}

export type ProcurementCategorySelectItem = {
  title: string
  value: string
  name: string
  depth: number
}

export function procurementCategoryTreeItems(
  categories: GrossanlassProcurementCategory[],
  excludeIds?: Set<string>,
): ProcurementCategorySelectItem[] {
  const items: ProcurementCategorySelectItem[] = []
  const walk = (parentId: string | null, depth: number) => {
    for (const cat of childrenOfProcurementCategory(categories, parentId)) {
      if (excludeIds?.has(cat.id) || isProcurementArticle(cat)) continue
      items.push({
        title: pathLabelOfProcurementCategory(categories, cat.id),
        value: cat.id,
        name: cat.name,
        depth,
      })
      walk(cat.id, depth + 1)
    }
  }
  walk(null, 0)
  return items
}

export function isLeafProcurementCategory(
  categories: GrossanlassProcurementCategory[],
  categoryId: string,
): boolean {
  const row = categories.find((c) => c.id === categoryId)
  if (isProcurementArticle(row)) return true
  return childrenOfProcurementCategory(categories, categoryId).length === 0
}

export function leafIdsUnderProcurementCategory(
  categories: GrossanlassProcurementCategory[],
  rootId: string,
): string[] {
  const leaves: string[] = []
  const walk = (id: string) => {
    const row = categories.find((c) => c.id === id)
    const children = childrenOfProcurementCategory(categories, id)
    if (isProcurementArticle(row) || children.length === 0) {
      leaves.push(id)
      return
    }
    for (const child of children) {
      walk(child.id)
    }
  }
  walk(rootId)
  return leaves
}

function parentOfProcurementCategory(
  categories: GrossanlassProcurementCategory[],
  categoryId: string,
): GrossanlassProcurementCategory | undefined {
  const row = categories.find((c) => c.id === categoryId)
  if (!row?.parent_id) return undefined
  return categories.find((c) => c.id === row.parent_id)
}

export function isProcurementCategoryFullySelected(
  categories: GrossanlassProcurementCategory[],
  selectedIds: string[],
  categoryId: string,
): boolean {
  const selected = new Set(selectedIds)
  const leaves = leafIdsUnderProcurementCategory(categories, categoryId)
  if (leaves.length === 0) return selected.has(categoryId)
  return leaves.every((id) => selected.has(id))
}

export function isProcurementCategoryIndeterminate(
  categories: GrossanlassProcurementCategory[],
  selectedIds: string[],
  categoryId: string,
): boolean {
  const selected = new Set(selectedIds)
  const leaves = leafIdsUnderProcurementCategory(categories, categoryId)
  if (leaves.length <= 1) return false
  const n = leaves.filter((id) => selected.has(id)).length
  return n > 0 && n < leaves.length
}

export function toggleProcurementCategorySelection(
  categories: GrossanlassProcurementCategory[],
  selectedIds: string[],
  categoryId: string,
  checked: boolean,
): string[] {
  const next = new Set(selectedIds)
  const treeIds = descendantIdsOfProcurementCategory(categories, categoryId)
  if (checked) {
    for (const id of treeIds) next.add(id)
    let parent = parentOfProcurementCategory(categories, categoryId)
    while (parent) {
      if (isProcurementCategoryFullySelected(categories, [...next], parent.id)) {
        next.add(parent.id)
      }
      parent = parentOfProcurementCategory(categories, parent.id)
    }
  } else {
    for (const id of treeIds) next.delete(id)
    let parent = parentOfProcurementCategory(categories, categoryId)
    while (parent) {
      next.delete(parent.id)
      parent = parentOfProcurementCategory(categories, parent.id)
    }
  }
  return [...next]
}

export function leafIdsOfProcurementCategorySelection(
  categories: GrossanlassProcurementCategory[],
  selectedIds: string[],
): string[] {
  const selected = new Set(selectedIds.filter(Boolean))
  const wanted = new Set<string>()
  for (const id of selected) {
    const leaves = leafIdsUnderProcurementCategory(categories, id)
    if (leaves.length === 0) continue
    const picked = leaves.filter((leaf) => selected.has(leaf))
    if (picked.length > 0) {
      for (const leaf of picked) wanted.add(leaf)
      continue
    }
    for (const leaf of leaves) wanted.add(leaf)
  }
  const ordered: string[] = []
  const walk = (parentId: string | null) => {
    for (const cat of childrenOfProcurementCategory(categories, parentId)) {
      if (wanted.has(cat.id) && isLeafProcurementCategory(categories, cat.id)) {
        ordered.push(cat.id)
      }
      walk(cat.id)
    }
  }
  walk(null)
  for (const id of wanted) {
    if (!ordered.includes(id)) ordered.push(id)
  }
  return ordered
}

export function selectionMatchesProcurementCategoryFilter(
  categories: GrossanlassProcurementCategory[],
  selectedIds: string[],
  filterId: string,
): boolean {
  const subtree = descendantIdsOfProcurementCategory(categories, filterId)
  return selectedIds.some((id) => subtree.has(id))
}

export function isPackageProcurementCategory(
  categories: GrossanlassProcurementCategory[],
  categoryId: string,
): boolean {
  const row = categories.find((c) => c.id === categoryId)
  if (!row) return false
  return !isProcurementArticle(row)
}

export type ProcurementCategoryTreeRow = {
  category: GrossanlassProcurementCategory
  depth: number
  hasChildren: boolean
  childCount: number
}

export function visibleProcurementCategoryTreeRows(
  categories: GrossanlassProcurementCategory[],
  collapsedIds: Iterable<string>,
): ProcurementCategoryTreeRow[] {
  const collapsed = collapsedIds instanceof Set ? collapsedIds : new Set(collapsedIds)
  const rows: ProcurementCategoryTreeRow[] = []
  const walk = (parentId: string | null, depth: number) => {
    for (const category of childrenOfProcurementCategory(categories, parentId)) {
      const childCount = childrenOfProcurementCategory(categories, category.id).length
      rows.push({
        category,
        depth,
        hasChildren: childCount > 0,
        childCount,
      })
      if (childCount > 0 && !collapsed.has(category.id)) {
        walk(category.id, depth + 1)
      }
    }
  }
  walk(null, 0)
  return rows
}

/** Unterkategorien mit Artikeln zugeklappt — sichtbar bleibt der grobe Kategoriebaum. */
export function defaultCollapsedArticleParentIds(
  categories: GrossanlassProcurementCategory[],
): string[] {
  return categories
    .filter((category) => !isProcurementArticle(category))
    .filter((category) =>
      childrenOfProcurementCategory(categories, category.id).some((child) => isProcurementArticle(child)),
    )
    .map((category) => category.id)
}

export function normalizeProcurementCategorySelection(
  categories: GrossanlassProcurementCategory[],
  selectedIds: string[],
): string[] {
  let next = [...selectedIds]
  for (const id of selectedIds) {
    const leaves = leafIdsUnderProcurementCategory(categories, id)
    const picked = leaves.filter((leaf) => next.includes(leaf))
    if (leaves.length > 0 && picked.length === 0) {
      next = toggleProcurementCategorySelection(categories, next, id, true)
    }
  }
  return next
}
