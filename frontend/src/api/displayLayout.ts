import apiClient from './apiClient'

/** Layout-Dokument (Schema Version 1) eines Infoscreens, siehe docs/devices/infoscreen.md. */
export interface LayoutDocument {
  schema_version: 1
  default_variant_id: string
  variants: LayoutVariant[]
  pages: LayoutPage[]
  /** Datenmodus der veröffentlichten Anzeige: fehlt = «live» (echte GA-Daten); «demo» nur ausdrücklich. */
  data_mode?: LayoutDataMode
}

export type LayoutDataMode = 'live' | 'demo'

export interface LayoutVariant {
  id: string
  name: string
  /** z. B. «16:9», «3:2» */
  aspect_ratio: string
  root: LayoutContent
}

export interface LayoutPage {
  id: string
  name: string
  rows: number
  /** Spalten des Rasters (Standard 12; feinere Raster z. B. für LED-Walls). */
  columns?: number
  items: LayoutItem[]
}

export interface LayoutItem {
  id: string
  x: number
  y: number
  w: number
  h: number
  content: LayoutContent
}

export interface LayoutWidgetContent {
  type: 'widget'
  widget: string
  widget_version: number
  config: Record<string, unknown>
}
export interface LayoutGroupContent {
  type: 'group'
  /** Anzeigename der Gruppe im Studio. */
  name?: string
  rows: number
  /** Spalten des eigenen Rasters der Gruppe (Standard 12). */
  columns?: number
  items: LayoutItem[]
}
export interface LayoutPageContent {
  type: 'page'
  page_id: string
}
export interface LayoutRotationContent {
  type: 'rotation'
  enabled: boolean
  interval_sec: number
  steps: { name?: string; duration_sec: number | null; content: LayoutContent }[]
}
export type LayoutContent = LayoutWidgetContent | LayoutGroupContent | LayoutPageContent | LayoutRotationContent

export interface LayoutOrigin {
  template_id?: string
  template_version?: number
  screen_id?: string
  restored_from_revision?: number
}

export interface LayoutRevisionInfo {
  number: number
  published_at: string
  published_by_user_id: string | null
  restored_from_number: number | null
}

export interface LayoutState {
  draft: LayoutDocument | null
  draft_version: number
  draft_updated_at: string | null
  origin: LayoutOrigin | null
  published: LayoutRevisionInfo | null
  has_unpublished_changes: boolean
}

export interface LayoutError {
  path: string
  code: string
  message: string
}

export interface WidgetField {
  type: 'string' | 'text' | 'int' | 'float' | 'bool' | 'enum' | 'set' | 'url' | 'lines'
  default: unknown
  max?: number
  min?: number
  values?: string[]
  max_items?: number
}

export interface WidgetDefinition {
  type: string
  category: 'general' | 'ga' | 'department'
  /** Fachfamilie der GA-Widgets (material, logistik, overall, projekt, tasks, helpers, feed, warnings, map). */
  family?: string
  contexts: string[]
  data_source: 'static' | 'clock' | 'ga_demo' | 'ga_live'
  /** Hat eine echte Datenanbindung; sonst nur Demo möglich (Veröffentlichen mit echten Daten wird abgelehnt). */
  live?: boolean
  min_size: [number, number]
  default_size: [number, number]
  version: number
  fields: Record<string, WidgetField>
}

export interface LayoutTemplate {
  id: string
  version: number
  contexts: string[]
  layout: LayoutDocument
}

/** Offizieller Baustein (Widget-Gruppe oder Layoutbereich) aus dem Katalog. */
export interface OfficialBlock {
  id: string
  kind: 'group' | 'region'
  category: string
  contexts: string[]
  default_size: [number, number]
  content: LayoutGroupContent
}

/** Eigener Baustein eines Grossanlasses (Vorlage; Einfügen erzeugt immer eine unabhängige Kopie). */
export interface CustomBlock {
  id: string
  name: string
  kind: 'widget' | 'group' | 'rotation'
  content: LayoutContent
  default_size: [number, number]
  created_by_user_id: string | null
  created_at: string
  updated_at: string
}

export interface LayoutCatalog {
  enabled: boolean
  context: 'grossanlass' | 'department'
  limits: {
    columns: number
    max_columns: number
    default_rows: number
    max_rows: number
    max_depth: number
    max_variants: number
    max_pages: number
    max_items: number
    max_steps: number
    min_interval_sec: number
    max_interval_sec: number
    default_interval_sec: number
  }
  widgets: WidgetDefinition[]
  templates: LayoutTemplate[]
  blocks: OfficialBlock[]
}

