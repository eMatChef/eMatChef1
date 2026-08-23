export type GaEinsatzStatus = 'planned' | 'issued' | 'returned'
export type GaEinsatzKind = 'unique' | 'quantity'
export type GaConflictKind = 'unique_overlap' | 'quantity_overbook'
export type GaEinsatzViewMode = 'object' | 'ressort'

export type GaPreviewEinsatz = {
  id: string
  objectId: string
  objectName: string
  kind: GaEinsatzKind
  qty: number
  stock: number
  fromIso: string
  toIso: string
  fromLabel: string
  toLabel: string
  ressort: string
  bauprojekt?: string
  status: GaEinsatzStatus
  who: string
  conflictId?: string
}

export type GaPreviewConflict = {
  id: string
  kind: GaConflictKind
  objectId: string
  objectName: string
  einsatzIds: string[]
  title: string
  text: string
}

export type GaEinsatzGroup = {
  key: string
  title: string
  subtitle?: string
  rows: GaPreviewEinsatz[]
}

type Translate = (key: string, values?: Record<string, string | number>) => string

/** Demo-Anlasswoche: Fr Aufbau · Sa Event · So Retour */
export const GA_EINSATZ_TIMELINE_START = '2027-07-16T06:00:00'
export const GA_EINSATZ_TIMELINE_END = '2027-07-19T18:00:00'

export const GA_EINSATZ_TIMELINE_DAYS = [
  { iso: '2027-07-16', key: 'dayFri' },
  { iso: '2027-07-17', key: 'daySat' },
  { iso: '2027-07-18', key: 'daySun' },
] as const

