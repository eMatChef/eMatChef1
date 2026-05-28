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
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, '_')
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

export function parseCsvText(text: string): MaterialImportRow[] {
  const normalized = text.replace(/^\uFEFF/, '')
  const lines = normalized.split(/\r?\n/).filter((l) => l.trim() !== '')
  if (lines.length < 2) return []

  const delimiter = detectDelimiter(lines[0])
  const headerCells = parseCsvLine(lines[0], delimiter)
  const headerMap = mapHeaders(headerCells)

  const rows: MaterialImportRow[] = []
  for (let i = 1; i < lines.length; i++) {
    const cells = parseCsvLine(lines[i], delimiter)
    const row = rowFromCells(cells, headerMap, i)
    if (row) rows.push(row)
  }
  return rows
}

export async function parseImportFile(file: File): Promise<MaterialImportRow[]> {
  const name = file.name.toLowerCase()
  if (name.endsWith('.xlsx') || name.endsWith('.xls')) {
    const buffer = await file.arrayBuffer()
    const workbook = XLSX.read(buffer, { type: 'array' })
    const sheetName = workbook.SheetNames[0]
    if (!sheetName) return []
    const sheet = workbook.Sheets[sheetName]
    const matrix = XLSX.utils.sheet_to_json<string[]>(sheet, { header: 1, defval: '' }) as string[][]
    if (matrix.length < 2) return []

    const headerMap = mapHeaders(matrix[0].map((c) => String(c ?? '')))
    const rows: MaterialImportRow[] = []
    for (let i = 1; i < matrix.length; i++) {
      const cells = matrix[i].map((c) => String(c ?? ''))
      const row = rowFromCells(cells, headerMap, i)
      if (row) rows.push(row)
    }
    return rows
  }

  const text = await file.text()
  return parseCsvText(text)
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