/** Layout-Teil des Anzeige-Payloads (Geräte: nur die veröffentlichte Revision). */
export interface DisplayLayoutPayload {
  state: 'ready' | 'unpublished'
  source: 'published' | 'draft'
  revision?: number | null
  published_at?: string
  layout?: LayoutDocument
  variant_id?: string
  font_scale?: number
}

const base = (departmentId: string, screenId: string) =>
  `/api/departments/${encodeURIComponent(departmentId)}/display-screens/${encodeURIComponent(screenId)}/layout`

export async function getLayoutCatalog(departmentId: string): Promise<LayoutCatalog> {
  const res = await apiClient.get<LayoutCatalog>(`/api/departments/${encodeURIComponent(departmentId)}/display-layout/catalog`)
  return res.data
}

export async function getLayoutState(departmentId: string, screenId: string): Promise<LayoutState> {
  const res = await apiClient.get<LayoutState>(base(departmentId, screenId))
  return res.data
}

/** Entwurf aus Vorlage (`blank` = leer) oder aus einem anderen Infoscreen desselben Departments. */
export async function initializeLayout(
  departmentId: string,
  screenId: string,
  body: { template_id?: string; source_screen_id?: string; expected_version: number },
): Promise<LayoutState> {
  const res = await apiClient.post<LayoutState>(`${base(departmentId, screenId)}/initialize`, body)
  return res.data
}

export async function saveLayoutDraft(
  departmentId: string,
  screenId: string,
  layout: LayoutDocument,
  expectedVersion: number,
): Promise<LayoutState> {
  const res = await apiClient.put<LayoutState>(`${base(departmentId, screenId)}/draft`, { layout, expected_version: expectedVersion })
  return res.data
}

export async function validateLayoutDraft(departmentId: string, screenId: string): Promise<{ valid: boolean; errors: LayoutError[] }> {
  const res = await apiClient.post<{ valid: boolean; errors: LayoutError[] }>(`${base(departmentId, screenId)}/validate`)
  return res.data
}

export async function publishLayout(departmentId: string, screenId: string, expectedVersion: number): Promise<LayoutState> {
  const res = await apiClient.post<LayoutState>(`${base(departmentId, screenId)}/publish`, { expected_version: expectedVersion })
  return res.data
}

export async function listLayoutRevisions(departmentId: string, screenId: string): Promise<LayoutRevisionInfo[]> {
  const res = await apiClient.get<LayoutRevisionInfo[]>(`${base(departmentId, screenId)}/revisions`)
  return res.data || []
}

export async function restoreLayoutRevision(
  departmentId: string,
  screenId: string,
  number: number,
  expectedVersion: number,
  publish: boolean,
): Promise<LayoutState> {
  const res = await apiClient.post<LayoutState>(`${base(departmentId, screenId)}/revisions/${number}/restore`, {
    expected_version: expectedVersion,
    publish,
  })
  return res.data
}

const blocksBase = (departmentId: string) => `/api/departments/${encodeURIComponent(departmentId)}/display-blocks`

export async function listDisplayBlocks(departmentId: string): Promise<CustomBlock[]> {
  const res = await apiClient.get<{ blocks: CustomBlock[] }>(blocksBase(departmentId))
  return res.data.blocks ?? []
}

export async function createDisplayBlock(
  departmentId: string,
  body: { name: string; content: LayoutContent; default_w: number; default_h: number },
): Promise<CustomBlock> {
  const res = await apiClient.post<CustomBlock>(blocksBase(departmentId), body)
  return res.data
}

export async function renameDisplayBlock(departmentId: string, blockId: string, name: string): Promise<CustomBlock> {
  const res = await apiClient.patch<CustomBlock>(`${blocksBase(departmentId)}/${encodeURIComponent(blockId)}`, { name })
  return res.data
}

export async function deleteDisplayBlock(departmentId: string, blockId: string): Promise<void> {
  await apiClient.delete(`${blocksBase(departmentId)}/${encodeURIComponent(blockId)}`)
}

/** Fehlerdetails (Validierung 422, Versionskonflikt 409) aus einer Axios-Antwort. */
export function layoutErrorInfo(err: unknown): { status?: number; code?: string; errors: LayoutError[]; currentVersion?: number } {
  const res = (err as { response?: { status?: number; data?: { code?: string; errors?: LayoutError[]; current_version?: number } } })?.response
  return { status: res?.status, code: res?.data?.code, errors: res?.data?.errors ?? [], currentVersion: res?.data?.current_version }
}

/** Kartendaten des Widgets «ga_site_map»: dieselben Orte und derselbe Geländeplan wie die GA-Planung (nur Anzeige). */
export interface DisplayMapPlace {
  id: string
  name: string
  kind: string
  latitude: number | null
  longitude: number | null
  polygon: { lat: number; lng: number }[] | null
  starred: boolean
  /** Zugeordnete Gruppen samt Vorgängern (Filter nach Bauprojekt/Bereich). */
  group_ids: string[]
}

