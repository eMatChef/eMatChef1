export type GaEinsatzStatus = 'planned' | 'pending_approval' | 'issued' | 'returned'
export type GaEinsatzBarRole = 'einsatz' | 'handover' | 'giveback' | 'service' | 'unreleased'
export type GaEinsatzRingId = 'fleet' | 'tools' | 'consumable'
export type GaEinsatzKind = 'unique' | 'quantity'
export type GaConflictKind = 'unique_overlap' | 'quantity_overbook'
export type GaEinsatzViewMode = 'object' | 'ressort'
export type GaCalendarScale = 'month' | 'week' | 'day'

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
  barRole?: GaEinsatzBarRole
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

export type GaEinsatzFamily = 'vehicle' | 'material'
export type GaEinsatzStayMode = 'return' | 'stay'

export type GaEinsatzResource = {
  id: string
  name: string
  family: GaEinsatzFamily
  stayMode: GaEinsatzStayMode
  categoryId: string
  kind: GaEinsatzKind
  stock: number
  presentFromIso?: string
  presentToIso?: string
  released?: boolean
}

export type GaEinsatzCategoryBlock = {
  id: string
  ringId: GaEinsatzRingId
  ringLabel: string
  label: string
  resources: Array<GaEinsatzResource & {
    bookings: GaPreviewEinsatz[]
    lanes: number
    laneOf: Record<string, number>
  }>
}

export type GaEinsatzRingBlock = {
  id: GaEinsatzRingId
  label: string
  blocks: GaEinsatzCategoryBlock[]
}

export type GaCalendarColumn = {
  key: string
  label: string
  sub: string
  weekend: boolean
  startMs: number
  endMs: number
}

type Translate = (key: string, values?: Record<string, string | number>) => string

