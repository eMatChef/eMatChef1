import type { GaLifecycle, GaMaterialsTabId, GaPreviewRow } from '@/views/grossanlass/grossanlassMaterialsPreviewData'
import {
  parseLocalDate,
  type GaEinsatzBarRole,
  type GaEinsatzFamily,
  type GaEinsatzKind,
  type GaEinsatzResource,
  type GaEinsatzStayMode,
  type GaPreviewEinsatz,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'

export type GaZusageOrigin = 'loan' | 'buy' | 'buy_resale'
export type GaParkServiceKind = 'clean' | 'grease' | 'other'

export type GaPreviewParkService = {
  id: string
  kind: GaParkServiceKind
  fromIso: string
  toIso: string
  who: string
  label?: string
}

export type GaFeinWishPreview = {
  label: string
  ressort: string
  fromIso: string
  toIso: string
}

export type GaZusageArticle = {
  id: string
  name: string
  barcode: string
  family: GaEinsatzFamily
  origin: GaZusageOrigin
  source: string
  plate?: string
  presentFromIso: string
  presentToIso: string
  handoverFromIso: string
  handoverToIso: string
  returnFromIso: string
  returnToIso: string
  released: boolean
  tabs: GaMaterialsTabId[]
  categoryId: string
  kind: GaEinsatzKind
  stock: number
  stayMode: GaEinsatzStayMode
  services: GaPreviewParkService[]
  feinWish?: GaFeinWishPreview
  fromLineId?: string
  sessionCreated?: boolean
}

type Translate = (key: string, values?: Record<string, string | number>) => string

export function combineIso(date: string, time: string): string {
  const clock = time.length === 5 ? `${time}:00` : time
  return `${date}T${clock}`
}

export function isoDatePart(iso: string): string {
  return iso.slice(0, 10)
}

export function isoTimePart(iso: string): string {
  return iso.slice(11, 16)
}

export function formatGaIsoLabel(iso: string, locale = 'de-CH'): string {
  const date = parseLocalDate(iso)
  return date.toLocaleString(locale, {
    weekday: 'short',
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function formatGaDateLabel(iso: string, locale = 'de-CH'): string {
  return parseLocalDate(iso).toLocaleDateString(locale, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

export function originToLifecycle(origin: GaZusageOrigin): GaLifecycle {
  if (origin === 'loan') return 'loan'
  if (origin === 'buy_resale') return 'buy_resale'
  return 'reusable'
}

export function parkServiceLabel(kind: GaParkServiceKind, t: Translate, custom?: string): string {
  if (kind === 'other' && custom) return custom
  return t(`grossanlass.materials.zusage.service.${kind}`)
}

export function createGrossanlassZusageArticles(t: Translate): GaZusageArticle[] {
  const meier = t('grossanlass.materials.sourceMeier')
  const winterthur = t('grossanlass.materials.sourceWinterthur')
  const bau = t('grossanlass.materialUebersicht.sampleRessortBau')
  const sicherheit = t('grossanlass.materialUebersicht.sampleRessortSicherheit')
  const mw = t('grossanlass.materialUebersicht.sampleWho3')

  return [
    {
      id: 'gator',
      name: t('grossanlass.materials.sampleGator'),
      barcode: 'GATOR-ZH-441',
      family: 'vehicle',
      origin: 'loan',
      source: meier,
      plate: 'ZH 441 207',
      presentFromIso: '2027-07-16T07:00:00',
      presentToIso: '2027-07-18T12:00:00',
      handoverFromIso: '2027-07-16T07:00:00',
      handoverToIso: '2027-07-16T08:00:00',
      returnFromIso: '2027-07-18T08:00:00',
      returnToIso: '2027-07-18T12:00:00',
      released: true,
      tabs: ['fahrzeuge', 'uebersicht', 'leihweise'],
      categoryId: 'fahrzeuge',
      kind: 'unique',
      stock: 1,
      stayMode: 'return',
      services: [
        {
          id: 'gator-grease',
          kind: 'grease',
          fromIso: '2027-07-16T18:00:00',
          toIso: '2027-07-16T19:00:00',
          who: mw,
        },
        {
          id: 'gator-clean',
          kind: 'clean',
          fromIso: '2027-07-18T06:00:00',
          toIso: '2027-07-18T08:00:00',
          who: mw,
        },
      ],
      feinWish: {
        label: t('grossanlass.planung.feinPartner.wishGatorWide'),
        ressort: sicherheit,
        fromIso: '2027-07-12T08:00:00',
        toIso: '2027-07-19T18:00:00',
      },
    },
    {
      id: 'zelt',
      name: t('grossanlass.materialUebersicht.sampleZelt'),
      barcode: 'ZELT-10X20-LEIH',
      family: 'material',
      origin: 'loan',
      source: winterthur,
      presentFromIso: '2027-07-16T06:00:00',
      presentToIso: '2027-07-18T21:00:00',
      handoverFromIso: '2027-07-16T06:00:00',
      handoverToIso: '2027-07-16T07:00:00',
      returnFromIso: '2027-07-18T20:00:00',
      returnToIso: '2027-07-18T21:00:00',
      released: true,
      tabs: ['uebersicht', 'leihweise'],
      categoryId: 'infra',
      kind: 'unique',
      stock: 1,
      stayMode: 'stay',
      services: [],
      feinWish: {
        label: t('grossanlass.planung.feinPartner.wishZeltFit'),
        ressort: t('grossanlass.materialUebersicht.sampleRessortVerpflegung'),
        fromIso: '2027-07-16T07:00:00',
        toIso: '2027-07-18T20:00:00',
      },
    },
    {
      id: 'teleskop',
      name: t('grossanlass.materialUebersicht.sampleTeleskop'),
      barcode: 'TEL-MEIER-07',
      family: 'vehicle',
      origin: 'loan',
      source: meier,
      plate: 'ZH 512 018',
      presentFromIso: '2027-07-12T08:00:00',
      presentToIso: '2027-07-20T12:00:00',
      handoverFromIso: '2027-07-12T08:00:00',
      handoverToIso: '2027-07-12T10:00:00',
      returnFromIso: '2027-07-20T08:00:00',
      returnToIso: '2027-07-20T12:00:00',
      released: true,
      tabs: ['fahrzeuge', 'uebersicht', 'leihweise'],
      categoryId: 'fahrzeuge',
      kind: 'unique',
      stock: 1,
      stayMode: 'return',
      services: [
        {
          id: 'tel-grease',
          kind: 'grease',
          fromIso: '2027-07-17T18:00:00',
          toIso: '2027-07-17T19:30:00',
          who: mw,
        },
      ],
    },
    {
      id: 'radlader',
      name: t('grossanlass.materials.sampleRadlader'),
      barcode: 'RAD-HUBER-03',
      family: 'vehicle',
      origin: 'loan',
      source: t('grossanlass.materials.sourceHuber'),
      plate: 'ZH 773 441',
      presentFromIso: '2027-07-10T08:00:00',
      presentToIso: '2027-07-22T12:00:00',
      handoverFromIso: '2027-07-10T08:00:00',
      handoverToIso: '2027-07-10T10:00:00',
      returnFromIso: '2027-07-22T08:00:00',
      returnToIso: '2027-07-22T12:00:00',
      released: false,
      tabs: ['fahrzeuge', 'uebersicht', 'leihweise'],
      categoryId: 'fahrzeuge',
      kind: 'unique',
      stock: 1,
      stayMode: 'return',
      services: [],
      feinWish: {
        label: t('grossanlass.planung.feinPartner.wishRadladerWide'),
        ressort: bau,
        fromIso: '2027-07-08T08:00:00',
        toIso: '2027-07-25T18:00:00',
      },
    },
  ]
}

export function articleToResource(article: GaZusageArticle): GaEinsatzResource {
  return {
    id: article.id,
    name: article.name,
    family: article.family,
    stayMode: article.stayMode,
    categoryId: article.categoryId,
    kind: article.kind,
    stock: article.stock,
    presentFromIso: article.presentFromIso,
    presentToIso: article.presentToIso,
    released: article.released,
  }
}

export function mergeEinsatzResources(
  base: GaEinsatzResource[],
  articles: GaZusageArticle[],
): GaEinsatzResource[] {
  const byId = new Map(base.map((row) => [row.id, { ...row }]))
  for (const article of articles) {
    const existing = byId.get(article.id)
    const extra = articleToResource(article)
    byId.set(article.id, existing ? { ...existing, ...extra } : extra)
  }
  return [...byId.values()]
}

function occupancyBar(
  article: GaZusageArticle,
  role: GaEinsatzBarRole,
  id: string,
  fromIso: string,
  toIso: string,
  who: string,
  objectName: string,
  locale: string,
): GaPreviewEinsatz {
  return {
    id,
    objectId: article.id,
    objectName,
    kind: article.kind,
    qty: 1,
    stock: article.stock,
    fromIso,
    toIso,
    fromLabel: formatGaIsoLabel(fromIso, locale),
    toLabel: formatGaIsoLabel(toIso, locale),
    ressort: article.source,
    status: 'planned',
    who,
    barRole: role,
  }
}

export function zusageOccupancyBars(
  articles: GaZusageArticle[],
  t: Translate,
  locale = 'de-CH',
): GaPreviewEinsatz[] {
  const mw = t('grossanlass.materialUebersicht.sampleWho3')
  const rows: GaPreviewEinsatz[] = []
  for (const article of articles) {
    rows.push(occupancyBar(
      article,
      'handover',
      `${article.id}-handover`,
      article.handoverFromIso,
      article.handoverToIso,
      mw,
      article.name,
      locale,
    ))
    rows.push(occupancyBar(
      article,
      'giveback',
      `${article.id}-return`,
      article.returnFromIso,
      article.returnToIso,
      mw,
      article.name,
      locale,
    ))
    for (const service of article.services) {
      rows.push({
        ...occupancyBar(
          article,
          'service',
          service.id,
          service.fromIso,
          service.toIso,
          service.who,
          article.name,
          locale,
        ),
        ressort: parkServiceLabel(service.kind, t, service.label),
      })
    }
  }
  return rows
}

export function feinDeltaKind(article: GaZusageArticle): 'wide' | 'fit' | 'none' {
  const wish = article.feinWish
  if (!wish) return 'none'
  const wishFrom = parseLocalDate(wish.fromIso).getTime()
  const wishTo = parseLocalDate(wish.toIso).getTime()
  const haveFrom = parseLocalDate(article.presentFromIso).getTime()
  const haveTo = parseLocalDate(article.presentToIso).getTime()
  if (wishFrom < haveFrom || wishTo > haveTo) return 'wide'
  return 'fit'
}

export function applyZusageToMaterialRow(
  row: GaPreviewRow,
  article: GaZusageArticle,
  locale = 'de-CH',
): GaPreviewRow {
  return {
    ...row,
    source: article.source,
    validFrom: formatGaDateLabel(article.presentFromIso, locale),
    validTo: formatGaDateLabel(article.presentToIso, locale),
    presentFromIso: article.presentFromIso,
    presentToIso: article.presentToIso,
    handoverFromIso: article.handoverFromIso,
    handoverToIso: article.handoverToIso,
    returnFromIso: article.returnFromIso,
    returnToIso: article.returnToIso,
    releasedForEinsatz: article.released,
    parkServices: article.services,
    origin: article.origin,
    feinWish: article.feinWish,
    sessionCreated: article.sessionCreated,
  }
}

export function articleToMaterialRow(article: GaZusageArticle, locale = 'de-CH'): GaPreviewRow {
  const lifecycle = originToLifecycle(article.origin)
  return {
    id: article.id,
    name: article.name,
    barcode: article.barcode,
    is_combo: true,
    material_type: 'physical_combo',
    lifecycle,
    tabs: article.tabs,
    plate: article.plate,
    source: article.source,
    total_stock: article.stock,
    issued_out: 0,
    repair_stock: 0,
    available: article.released ? article.stock : 0,
    validFrom: formatGaDateLabel(article.presentFromIso, locale),
    validTo: formatGaDateLabel(article.presentToIso, locale),
    location: undefined,
    presentFromIso: article.presentFromIso,
    presentToIso: article.presentToIso,
    handoverFromIso: article.handoverFromIso,
    handoverToIso: article.handoverToIso,
    returnFromIso: article.returnFromIso,
    returnToIso: article.returnToIso,
    releasedForEinsatz: article.released,
    parkServices: article.services,
    origin: article.origin,
    feinWish: article.feinWish,
    sessionCreated: article.sessionCreated,
    components: [],
  }
}
