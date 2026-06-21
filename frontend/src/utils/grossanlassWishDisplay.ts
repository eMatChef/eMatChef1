import type { GrossanlassRoundFormField } from '@/api/grossanlassRoundForm'
import { sortFormFields } from '@/api/grossanlassRoundForm'
import type { GrossanlassWishKind, GrossanlassWishLine } from '@/api/grossanlassWishes'
import { formatActivityDateDe, formatActivityDateRangeDe } from '@/utils/activityDateIso'

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
  },
): string {
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
    return formatDateRangeFromIso(wish.valid_from, wish.valid_to)
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
  if (raw === null || raw === undefined || raw === '') {
    return '–'
  }

  if (field.custom_type === 'date_range') {
    if (typeof raw === 'object' && raw !== null && !Array.isArray(raw)) {
      const range = raw as { from?: string; to?: string }
      return formatDateRangeFromIso(range.from, range.to)
    }
    return '–'
  }

  if (field.custom_type === 'select' && Array.isArray(raw)) {
    return raw.length > 0 ? raw.join(', ') : '–'
  }

  return String(raw)
}