/** Demo-Anlasswoche: Fr Aufbau · Sa Event · So Retour */
export const GA_EINSATZ_ANCHOR_ISO = '2027-07-16'
export const GA_EINSATZ_TIMELINE_START = '2027-07-16T06:00:00'
export const GA_EINSATZ_TIMELINE_END = '2027-07-19T18:00:00'
export const GA_EINSATZ_DAY_HOUR_FROM = 0
export const GA_EINSATZ_DAY_HOUR_TO = 24

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
  const akkuschrauber = t('grossanlass.materialUebersicht.sampleAkkuschrauber')
  const folie = t('grossanlass.materialUebersicht.sampleFolie')

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
      status: 'pending_approval',
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
      status: 'pending_approval',
      who: t('grossanlass.materialUebersicht.sampleWho2'),
      conflictId: 'gator-sa',
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
      status: 'pending_approval',
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
      status: 'pending_approval',
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
    {
      id: 'akku-bau',
      objectId: 'akkuschrauber',
      objectName: akkuschrauber,
      kind: 'unique',
      qty: 1,
      stock: 4,
      fromIso: '2027-07-16T08:00:00',
      toIso: '2027-07-16T16:00:00',
      fromLabel: t('grossanlass.materialUebersicht.slotFriMorning'),
      toLabel: t('grossanlass.materialUebersicht.slotFriEvening'),
      ressort: bau,
      bauprojekt: t('grossanlass.materialUebersicht.sampleProjektBuehne'),
      status: 'returned',
      who: t('grossanlass.materialUebersicht.sampleWho1'),
    },
    {
      id: 'akku-technik',
      objectId: 'akkuschrauber',
      objectName: akkuschrauber,
      kind: 'unique',
      qty: 1,
      stock: 4,
      fromIso: '2027-07-17T09:00:00',
      toIso: '2027-07-17T12:00:00',
      fromLabel: t('grossanlass.materialUebersicht.slotSatMorning'),
      toLabel: t('grossanlass.materialUebersicht.slotSatNoon'),
      ressort: technik,
      status: 'planned',
      who: t('grossanlass.materialUebersicht.sampleWho3'),
    },
    {
      id: 'folie-bau',
      objectId: 'folie',
      objectName: folie,
      kind: 'quantity',
      qty: 180,
      stock: 200,
      fromIso: '2027-07-16T08:00:00',
      toIso: '2027-07-18T20:00:00',
      fromLabel: t('grossanlass.materialUebersicht.slotFriMorning'),
      toLabel: t('grossanlass.materialUebersicht.slotSunEvening'),
      ressort: bau,
      bauprojekt: t('grossanlass.materialUebersicht.sampleProjektBuehne'),
      status: 'issued',
      who: t('grossanlass.materialUebersicht.sampleWho2'),
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

export type GaPreviewWishTemplate = {
  id: string
  label: string
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
  who: string
  hasConflict: boolean
}

export function createGrossanlassWishBookingTemplates(t: Translate): GaPreviewWishTemplate[] {
  const bau = t('grossanlass.materialUebersicht.sampleRessortBau')
  const technik = t('grossanlass.materialUebersicht.sampleRessortTechnik')
  const sicherheit = t('grossanlass.materialUebersicht.sampleRessortSicherheit')
  return [
    {
      id: 'wish-gator',
      label: t('grossanlass.materialUebersicht.wishGator'),
      objectId: 'gator',
      objectName: t('grossanlass.materials.sampleGator'),
      kind: 'unique',
      qty: 1,
      stock: 1,
      fromIso: '2027-07-17T10:00:00',
      toIso: '2027-07-17T14:00:00',
      fromLabel: t('grossanlass.materialUebersicht.slotSatMorning'),
      toLabel: t('grossanlass.materialUebersicht.slotSatAfternoon'),
      ressort: sicherheit,
      who: t('grossanlass.materialUebersicht.sampleWho3'),
      hasConflict: true,
    },
    {
      id: 'wish-drill',
      label: t('grossanlass.materialUebersicht.wishDrill'),
      objectId: 'akkuschrauber',
      objectName: t('grossanlass.materialUebersicht.sampleAkkuschrauber'),
      kind: 'unique',
      qty: 2,
      stock: 4,
      fromIso: '2027-07-16T08:00:00',
      toIso: '2027-07-16T18:00:00',
      fromLabel: t('grossanlass.materialUebersicht.slotFriMorning'),
      toLabel: t('grossanlass.materialUebersicht.slotFriEvening'),
      ressort: bau,
      bauprojekt: t('grossanlass.materialUebersicht.sampleProjektBuehne'),
      who: t('grossanlass.materialUebersicht.sampleWho1'),
      hasConflict: false,
    },
    {
      id: 'wish-trailer',
      label: t('grossanlass.materialUebersicht.wishTrailer'),
      objectId: 'anhaenger',
      objectName: t('grossanlass.materials.sampleTrailer'),
      kind: 'unique',
      qty: 1,
      stock: 1,
      fromIso: '2027-07-18T08:00:00',
      toIso: '2027-07-18T12:00:00',
      fromLabel: t('grossanlass.materialUebersicht.slotSunMorning'),
      toLabel: t('grossanlass.materialUebersicht.slotSunNoon'),
      ressort: technik,
      who: t('grossanlass.materialUebersicht.sampleWho2'),
      hasConflict: false,
    },
  ]
}

export function createGrossanlassFreePickTemplates(t: Translate): GaPreviewWishTemplate[] {
  const bau = t('grossanlass.materialUebersicht.sampleRessortBau')
  const who = t('grossanlass.materialUebersicht.sampleWho1')
  return createGrossanlassEinsatzResources(t).map((resource) => ({
    id: `pick-${resource.id}`,
    label: resource.name,
    objectId: resource.id,
    objectName: resource.name,
    kind: resource.kind,
    qty: resource.kind === 'quantity' ? Math.min(2, resource.stock) : 1,
    stock: resource.stock,
    fromIso: '2027-07-16T08:00:00',
    toIso: '2027-07-16T18:00:00',
    fromLabel: t('grossanlass.materialUebersicht.slotFriMorning'),
    toLabel: t('grossanlass.materialUebersicht.slotFriEvening'),
    ressort: bau,
    who,
    hasConflict: false,
  }))
}

export function wishTemplateToEinsatz(wish: GaPreviewWishTemplate, id: string): GaPreviewEinsatz {
  return {
    id,
    objectId: wish.objectId,
    objectName: wish.objectName,
    kind: wish.kind,
    qty: wish.qty,
    stock: wish.stock,
    fromIso: wish.fromIso,
    toIso: wish.toIso,
    fromLabel: wish.fromLabel,
    toLabel: wish.toLabel,
    ressort: wish.ressort,
    bauprojekt: wish.bauprojekt,
    status: wish.hasConflict ? 'pending_approval' : 'planned',
    who: wish.who,
    conflictId: wish.hasConflict ? 'preview-from-wish' : undefined,
  }
}

export function resourceToPickTemplate(
  resource: GaEinsatzResource,
  t: Translate,
): GaPreviewWishTemplate {
  const bau = t('grossanlass.materialUebersicht.sampleRessortBau')
  return {
    id: `pick-${resource.id}`,
    label: resource.name,
    objectId: resource.id,
    objectName: resource.name,
    kind: resource.kind,
    qty: resource.kind === 'quantity' ? Math.min(2, resource.stock) : 1,
    stock: resource.stock,
    fromIso: '2027-07-16T08:00:00',
    toIso: '2027-07-16T18:00:00',
    fromLabel: t('grossanlass.materialUebersicht.slotFriMorning'),
    toLabel: t('grossanlass.materialUebersicht.slotFriEvening'),
    ressort: bau,
    who: t('grossanlass.materialUebersicht.sampleWho1'),
    hasConflict: false,
  }
}

export function createGrossanlassEinsatzResources(t: Translate): GaEinsatzResource[] {
  return [
    {
      id: 'gator',
      name: t('grossanlass.materials.sampleGator'),
      family: 'vehicle',
      stayMode: 'return',
      categoryId: 'fahrzeuge',
      kind: 'unique',
      stock: 1,
    },
    {
      id: 'teleskop',
      name: t('grossanlass.materialUebersicht.sampleTeleskop'),
      family: 'vehicle',
      stayMode: 'return',
      categoryId: 'fahrzeuge',
      kind: 'unique',
      stock: 1,
    },
    {
      id: 'anhaenger',
      name: t('grossanlass.materials.sampleTrailer'),
      family: 'vehicle',
      stayMode: 'return',
      categoryId: 'fahrzeuge',
      kind: 'unique',
      stock: 1,
    },
    {
      id: 'akkuschrauber',
      name: t('grossanlass.materialUebersicht.sampleAkkuschrauber'),
      family: 'material',
      stayMode: 'return',
      categoryId: 'werkzeug',
      kind: 'unique',
      stock: 4,
    },
    {
      id: 'kabel',
      name: t('grossanlass.materialUebersicht.sampleKabel'),
      family: 'material',
      stayMode: 'return',
      categoryId: 'elektro',
      kind: 'quantity',
      stock: 6,
    },
    {
      id: 'geruest',
      name: t('grossanlass.materialUebersicht.sampleGeruest'),
      family: 'material',
      stayMode: 'stay',
      categoryId: 'infra',
      kind: 'quantity',
      stock: 12,
    },
    {
      id: 'zelt',
      name: t('grossanlass.materialUebersicht.sampleZelt'),
      family: 'material',
      stayMode: 'stay',
      categoryId: 'infra',
      kind: 'unique',
      stock: 1,
    },
    {
      id: 'folie',
      name: t('grossanlass.materialUebersicht.sampleFolie'),
      family: 'material',
      stayMode: 'stay',
      categoryId: 'verbrauch',
      kind: 'quantity',
      stock: 200,
    },
  ]
}

export function categoryLabel(categoryId: string, t: Translate): string {
  return t(`grossanlass.materialUebersicht.cat.${categoryId}`)
}

export function resourceRingId(resource: GaEinsatzResource): GaEinsatzRingId {
  if (resource.family === 'vehicle') return 'fleet'
  if (resource.categoryId === 'werkzeug' || resource.categoryId === 'elektro') return 'tools'
  return 'consumable'
}

export function resourceRingLabel(ringId: GaEinsatzRingId, t: Translate): string {
  if (ringId === 'fleet') return t('grossanlass.materialUebersicht.ringFleet')
  if (ringId === 'tools') return t('grossanlass.materialUebersicht.ringTools')
  return t('grossanlass.materialUebersicht.ringConsumable')
}

function resourceBlockKey(resource: GaEinsatzResource): string {
  return `${resourceRingId(resource)}:${resource.categoryId}`
}

function resourceBlockLabel(resource: GaEinsatzResource, t: Translate): string {
  return categoryLabel(resource.categoryId, t)
}

function resourceBlockOrder(resource: GaEinsatzResource): number {
  const ring = { fleet: 0, tools: 10, consumable: 20 }[resourceRingId(resource)]
  const category = { fahrzeuge: 0, werkzeug: 0, elektro: 1, verbrauch: 0, infra: 1 }[resource.categoryId] ?? 9
  return ring + category
}

export function groupEinsatzBlocksByRing(blocks: GaEinsatzCategoryBlock[]): GaEinsatzRingBlock[] {
  const rings: GaEinsatzRingBlock[] = []
  for (const block of blocks) {
    const last = rings[rings.length - 1]
    if (last && last.id === block.ringId) {
      last.blocks.push(block)
      continue
    }
    rings.push({ id: block.ringId, label: block.ringLabel, blocks: [block] })
  }
  return rings
}

export function buildEinsatzCalendarBlocks(
  resources: GaEinsatzResource[],
  bookings: GaPreviewEinsatz[],
  t: Translate,
  onlyWithBookings = false,
): GaEinsatzCategoryBlock[] {
  const byObject = new Map<string, GaPreviewEinsatz[]>()
  for (const row of bookings) {
    const list = byObject.get(row.objectId) ?? []
    list.push(row)
    byObject.set(row.objectId, list)
  }
  const grouped = new Map<string, GaEinsatzCategoryBlock & { order: number }>()
  for (const resource of resources) {
    const resourceBookings = byObject.get(resource.id) ?? []
    if (onlyWithBookings && resourceBookings.length === 0) continue
    const laneOf = packBookingLanes(resourceBookings)
    const key = resourceBlockKey(resource)
    const ringId = resourceRingId(resource)
    const block = grouped.get(key) ?? {
      id: key,
      ringId,
      ringLabel: resourceRingLabel(ringId, t),
      label: resourceBlockLabel(resource, t),
      resources: [],
      order: resourceBlockOrder(resource),
    }
    block.resources.push({
      ...resource,
      bookings: resourceBookings,
      lanes: Math.max(1, Object.keys(laneOf).length ? Math.max(...Object.values(laneOf)) + 1 : 1),
      laneOf,
    })
    grouped.set(key, block)
  }
  return [...grouped.values()]
    .sort((a, b) => a.order - b.order || a.label.localeCompare(b.label, 'de'))
    .map(({ order: _order, ...block }) => block)
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
  return parseLocalDate(iso).getTime()
}

export function parseLocalDate(iso: string): Date {
  const [datePart, rest = '00:00:00'] = iso.split('T')
  const timePart = rest.replace(/[Zz]$/, '').replace(/[+-]\d{2}:\d{2}$/, '')
  const [year, month, day] = datePart.split('-').map(Number)
  const [hour = 0, minute = 0, second = 0] = timePart.split(':').map(Number)
  return new Date(year, month - 1, day, hour, minute, Number.isFinite(second) ? second : 0)
}

function atHour(date: Date, hour: number): Date {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate(), hour, 0, 0, 0)
}

