import * as XLSX from 'xlsx'
import { normalizeMaterialMetricInput } from '@/utils/materialMetricUnits'

/** Upload-Limits: begrenzen DoS-Fläche beim Parsen unbekannter Dateien. */
export const MAX_IMPORT_FILE_BYTES = 5 * 1024 * 1024
export const ALLOWED_IMPORT_EXTENSIONS = ['.csv', '.xlsx', '.xls'] as const

export type ImportFileErrorCode = 'too_large' | 'bad_type'

/** Fehler mit Code, damit die UI eine passende Meldung wählen kann. */
export class ImportFileError extends Error {
  code: ImportFileErrorCode
  constructor(code: ImportFileErrorCode, message: string) {
    super(message)
    this.name = 'ImportFileError'
    this.code = code
  }
}

function assertAcceptableImportFile(file: File): void {
  if (file.size > MAX_IMPORT_FILE_BYTES) {
    throw new ImportFileError('too_large', `File too large: ${file.size} bytes`)
  }
  const lower = file.name.toLowerCase()
  if (!ALLOWED_IMPORT_EXTENSIONS.some((ext) => lower.endsWith(ext))) {
    throw new ImportFileError('bad_type', `Unsupported file type: ${file.name}`)
  }
}

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
  'manufacturer',
  'supplier',
  'storage',
  'stock_location_mode',
  'rack',
  'slot',
  'container',
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
  'manufacturer',
  'supplier',
  'storage',
  'stock_location_mode',
  'rack',
  'slot',
  'container',
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

export type MaterialImportMatchKind = 'exact' | 'specs'

export interface MaterialImportSpecParts {
  size_length?: string | null
  size_width?: string | null
  size_height?: string | null
  color?: string | null
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
  manufacturer: string
  supplier_name: string
  supplier_id: string
  storage_name: string
  storage_address_id: string
  stock_location_mode: string
  rack_id: string
  rack_name: string
  slot_id: string
  slot_name: string
  container_name: string
  container_batch_id: string
  acquired_year: string
  acquired_on: string
  unit_price: string
  notes: string
  duplicate_action: 'add_batch' | 'skip' | 'create'
  /** Standard: Zeile wird importiert; abwählen = überspringen. */
  import_selected: boolean
  _existingMaterialId?: string | null
  _existingMaterialName?: string | null
  _existingMatchKind?: MaterialImportMatchKind | null
  _parseWarnings?: string[]
}

/** Duplikat-Schlüssel: Name + Masse + Farbe (wie Backend MaterialImportService). */
export function buildMaterialImportMatchKey(parts: {
  name: string
  size_length?: string | null
  size_width?: string | null
  size_height?: string | null
  color?: string | null
}): string {
  const normalizeName = (name: string) => name.trim().toLowerCase()
  const normalizeSize = (raw?: string | null): string => {
    if (!raw?.trim()) return ''
    return normalizeMaterialMetricInput(raw.trim(), 'cm') ?? raw.trim()
  }
  return [
    normalizeName(parts.name),
    normalizeSize(parts.size_length),
    normalizeSize(parts.size_width),
    normalizeSize(parts.size_height),
    normalizeName(parts.color ?? ''),
  ].join('|')
}

/** Nur Masse/Farbe — für «gleiche Specs, verwandter Name». */
export function buildMaterialImportSpecKey(parts: MaterialImportSpecParts): string {
  return buildMaterialImportMatchKey({ name: '', ...parts })
}

export function hasMaterialImportSpecs(parts: MaterialImportSpecParts): boolean {
  return !!(
    parts.size_length?.trim()
    || parts.size_width?.trim()
    || parts.size_height?.trim()
    || parts.color?.trim()
  )
}

function namesRelatedForImport(nameA: string, nameB: string): boolean {
  const a = nameA.trim().toLowerCase()
  const b = nameB.trim().toLowerCase()
  if (!a || !b) return false
  if (a === b) return true
  if (a.startsWith(`${b} `) || b.startsWith(`${a} `)) return true
  const stripLengthSuffix = (value: string) => value.replace(/\s+\d+(?:[.,]\d+)?\s*m\s*$/i, '').trim()
  const baseA = stripLengthSuffix(a)
  const baseB = stripLengthSuffix(b)
  return baseA === baseB || a.startsWith(baseB) || b.startsWith(baseA)
}

