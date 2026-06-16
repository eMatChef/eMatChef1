import apiClient from './apiClient'

export type JsOrderStatus = 'draft' | 'ready' | 'ordered' | 'fulfilled' | 'cancelled'
export type JsOrderDeliveryType = 'franko' | 'pickup_thun'
export type JsOrderCourseType = 'lager' | 'kaderbildung' | ''

export interface JsOrderFormBlock1 {
  first_name: string
  last_name: string
  email: string
  address: string
  postal_code: string
  city: string
  canton: string
  phone: string
  person_nr: string
  offer_number: string
  user_overridden: string[]
}

export interface JsOrderFormBlock2 {
  course_type: JsOrderCourseType
  participant_count: number | null
  delivery_date: string
  return_date: string
  coach_first_name: string
  coach_last_name: string
  coach_person_nr: string
  user_overridden: string[]
}

export interface JsOrderFormBlock3 {
  venue_name: string
  contact_first_name: string
  contact_last_name: string
  address: string
  postal_code: string
  city: string
  canton: string
  delivery_phone: string
  camp_leader_phone: string
  user_overridden: string[]
}

export interface JsOrderFormData {
  block1: JsOrderFormBlock1
  block2: JsOrderFormBlock2
  block3: JsOrderFormBlock3
}

export interface ActivityJsOrderItemApi {
  id: string
  material_item_id: string
  material_name?: string
  quantity_ordered: number
  dotation_suggested?: number | null
  order_confirmed: boolean
  quantity_received: number
  quantity_returned: number
  notes?: string | null
  sort_order: number
}

export interface JsOrderItemSaveRow {
  material_item_id: string
  quantity_ordered: number
  dotation_suggested?: number | null
  notes?: string | null
}

export interface JsCatalogItem {
  id: string
  name: string
  description?: string | null
  dotation_hint?: string | null
  dotation_suggested?: number | null
  dotation_max?: number | null
  dotation_group?: string | null
  dotation_group_max?: number | null
  dotation_group_warn_max?: number | null
  dotation_round_up?: number | null
  stock_available?: number | null
  pdf_form_line?: string | null
  pdf_line_order?: number | null
  variant_group?: string | null
}

export interface ActivityJsOrderApi {
  id: string
  activity_id: string
  status: JsOrderStatus
  form_data: JsOrderFormData
  participant_count: number | null
  delivery_type: JsOrderDeliveryType
  ordered_at?: string | null
  ordered_by_user_id?: string | null
  generated_pdf_media_id?: string | null
  generated_pdf_url?: string | null
  dotation_warnings?: string[]
  items: ActivityJsOrderItemApi[]
  created_at: string
  updated_at: string
}

export const EMPTY_JS_ORDER_FORM: JsOrderFormData = {
  block1: {
    first_name: '',
    last_name: '',
    email: '',
    address: '',
    postal_code: '',
    city: '',
    canton: '',
    phone: '',
    person_nr: '',
    offer_number: '',
    user_overridden: [],
  },
  block2: {
    course_type: '',
    participant_count: null,
    delivery_date: '',
    return_date: '',
    coach_first_name: '',
    coach_last_name: '',
    coach_person_nr: '',
    user_overridden: [],
  },
  block3: {
    venue_name: '',
    contact_first_name: '',
    contact_last_name: '',
    address: '',
    postal_code: '',
    city: '',
    canton: '',
    delivery_phone: '',
    camp_leader_phone: '',
    user_overridden: [],
  },
}

function numOrNull(v: unknown): number | null {
  if (v == null || v === '') return null
  const n = typeof v === 'number' ? v : Number.parseInt(String(v), 10)
  return Number.isFinite(n) && n >= 1 ? n : null
}

function str(v: unknown, fallback = ''): string {
  return v == null ? fallback : String(v)
}

function strList(v: unknown): string[] {
  if (!Array.isArray(v)) return []
  return v.map((x) => String(x))
}

function mapFormBlock1(raw: Record<string, unknown>): JsOrderFormBlock1 {
  return {
    first_name: str(raw.first_name),
    last_name: str(raw.last_name),
    email: str(raw.email),
    address: str(raw.address),
    postal_code: str(raw.postal_code),
    city: str(raw.city),
    canton: str(raw.canton),
    phone: str(raw.phone),
    person_nr: str(raw.person_nr),
    offer_number: str(raw.offer_number),
    user_overridden: strList(raw.user_overridden),
  }
}