export interface DisplayMapPlan {
  id: string
  name: string
  image_url: string
  bounds_north: number
  bounds_south: number
  bounds_east: number
  bounds_west: number
  overlay_opacity: number
}

export interface DisplayMapData {
  state: 'ready' | 'unavailable'
  plan: DisplayMapPlan | null
  places: DisplayMapPlace[]
}

/** Live-Daten der GA-Fachwidgets (Gerät und Editor-Live-Vorschau), nur lesend; siehe DisplayLiveDataService. */
export interface GaLiveTotals {
  required: number; covered: number; open: number; received: number; allocated: number; packed: number
  ready_for_transport: number; in_transit: number; at_place: number; return_open: number; returned: number; disposed: number
}
/** Bauprojekt/Bereich mit seiner Zuordnung aus der GA-Hierarchie (Ressort › Bereich › Teilbereich › Auftrag). */
export interface GaLiveGroup {
  id: string; name: string; kind: string | null; ressort: string; bereich: string; origin: string[]; window_end: string | null
}
export interface GaLiveMaterialItem {
  id: string; group_id: string; label: string; unit: string
  required: number; covered: number; open: number; received: number; allocated: number; packed: number
  ready_for_transport: number; in_transit: number; at_place: number
}
export interface GaLivePack {
  id: string; code: string; status: 'staging' | 'trip_released' | 'in_transit' | 'at_place'
  group_id: string; title: string; lines: { label: string; qty: number }[]; target: string
}
export interface GaLiveMaterialGroup {
  group_id: string; group_name: string; items: number; open_items: number; totals: Record<string, GaLiveTotals>
}
export interface GaLiveMaterialLine {
  label: string; unit: string; group_id: string; group_name: string
  required: number; covered: number; open: number; received: number; packed: number; expected?: number
}
export interface GaLiveNeed {
  id: string; title: string; from: string; to: string; target: string; cargo: string
  starts_at: string; ends_at: string; status: 'open' | 'planned' | 'underway' | 'done'
  ready: boolean; released: boolean; urgent: boolean; tour_id: string | null
  kind: 'transport' | 'vehicle'; group_id: string | null
}
export interface GaLiveTour {
  id: string; name: string; status: string; confirmed: boolean; vehicle: string; trailer: string; driver: string
  starts_at: string | null; ends_at: string | null; need_count: number; has_conflict: boolean; group_ids: string[]
  stops: { label: string; kind: string; planned_at: string | null; done: boolean }[]
}
export interface GaLiveTask {
  id: string; title: string; group_id: string; group_name: string; ressort: string; bereich: string; origin: string[]
  status: 'open' | 'progress' | 'review' | 'done'
  starts_at: string | null; ends_at: string | null; steps_total: number; steps_done: number
  blocked: boolean; blocked_reason: string | null; overdue: boolean
}
export interface GaLiveProject {
  group_id: string; name: string; progress: number
  tasks: { total: number; open: number; progress: number; review: number; done: number; blocked: number; overdue: number }
  material: { required: number; packed: number; open_items: number } | null
  helpers: { need: number; assigned: number }
  current_task: string | null; next_task: string | null; deadline: string | null
}
export interface GaLiveFeedEvent {
  id: string; at: string; area: 'task' | 'logistics'; kind: string; group_id: string | null; subject: string; detail: string
  severity: 'info' | 'success' | 'warning'
}
export interface GaLiveWarning { level: 'warning' | 'critical'; kind: string; group_id?: string | null; group_ids?: string[]; title: string; detail: string }
/** Fachzeit des Bildschirms: nur im Demomodus des Departments mit Zeitangabe (naive Wandzeit), sonst echte Zeit. */
export interface DisplayClockPayload { mode: 'real' | 'demo'; now?: string; offset_seconds?: number }
export interface GaLiveData {
  state: 'ready' | 'unavailable'
  generated_at?: string
  clock?: DisplayClockPayload
  groups?: GaLiveGroup[]
  material?: { groups: GaLiveMaterialGroup[]; items: GaLiveMaterialItem[]; packs: GaLivePack[]; missing: GaLiveMaterialLine[]; incoming: GaLiveMaterialLine[] }
  logistics?: {
    counts: Record<string, number>; needs: GaLiveNeed[]; tours: GaLiveTour[]; active_tours: number; vehicles: number; drivers: number
  }
  tasks?: GaLiveTask[]
  task_counts?: Record<string, number>
  projects?: GaLiveProject[]
  feed?: GaLiveFeedEvent[]
  warnings?: GaLiveWarning[]
}