/** Exakter Treffer oder gleiche Masse + ähnlicher Name (z. B. nach «+ 8 m an Name»). */
export function findImportMaterialMatch(
  row: MaterialImportRow,
  materials: Array<MaterialImportSpecMaterial & { id: string }>,
): { material: MaterialImportSpecMaterial & { id: string }; kind: MaterialImportMatchKind } | null {
  const rowKey = buildMaterialImportMatchKey(row)
  for (const m of materials) {
    if (buildMaterialImportMatchKey(m) === rowKey) {
      return { material: m, kind: 'exact' }
    }
  }

  if (!hasMaterialImportSpecs(row)) return null

  const rowSpecKey = buildMaterialImportSpecKey(row)
  for (const m of materials) {
    if (!hasMaterialImportSpecs(m)) continue
    if (buildMaterialImportSpecKey(m) !== rowSpecKey) continue
    if (namesRelatedForImport(row.name, m.name)) {
      return { material: m, kind: 'specs' }
    }
  }

  return null
}

export type MaterialImportSpecWarningCode =
  | 'name_exists_other_specs_db'
  | 'name_exists_other_specs_file'
  | 'will_add_batch'
  | 'matched_existing_by_specs'
  | 'would_create_duplicate_catalog'

export interface MaterialImportSpecWarning {
  code: MaterialImportSpecWarningCode
  existingMaterialName?: string
  existingSpecs?: string
  importSpecs?: string
  otherRowNumber?: number
}

/** Kurztext für Warnungen (Länge/Breite/Höhe/Farbe). */
export function formatMaterialImportSpecs(parts: MaterialImportSpecParts): string {
  const bits: string[] = []
  const length = parts.size_length?.trim()
  const width = parts.size_width?.trim()
  const height = parts.size_height?.trim()
  const color = parts.color?.trim()
  if (length) bits.push(`L ${length} cm`)
  if (width) bits.push(`B ${width} cm`)
  if (height) bits.push(`H ${height} cm`)
  if (color) bits.push(color)
  return bits.length > 0 ? bits.join(' · ') : '—'
}

type MaterialImportSpecMaterial = MaterialImportSpecParts & {
  id?: string
  name: string
}

/** Clientseitige Hinweise vor dem Import (Name gleich, Masse anders / Batch-Anhängen). */
export function computeMaterialImportSpecWarnings(
  row: MaterialImportRow,
  allRows: MaterialImportRow[],
  materials: MaterialImportSpecMaterial[],
): MaterialImportSpecWarning[] {
  if (row.import_selected === false) return []

  const normName = row.name.trim().toLowerCase()
  if (!normName) return []

  const warnings: MaterialImportSpecWarning[] = []
  const rowKey = buildMaterialImportMatchKey(row)
  const importSpecs = formatMaterialImportSpecs(row)
  const matchedId = row._existingMaterialId ?? null
  const matchedKind = row._existingMatchKind ?? null

  if (matchedId && matchedKind === 'specs') {
    const hit = materials.find((m) => m.id === matchedId)
    warnings.push({
      code: 'matched_existing_by_specs',
      existingMaterialName: hit?.name ?? row._existingMaterialName ?? '',
      existingSpecs: hit ? formatMaterialImportSpecs(hit) : undefined,
      importSpecs,
    })
  }

  if (!matchedId) {
    const dbOther = materials.filter(
      (m) => m.name.trim().toLowerCase() === normName && buildMaterialImportMatchKey(m) !== rowKey,
    )
    if (dbOther.length > 0) {
      const hit = dbOther[0]
      warnings.push({
        code: 'name_exists_other_specs_db',
        existingMaterialName: hit.name,
        existingSpecs: formatMaterialImportSpecs(hit),
        importSpecs,
      })
    }

    const sameBaseName = materials.filter(
      (m) => namesRelatedForImport(row.name, m.name) && buildMaterialImportMatchKey(m) !== rowKey,
    )
    if (sameBaseName.length > 0 && hasMaterialImportSpecs(row) && dbOther.length === 0) {
      warnings.push({
        code: 'would_create_duplicate_catalog',
        existingMaterialName: sameBaseName[0].name,
        existingSpecs: formatMaterialImportSpecs(sameBaseName[0]),
        importSpecs,
      })
    }
  }

  const fileOther = allRows.find(
    (other) =>
      other !== row
      && other.import_selected !== false
      && other.name.trim().toLowerCase() === normName
      && buildMaterialImportMatchKey(other) !== rowKey,
  )
  if (fileOther) {
    const otherIdx = allRows.indexOf(fileOther)
    warnings.push({
      code: 'name_exists_other_specs_file',
      importSpecs,
      existingSpecs: formatMaterialImportSpecs(fileOther),
      otherRowNumber: otherIdx >= 0 ? otherIdx + 1 : undefined,
    })
  }

  if (row._existingMaterialId && row.duplicate_action === 'add_batch' && matchedKind !== 'specs') {
    const hit = materials.find((m) => m.id === row._existingMaterialId)
    warnings.push({
      code: 'will_add_batch',
      existingMaterialName: hit?.name ?? row._existingMaterialName ?? row.name,
      existingSpecs: hit ? formatMaterialImportSpecs(hit) : undefined,
      importSpecs,
    })
  }

  return warnings
}

