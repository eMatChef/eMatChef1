import { defaultPrintFace, parsePrintFace, type PrintFace } from '@/print/printFace'
import type { PrintLayoutField } from '@/api/printLayouts'

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

function faceStorageKey(dept: string, kind: string) {
  return `ematchef.print-face.${dept}.${kind}`
}

export function savePrintFace(dept: string, kind: string, face: PrintFace) {
  if (!dept) return
  localStorage.setItem(faceStorageKey(dept, kind), JSON.stringify(face))
}

export function loadPrintFace(dept: string, kind: string): PrintFace {
  const fallback = defaultPrintFace(kind)
  if (!dept) return fallback
  try {
    const raw = localStorage.getItem(faceStorageKey(dept, kind))
    if (!raw) return fallback
    return parsePrintFace(JSON.parse(raw), kind)
  } catch {
    return fallback
  }
}

function boxesStorageKey(dept: string, layoutId: string) {
  return `ematchef.print-boxes.${dept}.${layoutId}`
}

export function saveJobFieldBoxes(dept: string, layoutId: string, fields: PrintLayoutField[]) {
  if (!dept || !layoutId) return
  localStorage.setItem(boxesStorageKey(dept, layoutId), JSON.stringify(fields))
}

export function loadJobFieldBoxes(dept: string, layoutId: string): PrintLayoutField[] | null {
  if (!dept || !layoutId) return null
  try {
    const raw = localStorage.getItem(boxesStorageKey(dept, layoutId))
    if (!raw) return null
    const parsed = JSON.parse(raw) as PrintLayoutField[]
    if (!Array.isArray(parsed) || !parsed.length) return null
    return parsed.filter((item) => item && typeof item.id === 'string' && typeof item.x === 'number')
  } catch {
    return null
  }
}
