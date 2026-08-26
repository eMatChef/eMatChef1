/** 0-based cell index and page for item `itemIndex` on a multi-cell sheet. */
export function sheetCellForItem(
  itemIndex: number,
  cellCount: number,
  startIndex: number,
): { pageIndex: number; cellIndex: number } {
  const count = Math.max(1, cellCount)
  const start = Math.max(0, Math.min(startIndex, count - 1))
  const abs = start + Math.max(0, itemIndex)
  return {
    pageIndex: Math.floor(abs / count),
    cellIndex: abs % count,
  }
}

export function sheetPageCount(itemCount: number, cellCount: number, startIndex: number): number {
  if (itemCount <= 0) return 1
  return sheetCellForItem(itemCount - 1, cellCount, startIndex).pageIndex + 1
}

/** 1-based next free cell after printing `itemCount` labels from `startIndex`. */
export function nextStartCell(itemCount: number, cellCount: number, startIndex: number): number {
  const count = Math.max(1, cellCount)
  const start = Math.max(0, Math.min(startIndex, count - 1))
  return ((start + Math.max(0, itemCount)) % count) + 1
}