/** Länge als lesbares Suffix (z. B. 1600 → «16 m»). */
export function formatImportLengthNameSuffix(sizeLength: string): string {
  const raw = sizeLength.trim().replace(',', '.')
  if (!raw) return ''
  const n = parseFloat(raw.replace(/[^\d.]/g, ''))
  if (!Number.isFinite(n) || n <= 0) return raw.includes('m') || raw.includes('cm') ? raw : `${raw} cm`
  if (n >= 100 && n % 100 === 0) return `${n / 100} m`
  if (n >= 100) return `${(n / 100).toFixed(1).replace(/\.0$/, '')} m`
  return `${n} cm`
}

/** Hängt Länge an den Artikelnamen an, wenn noch nicht enthalten. */
export function appendLengthSuffixToImportName(row: MaterialImportRow): boolean {
  const length = row.size_length?.trim()
  const base = row.name.trim()
  if (!length || !base) return false

  const suffix = formatImportLengthNameSuffix(length)
  if (!suffix) return false

  const lower = base.toLowerCase()
  const suffixLower = suffix.toLowerCase()
  if (lower.includes(suffixLower) || lower.includes(length.toLowerCase())) {
    return false
  }

  row.name = `${base} ${suffix}`
  return true
}

export function canAppendLengthToImportName(row: MaterialImportRow): boolean {
  if (!row.size_length?.trim() || !row.name.trim()) return false
  const suffix = formatImportLengthNameSuffix(row.size_length)
  if (!suffix) return false
  const lower = row.name.trim().toLowerCase()
  return !lower.includes(suffix.toLowerCase()) && !lower.includes(row.size_length.trim().toLowerCase())
}

export type ImportRowStorageIssue = 'lager' | 'mode' | 'rack' | 'slot' | 'container'

/** Pflicht vor Import: Lager + Gestell/Fach oder Kiste (wie Material-Wizard). */
export function getImportRowStorageIssues(row: MaterialImportRow): ImportRowStorageIssue[] {
  const issues: ImportRowStorageIssue[] = []
  if (!row.storage_address_id?.trim() && !row.storage_name?.trim()) {
    issues.push('lager')
  }
  const mode = row.stock_location_mode
  if (mode !== 'slot' && mode !== 'kiste') {
    issues.push('mode')
    return issues
  }
  if (mode === 'kiste') {
    if (!row.container_batch_id?.trim() && !row.container_name?.trim()) {
      issues.push('container')
    }
    return issues
  }
  if (!row.rack_id?.trim() && !row.rack_name?.trim()) {
    issues.push('rack')
  }
  if (!row.slot_id?.trim() && !row.slot_name?.trim()) {
    issues.push('slot')
  }
  return issues
}

export function isImportRowStorageComplete(row: MaterialImportRow): boolean {
  return getImportRowStorageIssues(row).length === 0
}

const STORAGE_OVERRIDE_FIELDS = [
  'storage_name',
  'storage_address_id',
  'stock_location_mode',
  'rack_id',
  'rack_name',
  'slot_id',
  'slot_name',
  'container_name',
  'container_batch_id',
] as const

const EDITABLE_OVERRIDE_FIELDS = [
  'name',
  'qty',
  'unit',
  'size_length',
  'size_width',
  'size_height',
  'size_unit',
  'color',
  'material',
  'manufacturer',
  'supplier_name',
  'supplier_id',
  'acquired_year',
  'acquired_on',
  'unit_price',
  'notes',
] as const

