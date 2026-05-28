import * as XLSX from 'xlsx'
import { normalizeMaterialMetricInput } from '@/utils/materialMetricUnits'

/** Spalten der Import-Vorlage (Kopfzeile). */
export const MATERIAL_IMPORT_COLUMNS = [
  'name',
  'qty',
  'unit',
  'size_length',
  'size_width',
  'size_height',
  'size_unit',
  'color',
  'material',
  'supplier',
  'acquired_year',
  'unit_price',
  'notes',
] as const

export type MaterialImportColumn = (typeof MATERIAL_IMPORT_COLUMNS)[number]

/** Nur Jahr angegeben: Monat/Tag vom Importzeitpunkt (heute), Jahr aus der Datei. */
export function acquiredDateFromYear(yearStr: string): string {
  const year = parseInt(yearStr, 10)
  if (!Number.isFinite(year) || year < 1900 || year > 2100) {
    return ''
  }
  const now = new Date()
  const month = now.getMonth() + 1
  const maxDay = new Date(year, month, 0).getDate()
  const day = Math.min(now.getDate(), maxDay)
  const mm = String(month).padStart(2, '0')
  const dd = String(day).padStart(2, '0')
  return `${year}-${mm}-${dd}`
}

export interface MaterialImportRow {
  row_index: number
  name: string
  qty: string
  unit: string
  size_length: string
  size_width: string
  size_height: string
  size_unit: string
  color: string
  material: string
  supplier_name: string
  supplier_id: string
  acquired_year: string
  acquired_on: string
  unit_price: string
  notes: string
  duplicate_action: 'add_batch' | 'skip' | 'create'
  /** Client-seitig: existierendes Material */
  _existingMaterialId?: string | null
  _existingMaterialName?: string | null
  _parseWarnings?: string[]
}

const HEADER_ALIASES: Record<string, MaterialImportColumn> = {
  name: 'name',
  artikel: 'name',
  qty: 'qty',
  menge: 'qty',
  quantity: 'qty',
  unit: 'unit',
  einheit: 'unit',
  size_length: 'size_length',
  laenge: 'size_length',
  länge: 'size_length',
  length: 'size_length',
  size_width: 'size_width',
  breite: 'size_width',
  width: 'size_width',
  size_height: 'size_height',
  hoehe: 'size_height',
  höhe: 'size_height',
  height: 'size_height',
  size_unit: 'size_unit',
  groesse_einheit: 'size_unit',
  grösse_einheit: 'size_unit',
  einhe: 'size_unit',
  color: 'color',
  farbe: 'color',
  material: 'material',
  supplier: 'supplier',
  lieferant: 'supplier',
  acquired_year: 'acquired_year',
  beschaffung: 'acquired_year',
  jahr: 'acquired_year',
  year: 'acquired_year',
  acquired_on: 'acquired_year',
  unit_price: 'unit_price',
  preis: 'unit_price',
  'à': 'unit_price',
  stueckpreis: 'unit_price',
  stückpreis: 'unit_price',
  notes: 'notes',
  zusatz: 'notes',
  bemerkung: 'notes',
  description: 'notes',
}

function normalizeHeader(cell: string): string {
  return cell
    .replace(/^\uFEFF/, '')
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, '_')
}

function cellToString(value: unknown): string {
  if (value == null || value === '') return ''
  if (value instanceof Date) {
    if (!Number.isNaN(value.getTime())) {
      return value.toISOString().slice(0, 10)
    }
    return ''
  }
  return String(value).trim()
}

function scoreHeaderRow(cells: string[]): number {
  let score = 0
  for (const c of cells) {
    const key = normalizeHeader(c)
    if (key && HEADER_ALIASES[key]) score++
  }
  return score
}

/** Erste Zeile mit erkannten Spaltenköpfen (z. B. Zeile 2 bei Materialliste mit Titelzeile). */
function findHeaderRowIndex(matrix: string[][]): number {
  const limit = Math.min(matrix.length, 30)
  let bestIdx = 0
  let bestScore = 0
  for (let i = 0; i < limit; i++) {
    const score = scoreHeaderRow(matrix[i] || [])
    if (score > bestScore) {
      bestScore = score
      bestIdx = i
    }
  }
  return bestScore >= 1 ? bestIdx : 0
}

function buildPositionalHeaderMap(columnCount: number): (MaterialImportColumn | null)[] {
  const map: (MaterialImportColumn | null)[] = []
  for (let i = 0; i < columnCount; i++) {
    map.push(MATERIAL_IMPORT_COLUMNS[i] ?? null)
  }
  return map
}

function headerMapHasName(headerMap: (MaterialImportColumn | null)[]): boolean {
  return headerMap.some((c) => c === 'name')
}

