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

/** Felder, die in der Zuordnungs-UI wählbar sind. */
export const IMPORT_UI_FIELDS: MaterialImportColumn[] = [
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
]

export type ColumnMapping = Partial<Record<MaterialImportColumn, number>>

/** Pro Datei-Spalte: zugeordnetes App-Feld oder leer. */
export type ColumnAssignment = MaterialImportColumn | ''

export function mappingToColumnAssignments(
  columnCount: number,
  mapping: ColumnMapping,
): ColumnAssignment[] {
  const assignments: ColumnAssignment[] = Array.from({ length: columnCount }, () => '')
  for (const field of IMPORT_UI_FIELDS) {
    const idx = mapping[field]
    if (typeof idx === 'number' && idx >= 0 && idx < columnCount) {
      assignments[idx] = field
    }
  }
  return assignments
}

export function columnAssignmentsToMapping(assignments: ColumnAssignment[]): ColumnMapping {
  const mapping: ColumnMapping = {}
  assignments.forEach((field, idx) => {
    if (field) mapping[field] = idx
  })
  return mapping
}

/** Erste Datenzeilen der Datei zur Anzeige unter der Zuordnungszeile. */
export function getSourcePreviewRows(
  matrix: string[][],
  headerRowIndex: number,
  maxRows = 15,
): string[][] {
  const result: string[][] = []
  for (let i = headerRowIndex + 1; i < matrix.length && result.length < maxRows; i++) {
    const row = matrix[i] || []
    if (!row.some((c) => cellToString(c))) continue
    const first = cellToString(row[0])
    if (first && HEADER_ALIASES[normalizeHeader(first)]) continue
    result.push(row.map((c) => cellToString(c)))
  }
  return result
}

const UNMAPPED = -1

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
  _existingMaterialId?: string | null
  _existingMaterialName?: string | null
  _parseWarnings?: string[]
}

export interface ImportFileRaw {
  matrix: string[][]
  headerRowIndex: number
  columnLabels: string[]
  suggestedMapping: ColumnMapping
}

