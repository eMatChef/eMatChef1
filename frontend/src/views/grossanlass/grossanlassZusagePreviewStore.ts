import { reactive } from 'vue'
import {
  createGrossanlassMaterialsPreview,
  type GaPreviewRow,
} from '@/views/grossanlass/grossanlassMaterialsPreviewData'
import {
  createGrossanlassEinsatzResources,
  type GaEinsatzResource,
  type GaPreviewEinsatz,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import {
  applyZusageToMaterialRow,
  articleToMaterialRow,
  combineIso,
  createGrossanlassZusageArticles,
  mergeEinsatzResources,
  zusageOccupancyBars,
  type GaParkServiceKind,
  type GaZusageArticle,
  type GaZusageOrigin,
} from '@/views/grossanlass/grossanlassZusagePreviewData'
import { acceptedGuestLoanResources } from '@/views/grossanlass/grossanlassGaestePreviewStore'

type Translate = (key: string, values?: Record<string, string | number>) => string

export type GaZusageCreateDraft = {
  name: string
  family: 'vehicle' | 'material'
  origin: GaZusageOrigin
  source: string
  plate?: string
  presentFromDate: string
  presentToDate: string
  presentFromTime: string
  presentToTime: string
  handoverDate: string
  handoverFromTime: string
  handoverToTime: string
  returnDate: string
  returnFromTime: string
  returnToTime: string
  released: boolean
  firstServiceKind?: GaParkServiceKind | ''
  firstServiceDate?: string
  firstServiceFromTime?: string
  firstServiceToTime?: string
  fromLineId?: string
}

const state = reactive({
  extras: [] as GaZusageArticle[],
  released: {} as Record<string, boolean>,
  extraServices: {} as Record<string, GaZusageArticle['services']>,
})

function slugId(name: string): string {
  const base = name
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
    .slice(0, 24) || 'artikel'
  return `zusage-${base}-${Date.now().toString(36)}`
}

export function listZusageArticles(t: Translate): GaZusageArticle[] {
  const demo = createGrossanlassZusageArticles(t).map((article) => ({
    ...article,
    released: article.id in state.released ? state.released[article.id] : article.released,
    services: [...article.services, ...(state.extraServices[article.id] ?? [])],
  }))
  const extras = state.extras.map((article) => ({
    ...article,
    released: article.id in state.released ? state.released[article.id] : article.released,
    services: [...article.services, ...(state.extraServices[article.id] ?? [])],
  }))
  return [...demo, ...extras]
}

export function findZusageArticle(t: Translate, id: string): GaZusageArticle | undefined {
  return listZusageArticles(t).find((article) => article.id === id)
}

export function mergedMaterialsCatalog(t: Translate, locale = 'de-CH'): GaPreviewRow[] {
  const articles = listZusageArticles(t)
  const byId = new Map(articles.map((article) => [article.id, article]))
  const base = createGrossanlassMaterialsPreview((key) => t(key)).map((row) => {
    const article = byId.get(row.id)
    return article ? applyZusageToMaterialRow(row, article, locale) : row
  })
  for (const article of articles) {
    if (base.some((row) => row.id === article.id)) continue
    const row = articleToMaterialRow(article, locale)
    row.vehicleStatus = article.released
      ? t('grossanlass.materials.zusage.releasedShort')
      : t('grossanlass.materials.zusage.heldShort')
    row.category_name = article.family === 'vehicle'
      ? t('grossanlass.materials.catFahrzeug')
      : t('grossanlass.materials.catInfra')
    base.push(row)
  }
  return base.map((row) => {
    const article = byId.get(row.id)
    if (!article) return row
    return {
      ...row,
      vehicleStatus: article.released
        ? t('grossanlass.materials.zusage.releasedShort')
        : t('grossanlass.materials.zusage.heldShort'),
    }
  })
}

export function mergedEinsatzResources(t: Translate): GaEinsatzResource[] {
  const fromArticles = mergeEinsatzResources(createGrossanlassEinsatzResources(t), listZusageArticles(t))
  const fromGuests = acceptedGuestLoanResources(t)
  const byId = new Map(fromArticles.map((row) => [row.id, row]))
  for (const row of fromGuests) {
    byId.set(row.id, row)
  }
  return [...byId.values()]
}

export function occupancyBarsForPreview(t: Translate, locale = 'de-CH'): GaPreviewEinsatz[] {
  return zusageOccupancyBars(listZusageArticles(t), t, locale)
}

export function setArticleReleased(id: string, released: boolean) {
  state.released[id] = released
  const extra = state.extras.find((article) => article.id === id)
  if (extra) extra.released = released
}

export function addParkService(
  id: string,
  service: { kind: GaParkServiceKind; fromIso: string; toIso: string; who: string; label?: string },
) {
  const list = state.extraServices[id] ?? []
  list.push({
    id: `${id}-svc-${Date.now()}`,
    ...service,
  })
  state.extraServices[id] = list
}

export function createArticleFromZusageDraft(draft: GaZusageCreateDraft, t: Translate): GaZusageArticle {
  const presentFromIso = combineIso(draft.presentFromDate, draft.presentFromTime)
  const presentToIso = combineIso(draft.presentToDate, draft.presentToTime)
  const article: GaZusageArticle = {
    id: slugId(draft.name),
    name: draft.name.trim(),
    barcode: `ZUSAGE-${Date.now().toString(36).toUpperCase()}`,
    family: draft.family,
    origin: draft.origin,
    source: draft.source.trim(),
    plate: draft.plate?.trim() || undefined,
    presentFromIso,
    presentToIso,
    handoverFromIso: combineIso(draft.handoverDate, draft.handoverFromTime),
    handoverToIso: combineIso(draft.handoverDate, draft.handoverToTime),
    returnFromIso: combineIso(draft.returnDate, draft.returnFromTime),
    returnToIso: combineIso(draft.returnDate, draft.returnToTime),
    released: draft.released,
    tabs: draft.family === 'vehicle'
      ? ['fahrzeuge', 'uebersicht', draft.origin === 'loan' ? 'leihweise' : 'eigen']
      : [draft.origin === 'loan' ? 'leihweise' : 'eigen', 'uebersicht'],
    categoryId: draft.family === 'vehicle' ? 'fahrzeuge' : 'infra',
    kind: 'unique',
    stock: 1,
    stayMode: 'return',
    services: [],
    fromLineId: draft.fromLineId,
    sessionCreated: true,
  }
  if (draft.firstServiceKind && draft.firstServiceDate && draft.firstServiceFromTime && draft.firstServiceToTime) {
    article.services.push({
      id: `${article.id}-svc-1`,
      kind: draft.firstServiceKind,
      fromIso: combineIso(draft.firstServiceDate, draft.firstServiceFromTime),
      toIso: combineIso(draft.firstServiceDate, draft.firstServiceToTime),
      who: t('grossanlass.materialUebersicht.sampleWho3'),
    })
  }
  state.extras.push(article)
  state.released[article.id] = article.released
  return article
}