/** Mehrzeilige Köpfe (Titel + Unterspalten): pro Spalte die unterste nicht-leere Bezeichnung. */
function mergeMultiRowHeaders(matrix: string[][], headerStartIdx: number, depth = 3): string[] {
  const end = Math.min(matrix.length, headerStartIdx + depth)
  let maxCols = 0
  for (let r = headerStartIdx; r < end; r++) {
    maxCols = Math.max(maxCols, matrix[r]?.length ?? 0)
  }
  const merged: string[] = []
  for (let c = 0; c < maxCols; c++) {
    let label = ''
    for (let r = headerStartIdx; r < end; r++) {
      const cell = (matrix[r]?.[c] ?? '').trim()
      if (cell) label = cell
    }
    merged.push(label)
  }
  return merged
}

function resolveHeaderMap(matrix: string[][], headerIdx: number): (MaterialImportColumn | null)[] {
  const merged = mergeMultiRowHeaders(matrix, headerIdx)
  let headerMap = mapHeaders(merged)
  if (!headerMapHasName(headerMap) && headerIdx + 1 < matrix.length) {
    const merged2 = mergeMultiRowHeaders(matrix, headerIdx, 4)
    headerMap = mapHeaders(merged2)
  }
  if (!headerMapHasName(headerMap)) {
    const sampleData = matrix[headerIdx + 1] || matrix[headerIdx + 2] || []
    if (sampleData.length >= 2 && cellToString(sampleData[0]) !== '') {
      return buildPositionalHeaderMap(Math.max(merged.length, sampleData.length))
    }
  }
  return headerMap
}

function detectDelimiter(line: string): string {
  const semi = (line.match(/;/g) || []).length
  const comma = (line.match(/,/g) || []).length
  return semi >= comma ? ';' : ','
}

function parseCsvLine(line: string, delimiter: string): string[] {
  const out: string[] = []
  let cur = ''
  let inQuotes = false
  for (let i = 0; i < line.length; i++) {
    const ch = line[i]
    if (ch === '"') {
      if (inQuotes && line[i + 1] === '"') {
        cur += '"'
        i++
      } else {
        inQuotes = !inQuotes
      }
      continue
    }
    if (ch === delimiter && !inQuotes) {
      out.push(cur)
      cur = ''
      continue
    }
    cur += ch
  }
  out.push(cur)
  return out.map((c) => c.trim())
}

function mapHeaders(headerCells: string[]): (MaterialImportColumn | null)[] {
  return headerCells.map((h) => {
    const key = normalizeHeader(h)
    return HEADER_ALIASES[key] ?? null
  })
}

function rowFromCells(cells: string[], headerMap: (MaterialImportColumn | null)[], rowIndex: number): MaterialImportRow | null {
  const data: Partial<Record<MaterialImportColumn, string>> = {}
  headerMap.forEach((col, i) => {
    if (!col) return
    data[col] = cells[i] ?? ''
  })

  const name = (data.name ?? '').trim()
  if (!name) return null

  const sizeUnit = (data.size_unit ?? '').trim().toLowerCase()
  let sizeLength = (data.size_length ?? '').trim()
  if (sizeLength && sizeUnit === 'm') {
    sizeLength = `${sizeLength} m`
  } else if (sizeLength && sizeUnit === 'mm') {
    sizeLength = `${sizeLength} mm`
  }

  const normalizedLength = normalizeMaterialMetricInput(sizeLength, 'cm') ?? sizeLength
  const normalizedWidth = normalizeMaterialMetricInput((data.size_width ?? '').trim(), 'cm') ?? (data.size_width ?? '').trim()
  const normalizedHeight = normalizeMaterialMetricInput((data.size_height ?? '').trim(), 'cm') ?? (data.size_height ?? '').trim()

  const year = (data.acquired_year ?? '').trim()
  let acquiredOn = ''
  if (/^\d{4}-\d{2}-\d{2}$/.test(year)) {
    acquiredOn = year
  } else if (/^\d{4}$/.test(year)) {
    acquiredOn = acquiredDateFromYear(year)
  }

  return {
    row_index: rowIndex,
    name,
    qty: (data.qty ?? '').trim(),
    unit: (data.unit ?? 'Stk').trim(),
    size_length: normalizedLength,
    size_width: normalizedWidth,
    size_height: normalizedHeight,
    size_unit: sizeUnit,
    color: (data.color ?? '').trim(),
    material: (data.material ?? '').trim(),
    supplier_name: (data.supplier ?? '').trim(),
    supplier_id: '',
    acquired_year: /^\d{4}$/.test(year) ? year : year.slice(0, 4),
    acquired_on: acquiredOn,
    unit_price: (data.unit_price ?? '').trim(),
    notes: (data.notes ?? '').trim(),
    duplicate_action: 'add_batch',
  }
}

function dataStartAfterHeader(matrix: string[][], headerIdx: number): number {
  let start = headerIdx + 1
  const mergedOnce = mapHeaders(mergeMultiRowHeaders(matrix, headerIdx))
  const nameCols = mergedOnce.map((c, i) => (c === 'name' ? i : -1)).filter((i) => i >= 0)
  while (start < matrix.length && start < headerIdx + 4) {
    const row = matrix[start] || []
    const first = cellToString(row[0])
    if (!first) {
      start++
      continue
    }
    const key = normalizeHeader(first)
    if (HEADER_ALIASES[key]) {
      start++
      continue
    }
    if (nameCols.length > 0 && nameCols.every((col) => !cellToString(row[col]))) {
      start++
      continue
    }
    break
  }
  return start
}

