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

export function grossanlassPayerSelectItems(
  groups: Array<{ id: string; name: string; parent_id?: string | null }>,
  logisticsGroupId: string | null | undefined,
  labels: { central: string; potSuffix: string },
): Array<{ title: string; value: string | null }> {
  const items = groups.map((group) => {
    let title = grossanlassGroupPathTitle(group, groups)
    if (logisticsGroupId && group.id === logisticsGroupId) {
      title = `${title} ${labels.potSuffix}`
    }
    return { title, value: group.id as string | null }
  })
  if (!logisticsGroupId) {
    return [{ title: labels.central, value: null }, ...items]
  }
  return items
}
