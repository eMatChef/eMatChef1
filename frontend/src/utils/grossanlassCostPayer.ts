export type GrossanlassPayerGroup = {
  id: string
  name: string
  parent_id?: string | null
  level?: number
}

export type GrossanlassPayerSelectItem = {
  title: string
  value: string | null
  name: string
  depth: number
}

export function grossanlassGroupPathTitle(
  group: { id: string; name: string; parent_id?: string | null },
  all: Array<{ id: string; name: string; parent_id?: string | null }>,
): string {
  const parent = all.find((row) => row.id === group.parent_id)
  return parent ? `${parent.name} / ${group.name}` : group.name
}

export function isGrossanlassLogisticsPayer(
  payerGroupId: string | null | undefined,
  logisticsGroupId: string | null | undefined,
): boolean {
  if (logisticsGroupId) return payerGroupId === logisticsGroupId
  return payerGroupId == null
}

export function grossanlassPayerTreeDepth(
  group: GrossanlassPayerGroup,
  all: GrossanlassPayerGroup[],
): number {
  if (typeof group.level === 'number' && group.level >= 0) return group.level
  let depth = 0
  let parentId = group.parent_id ?? null
  const seen = new Set<string>()
  while (parentId) {
    if (seen.has(parentId)) break
    seen.add(parentId)
    const parent = all.find((row) => row.id === parentId)
    if (!parent) break
    depth += 1
    parentId = parent.parent_id ?? null
  }
  return depth
}

export function grossanlassPayerSelectItems(
  groups: GrossanlassPayerGroup[],
  logisticsGroupId: string | null | undefined,
  labels: { central: string; potSuffix: string },
): GrossanlassPayerSelectItem[] {
  const mapped = groups.map((group) => {
    const isPot = Boolean(logisticsGroupId && group.id === logisticsGroupId)
    const name = isPot ? `${group.name} ${labels.potSuffix}`.trim() : group.name
    const treeDepth = grossanlassPayerTreeDepth(group, groups)
    return {
      title: name,
      name,
      value: group.id as string | null,
      depth: isPot ? 0 : treeDepth + 1,
    }
  })

  if (!logisticsGroupId) {
    return [
      { title: labels.central, name: labels.central, value: null, depth: 0 },
      ...mapped.map((item) => ({
        ...item,
        depth: item.depth > 0 ? item.depth : 1,
      })),
    ]
  }

  const pot = mapped.filter((item) => item.value === logisticsGroupId)
  const rest = mapped.filter((item) => item.value !== logisticsGroupId)
  return [...pot, ...rest]
}