function mapFormBlock2(raw: Record<string, unknown>): JsOrderFormBlock2 {
  const course = str(raw.course_type)
  return {
    course_type: course === 'lager' || course === 'kaderbildung' ? course : '',
    participant_count: numOrNull(raw.participant_count),
    delivery_date: str(raw.delivery_date),
    return_date: str(raw.return_date),
    coach_first_name: str(raw.coach_first_name),
    coach_last_name: str(raw.coach_last_name),
    coach_person_nr: str(raw.coach_person_nr),
    user_overridden: strList(raw.user_overridden),
  }
}

function mapFormBlock3(raw: Record<string, unknown>): JsOrderFormBlock3 {
  return {
    venue_name: str(raw.venue_name),
    contact_first_name: str(raw.contact_first_name),
    contact_last_name: str(raw.contact_last_name),
    address: str(raw.address),
    postal_code: str(raw.postal_code),
    city: str(raw.city),
    canton: str(raw.canton),
    delivery_phone: str(raw.delivery_phone),
    camp_leader_phone: str(raw.camp_leader_phone),
    user_overridden: strList(raw.user_overridden),
  }
}

function mapFormData(raw: unknown): JsOrderFormData {
  const fd = (raw && typeof raw === 'object' ? raw : {}) as Record<string, unknown>
  return {
    block1: mapFormBlock1((fd.block1 as Record<string, unknown>) ?? {}),
    block2: mapFormBlock2((fd.block2 as Record<string, unknown>) ?? {}),
    block3: mapFormBlock3((fd.block3 as Record<string, unknown>) ?? {}),
  }
}

function mapOrder(raw: Record<string, unknown>): ActivityJsOrderApi {
  const delivery = str(raw.delivery_type)
  const status = str(raw.status, 'draft') as JsOrderStatus
  const itemsRaw = Array.isArray(raw.items) ? raw.items : []

  return {
    id: str(raw.id),
    activity_id: str(raw.activity_id),
    status,
    form_data: mapFormData(raw.form_data),
    participant_count: numOrNull(raw.participant_count),
    delivery_type: delivery === 'pickup_thun' ? 'pickup_thun' : 'franko',
    ordered_at: raw.ordered_at != null ? str(raw.ordered_at) : null,
    ordered_by_user_id: raw.ordered_by_user_id != null ? str(raw.ordered_by_user_id) : null,
    generated_pdf_media_id:
      raw.generated_pdf_media_id != null ? str(raw.generated_pdf_media_id) : null,
    generated_pdf_url: raw.generated_pdf_url != null ? str(raw.generated_pdf_url) : null,
    dotation_warnings: Array.isArray(raw.dotation_warnings)
      ? raw.dotation_warnings.map((w) => str(w)).filter(Boolean)
      : [],
    items: itemsRaw.map((item) => {
      const i = item as Record<string, unknown>
      return {
        id: str(i.id),
        material_item_id: str(i.material_item_id),
        material_name: i.material_name != null ? str(i.material_name) : undefined,
        quantity_ordered: Number(i.quantity_ordered ?? 0) || 0,
        dotation_suggested: numOrNull(i.dotation_suggested),
        order_confirmed: !!i.order_confirmed,
        quantity_received: Number(i.quantity_received ?? 0) || 0,
        quantity_returned: Number(i.quantity_returned ?? 0) || 0,
        notes: i.notes != null ? str(i.notes) : null,
        sort_order: Number(i.sort_order ?? 0) || 0,
      }
    }),
    created_at: str(raw.created_at),
    updated_at: str(raw.updated_at),
  }
}

export async function getActivityJsOrder(activityId: string): Promise<ActivityJsOrderApi | null> {
  const response = await apiClient.get<{ order: Record<string, unknown> | null }>(
    `/api/activities/${activityId}/js-order`,
  )
  const raw = response.data.order
  return raw ? mapOrder(raw) : null
}

