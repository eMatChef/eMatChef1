import type { GrossanlassCommitment } from '@/api/grossanlassCommitments'
import type { SandboxComboComponent } from '@/views/dev/materialSandboxTypes'
import { commitmentStemKey } from '@/views/grossanlass/gaCharge'
import type { GaPreviewRow } from '@/views/grossanlass/grossanlassMaterialsPreviewData'

type Translate = (key: string, values?: Record<string, string | number>) => string

const PART_MARK = '-part-'

export function isBestandPartLine(line: SandboxComboComponent): boolean {
  return line.id.includes(PART_MARK)
}

export function bestandChargeId(line: SandboxComboComponent): string {
  const at = line.id.indexOf(PART_MARK)
  return at >= 0 ? line.id.slice(0, at) : line.id
}

function windowOf(row: GaPreviewRow): string {
  if (row.validFrom && row.validTo) return `${row.validFrom} – ${row.validTo}`
  return row.validFrom || row.validTo || ''
}

function originLabel(row: GaPreviewRow, t: Translate): string {
  if (row.origin === 'buy_resale') return t('grossanlass.materials.originBadge.buy_resale')
  if (row.origin === 'buy') return t('grossanlass.materials.originBadge.buy')
  if (row.origin === 'loan' || row.lifecycle === 'loan') {
    return t('grossanlass.materials.originBadge.loan')
  }
  return t('grossanlass.materials.lifecycle.reusable')
}

function positionLabel(row: GaPreviewRow, t: Translate): string {
  const source = (row.source || '').trim()
  if (source) return source
  if (row.origin === 'loan' || row.lifecycle === 'loan') {
    return t('grossanlass.materials.originBadge.loan')
  }
  return t('grossanlass.materials.lifecycle.reusable')
}

function chargeLine(row: GaPreviewRow, t: Translate): SandboxComboComponent {
  return {
    id: row.id,
    name: positionLabel(row, t),
    qty: row.total_stock,
    assignment: row.origin === 'loan' || row.lifecycle === 'loan' ? 'fixed' : 'pool',
    assignment_label: originLabel(row, t),
    serial: windowOf(row) || row.barcode || null,
  }
}

function partLines(row: GaPreviewRow, t: Translate): SandboxComboComponent[] {
  return (row.components ?? [])
    .filter((part) => part.name.trim())
    .map((part, index) => ({
      id: `${row.id}${PART_MARK}${index}`,
      name: part.name.trim(),
      qty: part.qty,
      assignment: 'fixed' as const,
      assignment_label: t('grossanlass.materials.expandPart'),
      serial: part.serial ?? null,
    }))
}

function expandLines(row: GaPreviewRow, t: Translate, includeCharge: boolean): SandboxComboComponent[] {
  const parts = partLines(row, t)
  if (includeCharge) return [chargeLine(row, t), ...parts]
  return parts
}

function groupKey(row: GaPreviewRow, commitment: GrossanlassCommitment | undefined): string {
  if (!commitment || commitment.family === 'vehicle') return `solo:${row.id}`
  return commitmentStemKey(commitment)
}

function mergeMembers(members: GaPreviewRow[], t: Translate): GaPreviewRow {
  const first = members[0]
  const sources = [...new Set(members.map((row) => (row.source || '').trim()).filter(Boolean))]
  const origins = [...new Set(members.map((row) => row.origin).filter(Boolean))]
  return {
    ...first,
    is_combo: true,
    total_stock: members.reduce((sum, row) => sum + row.total_stock, 0),
    issued_out: members.reduce((sum, row) => sum + row.issued_out, 0),
    available: members.reduce((sum, row) => sum + row.available, 0),
    source: sources.length === 1 ? sources[0] : t('grossanlass.materials.severalPartners'),
    origin: origins.length === 1 ? origins[0] : first.origin,
    components: members.flatMap((row) => expandLines(row, t, true)),
  }
}

/** Gleiche Artikel (Partner / Chargen / Teile) zu einer Zeile mit Aufklapp-Positionen. */
export function groupBestandPreviewRows(
  rows: GaPreviewRow[],
  commitments: GrossanlassCommitment[],
  t: Translate,
): GaPreviewRow[] {
  const byId = new Map(commitments.map((row) => [row.id, row]))
  const buckets = new Map<string, GaPreviewRow[]>()
  for (const row of rows) {
    const key = groupKey(row, byId.get(row.id))
    const list = buckets.get(key) ?? []
    list.push(row)
    buckets.set(key, list)
  }
  const result: GaPreviewRow[] = []
  for (const members of buckets.values()) {
    if (members.length > 1) {
      result.push(mergeMembers(members, t))
      continue
    }
    const row = members[0]
    const parts = expandLines(row, t, false)
    result.push(parts.length ? { ...row, is_combo: true, components: parts } : { ...row, components: [] })
  }
  return result
}
