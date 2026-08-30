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
      if (excludeIds?.has(cat.id)) continue
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
