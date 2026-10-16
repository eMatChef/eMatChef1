import apiClient from './apiClient'

/** Layout-Dokument (Schema Version 1) eines Infoscreens, siehe docs/devices/infoscreen.md. */
export interface LayoutDocument {
  schema_version: 1
  default_variant_id: string
  variants: LayoutVariant[]
  pages: LayoutPage[]
}

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
  rows: number
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
  steps: { duration_sec: number | null; content: LayoutContent }[]
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
  type: 'string' | 'text' | 'int' | 'bool' | 'enum' | 'url' | 'lines'
  default: unknown
  max?: number
  min?: number
  values?: string[]
  max_items?: number
}

export interface WidgetDefinition {
  type: string
  category: 'general' | 'ga' | 'department'
  contexts: string[]
  data_source: 'static' | 'clock' | 'ga_demo'
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

export interface LayoutCatalog {
  enabled: boolean
  context: 'grossanlass' | 'department'
  limits: {
    columns: number
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

/** Fehlerdetails (Validierung 422, Versionskonflikt 409) aus einer Axios-Antwort. */
export function layoutErrorInfo(err: unknown): { status?: number; code?: string; errors: LayoutError[]; currentVersion?: number } {
  const res = (err as { response?: { status?: number; data?: { code?: string; errors?: LayoutError[]; current_version?: number } } })?.response
  return { status: res?.status, code: res?.data?.code, errors: res?.data?.errors ?? [], currentVersion: res?.data?.current_version }
}
