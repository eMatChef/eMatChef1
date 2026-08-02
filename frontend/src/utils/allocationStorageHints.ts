export type AllocationRowLike = {
  id: number | string
  mode: 'slot' | 'kiste'
  container_batch_id: string
  qty: number
}

export function kisteRowsWithContainer(rows: AllocationRowLike[]): AllocationRowLike[] {
  return rows.filter(
    (r) => r.mode === 'kiste' && r.qty > 0 && String(r.container_batch_id ?? '').trim() !== '',
  )
}

/** Kiste bereits in einer anderen Zeile gewählt (für Dropdown-Sperre). */
export function isContainerBatchUsedInOtherRow(
  rows: AllocationRowLike[],
  containerBatchId: string,
  rowId: number | string,
): boolean {
  const id = String(containerBatchId ?? '').trim()
  if (!id) return false
  for (const row of rows) {
    if (row.id === rowId) continue
    if (row.mode !== 'kiste' || row.qty <= 0) continue
    if (String(row.container_batch_id ?? '') === id) return true
  }
  return false
}

/** Diese Zeile teilt sich die Kiste mit einer anderen Zeile. */
export function allocationRowHasDuplicateKiste(
  row: AllocationRowLike,
  rows: AllocationRowLike[],
): boolean {
  if (row.mode !== 'kiste' || row.qty <= 0) return false
  const id = String(row.container_batch_id ?? '').trim()
  if (!id) return false
  return isContainerBatchUsedInOtherRow(rows, id, row.id)
}

/** Mindestens zwei Zeilen mit gleicher Kiste. */
export function hasDuplicateKisteContainers(rows: AllocationRowLike[]): boolean {
  const seen = new Set<string>()
  for (const row of kisteRowsWithContainer(rows)) {
    if (seen.has(row.container_batch_id)) return true
    seen.add(row.container_batch_id)
  }
  return false
}