export function createGrossanlassEinsatzPreview(t: Translate): {
  einsaetze: GaPreviewEinsatz[]
  conflicts: GaPreviewConflict[]
} {
  const bau = t('grossanlass.materialUebersicht.sampleRessortBau')
  const technik = t('grossanlass.materialUebersicht.sampleRessortTechnik')
  const wasser = t('grossanlass.materialUebersicht.sampleRessortWasser')
  const verpflegung = t('grossanlass.materialUebersicht.sampleRessortVerpflegung')
  const sicherheit = t('grossanlass.materialUebersicht.sampleRessortSicherheit')
  const gator = t('grossanlass.materials.sampleGator')
  const geruest = t('grossanlass.materialUebersicht.sampleGeruest')
  const zelt = t('grossanlass.materialUebersicht.sampleZelt')
  const teleskop = t('grossanlass.materialUebersicht.sampleTeleskop')
  const kabel = t('grossanlass.materialUebersicht.sampleKabel')

  const einsaetze: GaPreviewEinsatz[] = [
    {
      id: 'gator-aufbau',
      objectId: 'gator',
      objectName: gator,
      kind: 'unique',
      qty: 1,
      stock: 1,
      fromIso: '2027-07-16T08:00:00',
      toIso: '2027-07-16T18:00:00',
      fromLabel: t('grossanlass.materialUebersicht.slotFriMorning'),
      toLabel: t('grossanlass.materialUebersicht.slotFriEvening'),
      ressort: bau,
      bauprojekt: t('grossanlass.materialUebersicht.sampleProjektBuehne'),
      status: 'planned',
      who: t('grossanlass.materialUebersicht.sampleWho1'),
    },
    {
      id: 'gator-sicherheit',
      objectId: 'gator',
      objectName: gator,
      kind: 'unique',
      qty: 1,
      stock: 1,
      fromIso: '2027-07-17T10:00:00',
      toIso: '2027-07-17T14:00:00',
      fromLabel: t('grossanlass.materialUebersicht.slotSatMorning'),
      toLabel: t('grossanlass.materialUebersicht.slotSatAfternoon'),
      ressort: sicherheit,
      status: 'planned',
      who: t('grossanlass.materialUebersicht.sampleWho3'),
      conflictId: 'gator-sa',
    },
    {
      id: 'gator-bau-sa',
      objectId: 'gator',
      objectName: gator,
      kind: 'unique',
      qty: 1,
      stock: 1,
      fromIso: '2027-07-17T10:00:00',
      toIso: '2027-07-17T14:00:00',
      fromLabel: t('grossanlass.materialUebersicht.slotSatMorning'),
      toLabel: t('grossanlass.materialUebersicht.slotSatAfternoon'),
      ressort: bau,
      bauprojekt: t('grossanlass.materialUebersicht.sampleProjektBuehne'),
      status: 'planned',
      who: t('grossanlass.materialUebersicht.sampleWho2'),
      conflictId: 'gator-sa',
    },
    {
      id: 'gator-retour',
      objectId: 'gator',
      objectName: gator,
      kind: 'unique',
      qty: 1,
      stock: 1,
      fromIso: '2027-07-18T08:00:00',
      toIso: '2027-07-18T12:00:00',
      fromLabel: t('grossanlass.materialUebersicht.slotSunMorning'),
      toLabel: t('grossanlass.materialUebersicht.slotSunNoon'),
      ressort: t('grossanlass.materialUebersicht.sampleRetourFirma'),
      status: 'planned',
      who: t('grossanlass.materialUebersicht.sampleWho3'),
    },
    {
      id: 'geruest-bau',
      objectId: 'geruest',
      objectName: geruest,
      kind: 'quantity',
      qty: 8,
      stock: 12,
      fromIso: '2027-07-16T08:00:00',
      toIso: '2027-07-18T16:00:00',
      fromLabel: t('grossanlass.materialUebersicht.slotFriMorning'),
      toLabel: t('grossanlass.materialUebersicht.slotSunAfternoon'),
      ressort: bau,
      bauprojekt: t('grossanlass.materialUebersicht.sampleProjektBuehne'),
      status: 'planned',
      who: t('grossanlass.materialUebersicht.sampleWho1'),
      conflictId: 'geruest-qty',
    },
    {
      id: 'geruest-technik',
      objectId: 'geruest',
      objectName: geruest,
      kind: 'quantity',
      qty: 6,
      stock: 12,
      fromIso: '2027-07-16T14:00:00',
      toIso: '2027-07-17T20:00:00',
      fromLabel: t('grossanlass.materialUebersicht.slotFriAfternoon'),
      toLabel: t('grossanlass.materialUebersicht.slotSatEvening'),
      ressort: technik,
      status: 'planned',
      who: t('grossanlass.materialUebersicht.sampleWho2'),
      conflictId: 'geruest-qty',
    },
    {
      id: 'zelt-verpflegung',
      objectId: 'zelt',
      objectName: zelt,
      kind: 'unique',
      qty: 1,
      stock: 1,
      fromIso: '2027-07-16T07:00:00',
      toIso: '2027-07-18T20:00:00',
      fromLabel: t('grossanlass.materialUebersicht.slotFriMorning'),
      toLabel: t('grossanlass.materialUebersicht.slotSunEvening'),
      ressort: verpflegung,
      status: 'issued',
      who: t('grossanlass.materialUebersicht.sampleWho1'),
    },
    {
      id: 'teleskop-bau',
      objectId: 'teleskop',
      objectName: teleskop,
      kind: 'unique',
      qty: 1,
      stock: 1,
      fromIso: '2027-07-16T09:00:00',
      toIso: '2027-07-17T18:00:00',
      fromLabel: t('grossanlass.materialUebersicht.slotFriMorning'),
      toLabel: t('grossanlass.materialUebersicht.slotSatEvening'),
      ressort: bau,
      bauprojekt: t('grossanlass.materialUebersicht.sampleProjektWasser'),
      status: 'issued',
      who: t('grossanlass.materialUebersicht.sampleWho2'),
    },
    {
      id: 'kabel-technik',
      objectId: 'kabel',
      objectName: kabel,
      kind: 'quantity',
      qty: 2,
      stock: 6,
      fromIso: '2027-07-17T08:00:00',
      toIso: '2027-07-18T12:00:00',
      fromLabel: t('grossanlass.materialUebersicht.slotSatMorning'),
      toLabel: t('grossanlass.materialUebersicht.slotSunNoon'),
      ressort: technik,
      status: 'planned',
      who: t('grossanlass.materialUebersicht.sampleWho3'),
    },
    {
      id: 'kabel-wasser',
      objectId: 'kabel',
      objectName: kabel,
      kind: 'quantity',
      qty: 1,
      stock: 6,
      fromIso: '2027-07-16T10:00:00',
      toIso: '2027-07-16T16:00:00',
      fromLabel: t('grossanlass.materialUebersicht.slotFriMorning'),
      toLabel: t('grossanlass.materialUebersicht.slotFriEvening'),
      ressort: wasser,
      status: 'returned',
      who: t('grossanlass.materialUebersicht.sampleWho1'),
    },
  ]

  const conflicts: GaPreviewConflict[] = [
    {
      id: 'gator-sa',
      kind: 'unique_overlap',
      objectId: 'gator',
      objectName: gator,
      einsatzIds: ['gator-sicherheit', 'gator-bau-sa'],
      title: t('grossanlass.materialUebersicht.conflictUniqueTitle', { name: gator }),
      text: t('grossanlass.materialUebersicht.conflictUniqueText', {
        name: gator,
        a: sicherheit,
        b: bau,
      }),
    },
    {
      id: 'geruest-qty',
      kind: 'quantity_overbook',
      objectId: 'geruest',
      objectName: geruest,
      einsatzIds: ['geruest-bau', 'geruest-technik'],
      title: t('grossanlass.materialUebersicht.conflictQtyTitle', { name: geruest }),
      text: t('grossanlass.materialUebersicht.conflictQtyText', {
        name: geruest,
        used: 14,
        stock: 12,
      }),
    },
  ]

  return { einsaetze, conflicts }
}

export function groupEinsaetze(
  rows: GaPreviewEinsatz[],
  mode: GaEinsatzViewMode,
): GaEinsatzGroup[] {
  const map = new Map<string, GaEinsatzGroup>()
  for (const row of rows) {
    const key = mode === 'object' ? row.objectId : `${row.ressort}::${row.bauprojekt || ''}`
    const existing = map.get(key)
    if (existing) {
      existing.rows.push(row)
      continue
    }
    map.set(key, {
      key,
      title: mode === 'object' ? row.objectName : row.ressort,
      subtitle: mode === 'object'
        ? undefined
        : row.bauprojekt,
      rows: [row],
    })
  }
  return [...map.values()]
}

function timelineMs(iso: string): number {
  return new Date(iso).getTime()
}

const TIMELINE_START_MS = timelineMs(GA_EINSATZ_TIMELINE_START)
const TIMELINE_SPAN_MS = timelineMs(GA_EINSATZ_TIMELINE_END) - TIMELINE_START_MS

export function einsatzBarStyle(row: GaPreviewEinsatz): { left: string; width: string } {
  const start = Math.max(0, timelineMs(row.fromIso) - TIMELINE_START_MS)
  const end = Math.min(TIMELINE_SPAN_MS, timelineMs(row.toIso) - TIMELINE_START_MS)
  const width = Math.max(end - start, TIMELINE_SPAN_MS * 0.04)
  return {
    left: `${(start / TIMELINE_SPAN_MS) * 100}%`,
    width: `${(width / TIMELINE_SPAN_MS) * 100}%`,
  }
}
