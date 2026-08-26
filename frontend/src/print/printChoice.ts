export function printerStorageKey(dept: string) {
  return `ematchef.print-printer.${dept}`
}

export function layoutStorageKey(dept: string) {
  return `ematchef.print-layout.${dept}`
}

export function fieldsStorageKey(dept: string, kind: string) {
  return `ematchef.print-fields.${dept}.${kind}`
}

function choiceLabelStorageKey(dept: string) {
  return `ematchef.print-choice-label.${dept}`
}

export type PrintChoiceLabels = {
  printer: string
  layout: string
}

export function savePrintChoiceLabels(dept: string, labels: PrintChoiceLabels) {
  if (!dept) return
  localStorage.setItem(choiceLabelStorageKey(dept), JSON.stringify({
    printer: labels.printer,
    layout: labels.layout,
  }))
}

export function loadPrintChoiceLabels(dept: string): PrintChoiceLabels | null {
  if (!dept) return null
  try {
    const raw = localStorage.getItem(choiceLabelStorageKey(dept))
    if (!raw) return null
    const parsed = JSON.parse(raw) as Partial<PrintChoiceLabels>
    const printer = String(parsed.printer || '').trim()
    const layout = String(parsed.layout || '').trim()
    if (!printer && !layout) return null
    return { printer, layout }
  } catch {
    return null
  }
}

export function formatPrintChoice(labels: PrintChoiceLabels | null): string {
  if (!labels) return ''
  if (labels.layout && labels.printer) return `${labels.layout} · ${labels.printer}`
  return labels.layout || labels.printer
}

function nextCellStorageKey(dept: string, layoutId: string) {
  return `ematchef.print-next-cell.${dept}.${layoutId}`
}

export function saveNextStartCell(dept: string, layoutId: string, cell: number) {
  if (!dept || !layoutId) return
  const n = Math.max(1, Math.floor(cell))
  localStorage.setItem(nextCellStorageKey(dept, layoutId), String(n))
}

export function loadNextStartCell(dept: string, layoutId: string): number | null {
  if (!dept || !layoutId) return null
  const raw = localStorage.getItem(nextCellStorageKey(dept, layoutId))
  const n = Number(raw)
  if (!Number.isFinite(n) || n < 1) return null
  return Math.floor(n)
}

export function loadStoredNextStartCell(dept: string): number {
  const layoutId = localStorage.getItem(layoutStorageKey(dept)) || ''
  return loadNextStartCell(dept, layoutId) || 0
}