function startOfWeekMonday(date: Date): Date {
  const weekday = date.getDay()
  const diff = weekday === 0 ? -6 : 1 - weekday
  return atHour(new Date(date.getFullYear(), date.getMonth(), date.getDate() + diff), 0)
}

export function shiftCalendarAnchor(scale: GaCalendarScale, anchor: Date, direction: -1 | 1): Date {
  const next = new Date(anchor)
  if (scale === 'day') next.setDate(next.getDate() + direction)
  else if (scale === 'week') next.setDate(next.getDate() + direction * 7)
  else next.setMonth(next.getMonth() + direction)
  return next
}

export function calendarWindow(
  scale: GaCalendarScale,
  anchor: Date,
): { start: Date; end: Date } {
  if (scale === 'day') {
    return {
      start: atHour(anchor, GA_EINSATZ_DAY_HOUR_FROM),
      end: atHour(anchor, GA_EINSATZ_DAY_HOUR_TO),
    }
  }
  if (scale === 'week') {
    const start = startOfWeekMonday(anchor)
    const end = new Date(start)
    end.setDate(end.getDate() + 7)
    return { start, end }
  }
  const start = new Date(anchor.getFullYear(), anchor.getMonth(), 1)
  const end = new Date(anchor.getFullYear(), anchor.getMonth() + 1, 1)
  return { start, end }
}