export async function saveActivityJsOrder(
  activityId: string,
  payload: {
    form_data: JsOrderFormData
    participant_count?: number | null
    delivery_type?: JsOrderDeliveryType
    status?: 'draft' | 'ready'
    items?: JsOrderItemSaveRow[]
  },
): Promise<ActivityJsOrderApi> {
  const response = await apiClient.put<{ order: Record<string, unknown> }>(
    `/api/activities/${activityId}/js-order`,
    payload,
  )
  return mapOrder(response.data.order)
}

export async function prefillActivityJsOrder(
  activityId: string,
  options?: { force?: boolean },
): Promise<ActivityJsOrderApi> {
  const response = await apiClient.post<{ order: Record<string, unknown> }>(
    `/api/activities/${activityId}/js-order/prefill`,
    options?.force ? { force: true } : {},
  )
  return mapOrder(response.data.order)
}

export async function loadOrCreateActivityJsOrder(activityId: string): Promise<ActivityJsOrderApi> {
  const existing = await getActivityJsOrder(activityId)
  if (existing) return existing
  return prefillActivityJsOrder(activityId)
}

export async function getJsMaterialCatalog(params: {
  departmentId: string
  search?: string
  page?: number
  limit?: number
  participantCount?: number | null
  courseType?: string | null
}): Promise<{ items: JsCatalogItem[]; total: number; page: number; limit: number }> {
  const response = await apiClient.get<{
    items: Record<string, unknown>[]
    total: number
    page: number
    limit: number
  }>('/api/materials/js-catalog', {
    params: {
      department_id: params.departmentId,
      search: params.search?.trim() || undefined,
      page: params.page ?? 1,
      limit: params.limit ?? 40,
      participant_count:
        params.participantCount != null && params.participantCount >= 1
          ? params.participantCount
          : undefined,
      course_type: params.courseType?.trim() || undefined,
    },
  })
  const items = (response.data.items ?? []).map((raw) => ({
    id: str(raw.id),
    name: str(raw.name),
    description: raw.description != null ? str(raw.description) : null,
    dotation_hint: raw.dotation_hint != null ? str(raw.dotation_hint) : null,
    dotation_suggested: numOrNull(raw.dotation_suggested),
    dotation_max: numOrNull(raw.dotation_max),
    dotation_group: raw.dotation_group != null ? str(raw.dotation_group) : null,
    dotation_group_max: numOrNull(raw.dotation_group_max),
    dotation_group_warn_max: numOrNull(raw.dotation_group_warn_max),
    dotation_round_up: numOrNull(raw.dotation_round_up),
    stock_available: numOrNull(raw.stock_available),
    pdf_form_line: raw.pdf_form_line != null ? str(raw.pdf_form_line) : null,
    pdf_line_order: numOrNull(raw.pdf_line_order),
    variant_group: raw.variant_group != null ? str(raw.variant_group) : null,
  }))
  return {
    items,
    total: Number(response.data.total ?? 0) || 0,
    page: Number(response.data.page ?? 1) || 1,
    limit: Number(response.data.limit ?? 40) || 40,
  }
}

export async function applyJsOrderDotation(
  activityId: string,
  options?: { participantCount?: number | null },
): Promise<ActivityJsOrderApi> {
  const response = await apiClient.post<{ order: Record<string, unknown> }>(
    `/api/activities/${activityId}/js-order/apply-dotation`,
    {
      participant_count:
        options?.participantCount != null && options.participantCount >= 1
          ? options.participantCount
          : undefined,
    },
  )
  return mapOrder(response.data.order)
}

export async function generateActivityJsOrderPdf(
  activityId: string,
): Promise<{ order: ActivityJsOrderApi; pdf_url: string }> {
  const response = await apiClient.post<{ order: Record<string, unknown>; pdf_url: string }>(
    `/api/activities/${activityId}/js-order/generate-pdf`,
    {},
  )
  return {
    order: mapOrder(response.data.order),
    pdf_url: str(response.data.pdf_url),
  }
}

export async function fetchActivityJsOrderPdfBlob(pdfUrl: string): Promise<Blob> {
  const path = pdfUrl.startsWith('/') ? pdfUrl : `/${pdfUrl}`
  const response = await apiClient.get<Blob>(path, { responseType: 'blob' })
  return response.data
}

export function jsOrderStatusLabelKey(status: JsOrderStatus | null | undefined): string {
  if (!status) return 'activities.jsMaterial.order.statusNotStarted'
  return `activities.jsMaterial.order.status.${status}`
}