const HEADER_ALIASES: Record<string, MaterialImportColumn> = {
  name: 'name',
  artikel: 'name',
  qty: 'qty',
  menge: 'qty',
  quantity: 'qty',
  unit: 'unit',
  einheit: 'unit',
  stk: 'unit',
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
  beschafft: 'acquired_year',
  jahr: 'acquired_year',
  year: 'acquired_year',
  unit_price: 'unit_price',
  preis: 'unit_price',
  'à': 'unit_price',
  a: 'unit_price',
  stueckpreis: 'unit_price',
  stückpreis: 'unit_price',
  total: 'unit_price',
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

export function cellToString(value: unknown): string {
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

export function findHeaderRowIndex(matrix: string[][]): number {
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

export function mergeMultiRowHeaders(matrix: string[][], headerStartIdx: number, depth = 3): string[] {
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

export function getColumnLabels(matrix: string[][], headerRowIndex: number): string[] {
  return mergeMultiRowHeaders(matrix, headerRowIndex)
}

export function buildSuggestedMapping(columnLabels: string[]): ColumnMapping {
  const mapping: ColumnMapping = {}
  columnLabels.forEach((label, idx) => {
    const key = normalizeHeader(label)
    const field = key ? HEADER_ALIASES[key] : undefined
    if (field && mapping[field] === undefined) {
      mapping[field] = idx
    }
  })
  return mapping
}

export function columnMappingToHeaderMap(
  columnCount: number,
  mapping: ColumnMapping,
): (MaterialImportColumn | null)[] {
  const map: (MaterialImportColumn | null)[] = Array.from({ length: columnCount }, () => null)
  for (const field of IMPORT_UI_FIELDS) {
    const idx = mapping[field]
    if (typeof idx === 'number' && idx >= 0 && idx < columnCount) {
      map[idx] = field
    }
  }
  return map
}

export function excelColumnLetter(index: number): string {
  let n = index + 1
  let s = ''
  while (n > 0) {
    const rem = (n - 1) % 26
    s = String.fromCharCode(65 + rem) + s
    n = Math.floor((n - 1) / 26)
  }
  return s
}

export function formatFileColumnOption(index: number, label: string): string {
  const letter = excelColumnLetter(index)
  const name = label.trim() || `Spalte ${index + 1}`
  return `${letter}: ${name}`
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

function parseYearField(raw: string): { year: string; acquiredOn: string } {
  const s = raw.trim()
  if (!s) return { year: '', acquiredOn: '' }

  const iso = s.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (iso) {
    return { year: iso[1], acquiredOn: `${iso[1]}-${iso[2]}-${iso[3]}` }
  }

  if (/^\d{4}$/.test(s)) {
    return { year: s, acquiredOn: acquiredDateFromYear(s) }
  }

  const yearMatch = s.match(/\b(19|20)\d{2}\b/)
  if (yearMatch) {
    const year = yearMatch[0]
    return { year, acquiredOn: acquiredDateFromYear(year) }
  }

  return { year: s.slice(0, 4), acquiredOn: '' }
}

function normalizePriceDisplay(raw: string): string {
  let s = raw.trim()
  if (!s) return ''
  s = s.replace(/\s*\/.*$/i, '')
  s = s.replace(/chf|fr\.?/gi, '').replace(/\s/g, '').replace(',', '.')
  const num = s.replace(/[^0-9.]/g, '')
  return num || raw.trim()
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

  const { year, acquiredOn } = parseYearField(data.acquired_year ?? '')

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
    acquired_year: year,
    acquired_on: acquiredOn,
    unit_price: normalizePriceDisplay(data.unit_price ?? ''),
    notes: (data.notes ?? '').trim(),
    duplicate_action: 'add_batch',
  }
}

function dataStartAfterHeader(matrix: string[][], headerIdx: number, headerMap: (MaterialImportColumn | null)[]): number {
  let start = headerIdx + 1
  const nameCols = headerMap.map((c, i) => (c === 'name' ? i : -1)).filter((i) => i >= 0)
  while (start < matrix.length && start < headerIdx + 5) {
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

/** Matrix mit manueller Spaltenzuordnung in Import-Zeilen umwandeln. */
export function parseMatrixWithMapping(
  matrix: string[][],
  headerRowIndex: number,
  mapping: ColumnMapping,
): MaterialImportRow[] {
  const normalized = matrix.map((row) => (row || []).map((c) => cellToString(c)))
  const nonEmptyRows = normalized.filter((row) => row.some((c) => c !== ''))
  if (nonEmptyRows.length < 2) return []

  const colCount = Math.max(
    ...nonEmptyRows.slice(headerRowIndex, headerRowIndex + 4).map((r) => r.length),
    0,
  )
  const headerMap = columnMappingToHeaderMap(colCount, mapping)
  if (!headerMap.some((c) => c === 'name')) return []

  const dataStart = dataStartAfterHeader(nonEmptyRows, headerRowIndex, headerMap)
  const rows: MaterialImportRow[] = []
  for (let i = dataStart; i < nonEmptyRows.length; i++) {
    const row = rowFromCells(nonEmptyRows[i], headerMap, i)
    if (row) rows.push(row)
  }
  return rows
}

export async function readImportMatrixFromFile(file: File): Promise<ImportFileRaw> {
  let matrix: string[][] = []

  const name = file.name.toLowerCase()
  if (name.endsWith('.xlsx') || name.endsWith('.xls')) {
    const buffer = await file.arrayBuffer()
    const workbook = XLSX.read(buffer, { type: 'array', cellDates: true })
    const sheetName = workbook.SheetNames[0]
    if (!sheetName) {
      return { matrix: [], headerRowIndex: 0, columnLabels: [], suggestedMapping: {} }
    }
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
  const nonEmpty = normalized.filter((row) => row.some((c) => c !== ''))
  const headerRowIndex = findHeaderRowIndex(nonEmpty)
  const columnLabels = getColumnLabels(nonEmpty, headerRowIndex)
  const suggestedMapping = buildSuggestedMapping(columnLabels)

  return {
    matrix: normalized,
    headerRowIndex,
    columnLabels,
    suggestedMapping,
  }
}

export interface ParseImportFileResult {
  rows: MaterialImportRow[]
  debug?: {
    headerRowIndex: number
    headerCells: string[]
    mappedColumns: string[]
    lineCount: number
  }
}

/** Automatische Zuordnung (Vorlage mit passenden Köpfen). */
export async function parseImportFile(file: File): Promise<ParseImportFileResult> {
  const raw = await readImportMatrixFromFile(file)
  const rows = parseMatrixWithMapping(raw.matrix, raw.headerRowIndex, raw.suggestedMapping)
  return {
    rows,
    debug: {
      headerRowIndex: raw.headerRowIndex,
      headerCells: raw.columnLabels,
      mappedColumns: Object.keys(raw.suggestedMapping),
      lineCount: raw.matrix.filter((r) => r.some((c) => c)).length,
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

export { UNMAPPED as COLUMN_UNMAPPED }