export function calendarColumns(
  scale: GaCalendarScale,
  start: Date,
  end: Date,
  locale = 'de-CH',
): GaCalendarColumn[] {
  const columns: GaCalendarColumn[] = []
  if (scale === 'day') {
    for (let hour = GA_EINSATZ_DAY_HOUR_FROM; hour < GA_EINSATZ_DAY_HOUR_TO; hour += 1) {
      const colStart = atHour(start, hour)
      const colEnd = atHour(start, hour + 1)
      columns.push({
        key: `${hour}`,
        label: `${String(hour).padStart(2, '0')}`,
        sub: '',
        weekend: false,
        startMs: colStart.getTime(),
        endMs: colEnd.getTime(),
      })
    }
    return columns
  }
  const cursor = new Date(start)
  while (cursor < end) {
    const colStart = new Date(cursor)
    const colEnd = new Date(cursor)
    colEnd.setDate(colEnd.getDate() + 1)
    const weekday = colStart.getDay()
    columns.push({
      key: `${colStart.getFullYear()}-${colStart.getMonth()}-${colStart.getDate()}`,
      label: String(colStart.getDate()),
      sub: colStart.toLocaleDateString(locale, { weekday: 'short' }).replace(/\.$/, ''),
      weekend: weekday === 0 || weekday === 6,
      startMs: colStart.getTime(),
      endMs: colEnd.getTime(),
    })
    cursor.setDate(cursor.getDate() + 1)
  }
  return columns
}

