import type { GrossanlassRoundFormField } from '@/api/grossanlassRoundForm'
import { sortFormFields } from '@/api/grossanlassRoundForm'
import type { GrossanlassWishKind, GrossanlassWishLine } from '@/api/grossanlassWishes'
import { formatActivityDateRangeDe } from '@/utils/activityDateIso'
import type { DepartmentCalendarPeriod } from '@/api/calendarPeriods'
import { resolveWishNeedPeriod } from '@/utils/grossanlassWishPeriod'

export interface GrossanlassWishTableColumn {
  id: string
  label: string
  field: GrossanlassRoundFormField | null
}

function parseIsoDate(iso: string | null | undefined): Date | null {
  if (!iso) return null
  const d = new Date(iso)
  return Number.isFinite(d.getTime()) ? d : null
}

function formatDateRangeFromIso(from: string | null | undefined, to: string | null | undefined): string {
  const start = parseIsoDate(from)
  const end = parseIsoDate(to)
  if (!start || !end) return '–'
  return formatActivityDateRangeDe([start, end])
}

function formatDateTimeDe(iso: string | null | undefined): string {
  const d = parseIsoDate(iso)
  if (!d) return '–'
  return d.toLocaleString('de-CH', { dateStyle: 'short', timeStyle: 'short' })
}

export function buildGrossanlassWishTableColumns(
  fields: GrossanlassRoundFormField[],
): GrossanlassWishTableColumn[] {
  return sortFormFields(fields)
    .filter((f) => f.enabled && (f.role === 'input' || f.role === 'meta'))
    .map((field) => ({
      id: field.id,
      label: field.label,
      field,
    }))
}

export function formatGrossanlassWishCellValue(
  wish: GrossanlassWishLine,
  field: GrossanlassRoundFormField,
  labels: {
    wishKind: (kind: GrossanlassWishKind) => string
    calendarPeriods?: DepartmentCalendarPeriod[]
  },
): string {
  const need = resolveWishNeedPeriod(wish, labels.calendarPeriods || [])
  const needRange = formatDateRangeFromIso(need?.from, need?.to)
  const systemKey = field.system_key
  if (systemKey === 'bauprojekt' || systemKey === 'ressort_wahl' || systemKey === 'ressort') {
    return wish.group_name || '–'
  }
  if (systemKey === 'wish_kind') {
    return labels.wishKind(wish.wish_kind)
  }
  if (systemKey === 'label') {
    return wish.label || '–'
  }
  if (systemKey === 'quantity') {
    return String(wish.quantity ?? '–')
  }
  if (systemKey === 'location') {
    return wish.location || '–'
  }
  if (systemKey === 'period') {
    return needRange
  }
  if (systemKey === 'notes') {
    return wish.notes || '–'
  }
  if (systemKey === 'submitter') {
    return wish.created_by_name || '–'
  }
  if (systemKey === 'created_at') {
    return formatDateTimeDe(wish.created_at)
  }
  if (systemKey === 'updated_at') {
    return formatDateTimeDe(wish.updated_at)
  }

  const raw = wish.custom_values?.[field.id]
  if (field.custom_type === 'date_range') {
    if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
      const range = raw as { from?: string; to?: string }
      const stored = formatDateRangeFromIso(range.from, range.to)
      if (stored !== '–') return stored
    }
    return needRange
  }

  if (raw === null || raw === undefined || raw === '') {
    return '–'
  }

  if (field.custom_type === 'select' && Array.isArray(raw)) {
    const choices = raw.length > 0 ? raw.map(String).join(', ') : ''
    if (choices && needRange && needRange !== '–') return `${choices} · ${needRange}`
    return choices || needRange || '–'
  }

  return String(raw)
}
