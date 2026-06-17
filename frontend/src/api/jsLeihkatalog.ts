import apiClient from './apiClient'

export interface JsLeihkatalogCategory {
  id: string
  name: string
}

export interface JsLeihkatalogItem {
  id: string
  name: string
  description?: string | null
  pdf_line_no?: number | null
  pdf_line_order?: number | null
  pdf_form_line?: string | null
  dotation_hint?: string | null
  dotation_max?: number | null
  stock_available?: number | null
  category?: JsLeihkatalogCategory | null
  department_id: string
  is_js_material: boolean
}

export interface JsLeihkatalogListResponse {
  category: JsLeihkatalogCategory | null
  department_id: string
  items: JsLeihkatalogItem[]
  total: number
}

function str(v: unknown, fallback = ''): string {
  return v == null ? fallback : String(v)
}

function numOrNull(v: unknown): number | null {
  if (v == null || v === '') return null
  const n = typeof v === 'number' ? v : Number.parseInt(String(v), 10)
  return Number.isFinite(n) ? n : null
}

export async function getJsLeihkatalogAdmin(): Promise<JsLeihkatalogListResponse> {
  const response = await apiClient.get<Record<string, unknown>>('/api/admin/js-leihkatalog')
  const raw = response.data
  return {
    category: raw.category && typeof raw.category === 'object'
      ? { id: str((raw.category as Record<string, unknown>).id), name: str((raw.category as Record<string, unknown>).name) }
      : null,
    department_id: str(raw.department_id),
    total: numOrNull(raw.total) ?? 0,
    items: (Array.isArray(raw.items) ? raw.items : []).map((row) => {
      const r = row as Record<string, unknown>
      const cat = r.category as Record<string, unknown> | null | undefined
      return {
        id: str(r.id),
        name: str(r.name),
        description: r.description != null ? str(r.description) : null,
        pdf_line_no: numOrNull(r.pdf_line_no),
        pdf_line_order: numOrNull(r.pdf_line_no) != null ? (numOrNull(r.pdf_line_no)! - 1) : numOrNull(r.pdf_line_order),
        pdf_form_line: r.pdf_form_line != null ? str(r.pdf_form_line) : null,
        dotation_hint: r.dotation_hint != null ? str(r.dotation_hint) : null,
        dotation_max: numOrNull(r.dotation_max),
        stock_available: numOrNull(r.stock_available),
        category: cat ? { id: str(cat.id), name: str(cat.name) } : null,
        department_id: str(r.department_id),
        is_js_material: r.is_js_material === true,
      }
    }),
  }
}

export async function createJsLeihkatalogItem(payload: {
  name: string
  pdf_line_no: number
  description?: string
  stock_qty?: number
}): Promise<JsLeihkatalogItem> {
  const response = await apiClient.post<Record<string, unknown>>('/api/admin/js-leihkatalog', payload)
  const r = response.data
  return {
    id: str(r.id),
    name: str(r.name),
    description: r.description != null ? str(r.description) : null,
    pdf_line_no: numOrNull(r.pdf_line_no),
    pdf_line_order: numOrNull(r.pdf_line_no) != null ? numOrNull(r.pdf_line_no)! - 1 : null,
    pdf_form_line: r.pdf_form_line != null ? str(r.pdf_form_line) : null,
    dotation_hint: r.dotation_hint != null ? str(r.dotation_hint) : null,
    dotation_max: numOrNull(r.dotation_max),
    stock_available: numOrNull(r.stock_available),
    category: null,
    department_id: str(r.department_id),
    is_js_material: true,
  }
}

export async function updateJsLeihkatalogItem(
  id: string,
  payload: Partial<{ name: string; description: string | null; pdf_line_no: number; stock_qty: number }>,
): Promise<JsLeihkatalogItem> {
  const response = await apiClient.patch<Record<string, unknown>>(`/api/admin/js-leihkatalog/${id}`, payload)
  const r = response.data
  return {
    id: str(r.id),
    name: str(r.name),
    description: r.description != null ? str(r.description) : null,
    pdf_line_no: numOrNull(r.pdf_line_no),
    pdf_line_order: numOrNull(r.pdf_line_no) != null ? numOrNull(r.pdf_line_no)! - 1 : null,
    pdf_form_line: r.pdf_form_line != null ? str(r.pdf_form_line) : null,
    dotation_hint: r.dotation_hint != null ? str(r.dotation_hint) : null,
    dotation_max: numOrNull(r.dotation_max),
    stock_available: numOrNull(r.stock_available),
    category: null,
    department_id: str(r.department_id),
    is_js_material: true,
  }
}

export async function syncJsLeihkatalogManifest(): Promise<{ ok: boolean; stats: Record<string, number> }> {
  const response = await apiClient.post<{ ok: boolean; stats: Record<string, number> }>(
    '/api/admin/js-leihkatalog/sync-manifest',
  )
  return response.data
}