export function formatCalendarTitle(scale: GaCalendarScale, start: Date, end: Date, locale: string): string {
  const last = new Date(end.getTime() - 1)
  if (scale === 'day') {
    return start.toLocaleDateString(locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
  }
  if (scale === 'month') {
    return start.toLocaleDateString(locale, { month: 'long', year: 'numeric' })
  }
  const sameMonth = start.getMonth() === last.getMonth()
  const from = start.toLocaleDateString(locale, { day: 'numeric', month: sameMonth ? undefined : 'short' })
  const to = last.toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' })
  return `${from} – ${to}`
}

export function packBookingLanes(bookings: GaPreviewEinsatz[]): Record<string, number> {
  const sorted = [...bookings].sort((a, b) => timelineMs(a.fromIso) - timelineMs(b.fromIso))
  const laneEnds: number[] = []
  const laneOf: Record<string, number> = {}
  for (const booking of sorted) {
    const start = timelineMs(booking.fromIso)
    const end = timelineMs(booking.toIso)
    let lane = laneEnds.findIndex((laneEnd) => laneEnd <= start)
    if (lane < 0) {
      lane = laneEnds.length
      laneEnds.push(end)
    } else {
      laneEnds[lane] = end
    }
    laneOf[booking.id] = lane
  }
  return laneOf
}

export function barStyleInWindow(
  row: GaPreviewEinsatz,
  windowStart: Date,
  windowEnd: Date,
  scale: GaCalendarScale = 'week',
): { left: string; width: string } | null {
  const startMs = windowStart.getTime()
  const spanMs = windowEnd.getTime() - startMs
  if (spanMs <= 0) return null
  let from = timelineMs(row.fromIso)
  let to = timelineMs(row.toIso)
  if (scale === 'month') {
    const fromDate = parseLocalDate(row.fromIso)
    const toDate = parseLocalDate(row.toIso)
    from = atHour(fromDate, 0).getTime()
    to = new Date(toDate.getFullYear(), toDate.getMonth(), toDate.getDate() + 1).getTime()
  }
  from = Math.max(startMs, from)
  to = Math.min(windowEnd.getTime(), to)
  if (to <= from) return null
  return {
    left: `${((from - startMs) / spanMs) * 100}%`,
    width: `${Math.max(((to - from) / spanMs) * 100, 1.2)}%`,
  }
}

export function isoRangesOverlap(aFrom: string, aTo: string, bFrom: string, bTo: string): boolean {
  return timelineMs(aFrom) < timelineMs(bTo) && timelineMs(bFrom) < timelineMs(aTo)
}

export function overlappingObjectBookings(
  rows: GaPreviewEinsatz[],
  objectId: string,
  fromIso: string,
  toIso: string,
): GaPreviewEinsatz[] {
  return rows.filter(
    (row) => row.objectId === objectId && isoRangesOverlap(row.fromIso, row.toIso, fromIso, toIso),
  )
}

export function isIssuedSlotLocked(
  rows: GaPreviewEinsatz[],
  objectId: string,
  fromIso: string,
  toIso: string,
): boolean {
  return overlappingObjectBookings(rows, objectId, fromIso, toIso).some((row) => row.status === 'issued')
}

export function einsatzBarRole(booking: GaPreviewEinsatz): GaEinsatzBarRole {
  return booking.barRole ?? 'einsatz'
}

export function einsatzBarKind(booking: GaPreviewEinsatz): GaEinsatzStatus | GaEinsatzBarRole {
  const role = einsatzBarRole(booking)
  if (role !== 'einsatz') return role
  if (booking.status === 'issued' || booking.status === 'returned') return booking.status
  if (booking.status === 'pending_approval' || booking.conflictId) return 'pending_approval'
  return 'planned'
}

export function isUnreleasedSlot(
  rows: GaPreviewEinsatz[],
  objectId: string,
  fromIso: string,
  toIso: string,
): boolean {
  return overlappingObjectBookings(rows, objectId, fromIso, toIso).some(
    (row) => (row.barRole ?? 'einsatz') === 'unreleased',
  )
}

export function isOutsidePresentWindow(
  resource: { presentFromIso?: string; presentToIso?: string } | undefined,
  fromIso: string,
  toIso: string,
): boolean {
  if (!resource?.presentFromIso || !resource.presentToIso) return false
  return timelineMs(fromIso) < timelineMs(resource.presentFromIso)
    || timelineMs(toIso) > timelineMs(resource.presentToIso)
}

export type GaPresenceShade = {
  key: string
  kind: 'away' | 'unreleased'
  fromIso: string
  toIso: string
}

function toLocalIso(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
}

export function resourcePresenceShades(
  resource: GaEinsatzResource,
  windowStart: Date,
  windowEnd: Date,
): GaPresenceShade[] {
  const shades: GaPresenceShade[] = []
  if (resource.presentFromIso && resource.presentToIso) {
    const presentFrom = parseLocalDate(resource.presentFromIso)
    const presentTo = parseLocalDate(resource.presentToIso)
    if (presentFrom > windowStart) {
      shades.push({
        key: `${resource.id}-away-before`,
        kind: 'away',
        fromIso: toLocalIso(windowStart),
        toIso: resource.presentFromIso,
      })
    }
    if (presentTo < windowEnd) {
      shades.push({
        key: `${resource.id}-away-after`,
        kind: 'away',
        fromIso: resource.presentToIso,
        toIso: toLocalIso(windowEnd),
      })
    }
    if (resource.released === false) {
      shades.push({
        key: `${resource.id}-unreleased`,
        kind: 'unreleased',
        fromIso: resource.presentFromIso,
        toIso: resource.presentToIso,
      })
    }
  }
  return shades
}

export function isSlotConflict(
  rows: GaPreviewEinsatz[],
  draft: { objectId: string; kind: GaEinsatzKind; qty: number; stock: number },
  fromIso: string,
  toIso: string,
): boolean {
  const hits = overlappingObjectBookings(rows, draft.objectId, fromIso, toIso)
  if (!hits.length) return false
  if (draft.kind === 'unique') return true
  const used = hits.reduce((sum, row) => sum + row.qty, 0) + draft.qty
  return used > draft.stock
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