/** Manuelle Vorschau-Änderungen beim Neu-Parsen der Datei beibehalten. */
export function mergeImportRowOverrides(parsed: MaterialImportRow, existing: MaterialImportRow): MaterialImportRow {
  const merged: MaterialImportRow = { ...parsed }

  const hasStorageOverride =
    !!existing.storage_address_id?.trim()
    || !!existing.stock_location_mode
    || !!existing.storage_name?.trim()
    || !!existing.rack_id?.trim()
    || !!existing.container_batch_id?.trim()

  if (hasStorageOverride) {
    for (const field of STORAGE_OVERRIDE_FIELDS) {
      merged[field] = existing[field]
    }
  }

  if (existing.supplier_id?.trim()) {
    merged.supplier_name = existing.supplier_name
    merged.supplier_id = existing.supplier_id
  }

  for (const field of EDITABLE_OVERRIDE_FIELDS) {
    const prev = existing[field]
    const next = parsed[field]
    if (prev !== undefined && prev !== '' && prev !== next) {
      merged[field] = prev
    }
  }

  merged.duplicate_action = existing.duplicate_action || parsed.duplicate_action
  merged.import_selected = existing.import_selected !== false
  merged._existingMaterialId = existing._existingMaterialId ?? parsed._existingMaterialId
  merged._existingMaterialName = existing._existingMaterialName ?? parsed._existingMaterialName

  return merged
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
  materialart: 'material',
  manufacturer: 'manufacturer',
  hersteller: 'manufacturer',
  marke: 'manufacturer',
  brand: 'manufacturer',
  supplier: 'supplier',
  lieferant: 'supplier',
  gekauft_von: 'supplier',
  storage: 'storage',
  lager: 'storage',
  magazin: 'storage',
  lagerort: 'storage',
  standort: 'storage',
  stock_location_mode: 'stock_location_mode',
  lagerung: 'stock_location_mode',
  lagerart: 'stock_location_mode',
  lagerplatz_typ: 'stock_location_mode',
  gestell: 'rack',
  rack: 'rack',
  regal: 'rack',
  slot: 'slot',
  fach: 'slot',
  platz: 'slot',
  container: 'container',
  kiste: 'container',
  tasche: 'container',
  box: 'container',
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

function inferStockLocationModeFromCells(data: Partial<Record<MaterialImportColumn, string>>): string {
  const explicit = (data.stock_location_mode ?? '').trim().toLowerCase()
  if (['kiste', 'kisten', 'tasche', 'box', 'container'].includes(explicit)) return 'kiste'
  if (['slot', 'gestell', 'rack', 'regal', 'fach', 'platz'].includes(explicit)) return 'slot'
  if ((data.container ?? '').trim()) return 'kiste'
  if ((data.rack ?? '').trim() || (data.slot ?? '').trim()) return 'slot'
  return explicit
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
    manufacturer: (data.manufacturer ?? '').trim(),
    supplier_name: (data.supplier ?? '').trim(),
    supplier_id: '',
    storage_name: (data.storage ?? '').trim(),
    storage_address_id: '',
    stock_location_mode: inferStockLocationModeFromCells(data),
    rack_id: '',
    rack_name: (data.rack ?? '').trim(),
    slot_id: '',
    slot_name: (data.slot ?? '').trim(),
    container_name: (data.container ?? '').trim(),
    container_batch_id: '',
    acquired_year: year,
    acquired_on: acquiredOn,
    unit_price: normalizePriceDisplay(data.unit_price ?? ''),
    notes: (data.notes ?? '').trim(),
    duplicate_action: 'add_batch',
    import_selected: true,
  }
}

/** Zeilen, die beim Import mitgeschickt werden. */
export function rowsForImport(rows: MaterialImportRow[]): MaterialImportRow[] {
  return rows.filter((r) => r.import_selected !== false)
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
  assertAcceptableImportFile(file)
  let matrix: string[][] = []

  const name = file.name.toLowerCase()
  if (name.endsWith('.xlsx') || name.endsWith('.xls')) {
    const buffer = await file.arrayBuffer()
    // Nur Zellwerte lesen: keine Formeln/HTML/VBA auswerten (kleinere Angriffsfläche).
    const workbook = XLSX.read(buffer, {
      type: 'array',
      cellDates: true,
      cellFormula: false,
      cellHTML: false,
      bookVBA: false,
    })
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
  return rowsForImport(rows).map((r) => ({
    row_index: r.row_index,
    name: r.name.trim(),
    qty: parseInt(r.qty, 10) || 0,
    color: r.color || null,
    material: r.material || null,
    manufacturer: r.manufacturer || null,
    size_length: r.size_length || null,
    size_width: r.size_width || null,
    size_height: r.size_height || null,
    supplier_name: r.supplier_name || null,
    supplier_id: r.supplier_id || null,
    storage_name: r.storage_name || null,
    storage_address_id: r.storage_address_id || null,
    stock_location_mode: r.stock_location_mode || null,
    rack_id: r.rack_id || null,
    rack_name: r.rack_name || null,
    slot_id: r.slot_id || null,
    slot_name: r.slot_name || null,
    container_name: r.container_name || null,
    container_batch_id: r.container_batch_id || null,
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
