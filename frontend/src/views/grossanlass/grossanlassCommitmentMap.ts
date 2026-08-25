import type {
  GrossanlassCommitment,
  GrossanlassCommitmentItemDetails,
  GrossanlassCommitmentPart,
} from '@/api/grossanlassCommitments'
import type { GaMaterialsTabId, GaPreviewRow } from '@/views/grossanlass/grossanlassMaterialsPreviewData'
import {
  articleToMaterialRow,
  originToLifecycle,
  type GaParkServiceKind,
  type GaPreviewParkService,
  type GaZusageArticle,
} from '@/views/grossanlass/grossanlassZusagePreviewData'

type Translate = (key: string) => string

export function commitmentTabs(row: GrossanlassCommitment): GaMaterialsTabId[] {
  if (row.family === 'vehicle') {
    return ['fahrzeuge', 'uebersicht', row.origin === 'loan' ? 'leihweise' : 'eigen']
  }
  return [row.origin === 'loan' ? 'leihweise' : 'eigen', 'uebersicht']
}

export function commitmentQuantity(row: GrossanlassCommitment): number {
  return Math.max(1, Number(row.quantity) || 1)
}

export function commitmentDetails(row: GrossanlassCommitment): GrossanlassCommitmentItemDetails {
  return row.item_details && typeof row.item_details === 'object' ? row.item_details : {}
}

function mapServices(row: GrossanlassCommitment): GaPreviewParkService[] {
  return (row.services ?? []).map((service, index) => ({
    id: service.id || `${row.id}-svc-${index}`,
    kind: (service.kind as GaParkServiceKind) || 'other',
    fromIso: service.fromIso || '',
    toIso: service.toIso || '',
    who: service.who || '',
    label: service.label ?? undefined,
  }))
}

export function commitmentToArticle(row: GrossanlassCommitment): GaZusageArticle {
  const qty = commitmentQuantity(row)
  const wishFrom = row.wish_from || ''
  const wishTo = row.wish_to || ''
  return {
    id: row.id,
    name: row.name,
    barcode: row.barcode || '',
    family: row.family,
    origin: row.origin,
    source: row.source,
    plate: row.plate || undefined,
    presentFromIso: row.present_from || '',
    presentToIso: row.present_to || '',
    handoverFromIso: row.handover_from || '',
    handoverToIso: row.handover_to || '',
    returnFromIso: row.return_from || '',
    returnToIso: row.return_to || '',
    released: row.released,
    tabs: commitmentTabs(row),
    categoryId: row.family === 'vehicle' ? 'fahrzeuge' : 'infra',
    kind: qty > 1 ? 'quantity' : 'unique',
    stock: qty,
    stayMode: 'return',
    services: mapServices(row),
    feinWish: wishFrom && wishTo
      ? { label: row.wish_label || '', ressort: '', fromIso: wishFrom, toIso: wishTo }
      : undefined,
  }
}

function partLines(parts: GrossanlassCommitmentPart[] | undefined, t: Translate) {
  return (parts ?? [])
    .filter((part) => part.name.trim())
    .map((part, index) => ({
      id: `part-${index}`,
      name: part.name.trim(),
      qty: Math.max(1, Number(part.qty) || 1),
      assignment: 'fixed' as const,
      assignment_label: t('grossanlass.materials.zusage.sectionParts'),
      serial: null,
    }))
}

export function commitmentToPreviewRow(
  row: GrossanlassCommitment,
  t: Translate,
  locale = 'de-CH',
): GaPreviewRow {
  const article = commitmentToArticle(row)
  const details = commitmentDetails(row)
  const parts = partLines(details.parts, t)
  const mapped = articleToMaterialRow(article, locale)
  const packLabel = details.pack_size && details.pack_unit
    ? `${details.pack_size} ${details.pack_unit}`
    : details.pack_unit
  return {
    ...mapped,
    is_combo: parts.length > 0,
    pack_unit: packLabel || undefined,
    location: row.origin === 'loan' ? undefined : t('grossanlass.materials.locZentrallager'),
    category_name: row.family === 'vehicle'
      ? t('grossanlass.materials.catFahrzeug')
      : t('grossanlass.materials.catInfra'),
    vehicleStatus: row.released
      ? t('grossanlass.materials.zusage.releasedShort')
      : t('grossanlass.materials.zusage.heldShort'),
    lifecycle: originToLifecycle(row.origin),
    components: parts,
  }
}