function parseMatrix(matrix: string[][]): MaterialImportRow[] {
  const normalized = matrix.map((row) => (row || []).map((c) => cellToString(c)))
  const nonEmptyRows = normalized.filter((row) => row.some((c) => c !== ''))
  if (nonEmptyRows.length < 2) return []

  const headerIdx = findHeaderRowIndex(nonEmptyRows)
  const headerMap = resolveHeaderMap(nonEmptyRows, headerIdx)
  const dataStart = dataStartAfterHeader(nonEmptyRows, headerIdx)

  const rows: MaterialImportRow[] = []
  for (let i = dataStart; i < nonEmptyRows.length; i++) {
    const row = rowFromCells(nonEmptyRows[i], headerMap, i)
    if (row) rows.push(row)
  }
  return rows
}

export function parseCsvText(text: string): MaterialImportRow[] {
  const normalized = text.replace(/^\uFEFF/, '')
  let lines = normalized.split(/\r?\n/).filter((l) => l.trim() !== '')
  if (lines.length < 2) return []

  // Excel-Europe: erste Zeile "sep=;"
  if (/^sep\s*=/i.test(lines[0].trim())) {
    lines = lines.slice(1)
    if (lines.length < 2) return []
  }

  const delimiter = detectDelimiter(lines[0])
  const matrix = lines.map((line) => parseCsvLine(line, delimiter))
  return parseMatrix(matrix)
}

export interface ParseImportFileResult {
  rows: MaterialImportRow[]
  /** Kurzinfo für Fehlermeldungen */
  debug?: {
    headerRowIndex: number
    headerCells: string[]
    mappedColumns: string[]
    lineCount: number
  }
}

export async function parseImportFile(file: File): Promise<ParseImportFileResult> {
  const name = file.name.toLowerCase()
  let matrix: string[][] = []

  if (name.endsWith('.xlsx') || name.endsWith('.xls')) {
    const buffer = await file.arrayBuffer()
    const workbook = XLSX.read(buffer, { type: 'array', cellDates: true })
    const sheetName = workbook.SheetNames[0]
    if (!sheetName) return { rows: [] }
    const sheet = workbook.Sheets[sheetName]
    const raw = XLSX.utils.sheet_to_json<unknown[]>(sheet, { header: 1, defval: '' })
    matrix = raw.map((row) => (Array.isArray(row) ? row : []).map((c) => cellToString(c)))
  } else {
    const text = await file.text()
    const normalized = text.replace(/^\uFEFF/, '')
    let lines = normalized.split(/\r?\n/).filter((l) => l.trim() !== '')
    if (/^sep\s*=/i.test(lines[0]?.trim() ?? '')) {
      lines = lines.slice(1)
    }
    if (lines.length >= 1) {
      const delimiter = detectDelimiter(lines[0])
      matrix = lines.map((line) => parseCsvLine(line, delimiter))
    }
  }

  const normalized = matrix.map((row) => (row || []).map((c) => cellToString(c)))
  const nonEmptyRows = normalized.filter((row) => row.some((c) => c !== ''))
  const headerIdx = findHeaderRowIndex(nonEmptyRows)
  const headerCells = mergeMultiRowHeaders(nonEmptyRows, headerIdx)
  const headerMap = resolveHeaderMap(nonEmptyRows, headerIdx)
  const rows = parseMatrix(normalized)

  return {
    rows,
    debug: {
      headerRowIndex: headerIdx,
      headerCells,
      mappedColumns: headerMap.filter((c): c is MaterialImportColumn => c != null),
      lineCount: nonEmptyRows.length,
    },
  }
}

export function rowsToApiPayload(rows: MaterialImportRow[]) {
  return rows.map((r) => ({
    row_index: r.row_index,
    name: r.name.trim(),
    qty: parseInt(r.qty, 10) || 0,
    color: r.color || null,
    material: r.material || null,
    size_length: r.size_length || null,
    size_width: r.size_width || null,
    size_height: r.size_height || null,
    supplier_name: r.supplier_name || null,
    supplier_id: r.supplier_id || null,
    acquired_on: r.acquired_on || null,
    acquired_year: r.acquired_year || null,
    unit_price: r.unit_price || null,
    notes: r.notes || null,
    duplicate_action: r.duplicate_action,
  }))
}

export function downloadTemplateCsv(baseUrl = '') {
  const path = `${baseUrl}/templates/material-import-vorlage.csv`
  const a = document.createElement('a')
  a.href = path
  a.download = 'material-import-vorlage.csv'
  a.rel = 'noopener'
  document.body.appendChild(a)
  a.click()
  a.remove()
}
