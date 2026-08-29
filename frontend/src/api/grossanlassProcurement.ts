import apiClient from './apiClient'
import type { CreateGrossanlassWishPayload, GrossanlassWishKind } from './grossanlassWishes'

export type GrossanlassProcurementStatus =
  | 'bedarf'
  | 'offerte_eingeholt'
  | 'budgetiert'
  | 'bestellt'
  | 'teilweise_erhalten'
  | 'erhalten'

export interface GrossanlassProcurementPoolWish {
  id: string
  round_id: string
  round_name: string
  group_id: string
  group_name: string
  wish_kind: GrossanlassWishKind
  last_stage?: 'grob' | 'fein' | string | null
  label: string
  quantity: number
  location: string
  valid_from: string
  valid_to: string
  notes?: string | null
  created_by_user_id?: string
  created_by_name: string
  created_at: string
  updated_at?: string
  status?: string
  timeframe_notes?: string | null
  custom_values?: Record<string, unknown>
  received_quantity?: number
}

export interface GrossanlassProcurementQuoteSupplierAddress {
  id: string
  type: string
  name: string | null
  company: string | null
  email: string | null
  phone: string | null
  city_line: string
}

export interface GrossanlassProcurementQuote {
  id: string
  procurement_line_id: string
  supplier: string
  supplier_address_id: string | null
  supplier_address: GrossanlassProcurementQuoteSupplierAddress | null
  amount_chf: number
  notes: string | null
  selected: boolean
  pdf_filename: string | null
  pdf_url: string | null
  created_at: string
  updated_at: string
}

export interface GrossanlassProcurementQuoteContactExtract {
  text: string
  company: string | null
  name: string | null
  email: string | null
  phone: string | null
  street: string | null
  postal_code: string | null
  city: string | null
  amount_chf: number | null
}

export interface GrossanlassProcurementOrder {
  id: string
  procurement_line_id: string
  ordered_at: string
  cost_chf: number
  order_ref: string | null
  notes: string | null
  created_at: string
  updated_at: string
}

export interface GrossanlassProcurementLine {
  id: string
  department_id: string
  group_id: string
  group_name: string
  wish_kind: GrossanlassWishKind
  label: string
  quantity: number
  location: string
  notes: string | null
  category_id: string | null
  category_name: string | null
  category_parent_id: string | null
  category_parent_name: string | null
  status: GrossanlassProcurementStatus
  quantity_asked: number | null
  quantity_current: number
  quantity_delta: number | null
  merge_frozen: boolean
  wish_line_ids: string[]
  wish_count: number
  source_wishes: GrossanlassProcurementPoolWish[]
  source_quantity_sum: number
  received_quantity_sum: number
  quotes: GrossanlassProcurementQuote[]
  selected_quote_id: string | null
  budget_chf: number | null
  order: GrossanlassProcurementOrder | null
  created_at: string
  updated_at: string
}

export interface GrossanlassProcurementCategory {
  id: string
  department_id: string
  parent_id: string | null
  parent_name: string | null
  name: string
  sort_order: number
  rahmen_chf: number | null
}

export interface GrossanlassProcurementBundleSuggestion {
  key: string
  suggested_label: string
  wish_ids: string[]
  quantity_sum: number
  wish_count: number
  wishes: GrossanlassProcurementPoolWish[]
}

export interface GrossanlassCollectorAnswer {
  label: string
  value: string
}

export interface GrossanlassCollectorItem {
  id: string
  form_purpose: 'company_tip' | 'free' | string
  round_id: string
  round_name: string
  group_id: string
  group_name: string
  label: string
  quantity: number
  location: string
  notes?: string | null
  email?: string
  suggested_categories: string[]
  answers: GrossanlassCollectorAnswer[]
  created_by_name: string
  created_at: string
}

export interface GrossanlassCollectorRoundOption {
  id: string
  name: string
}

export interface GrossanlassBedarfOverview {
  pool: GrossanlassProcurementPoolWish[]
  lines: GrossanlassProcurementLine[]
  categories: GrossanlassProcurementCategory[]
  suggestions: GrossanlassProcurementBundleSuggestion[]
  company_tips?: GrossanlassCollectorItem[]
  free_ideas?: GrossanlassCollectorItem[]
  material_rounds?: GrossanlassCollectorRoundOption[]
}

export interface GrossanlassProcurementOverview {
  can_manage?: boolean
  logistics_group_id?: string | null
  logistics_group_name?: string | null
  totals: {
    line_count: number
    rahmen_chf: number | null
    soll_chf: number
    ist_chf: number
    cash_chf?: number
    netto_chf?: number
    delta_chf: number
    rahmen_minus_ist_chf: number | null
    rahmen_minus_cash_chf?: number | null
    rahmen_minus_soll_chf: number | null
    open_quotes_count: number
    ordered_not_received_count: number
  }
  by_status: Record<string, number>
  by_kind?: GrossanlassCostKindRow[]
  by_payer?: GrossanlassCostPayerRow[]
  by_requester?: GrossanlassCostRequesterRow[]
  by_group: Array<{
    group_id: string
    group_name: string
    soll_chf: number
    ist_chf: number
    cash_chf?: number
    netto_chf?: number
    line_count: number
  }>
  by_category: Array<{
    category_id: string | null
    category_name: string | null
    parent_id: string | null
    parent_name: string | null
    rahmen_chf: number | null
    soll_chf: number
    ist_chf: number
    cash_chf?: number
    netto_chf?: number
    line_count: number
  }>
  costs?: GrossanlassCost[]
  budgets?: GrossanlassBudget[]
}

export type GrossanlassCostKind = 'purchase' | 'rental' | 'loan' | 'buy_resale' | 'ancillary'
export type GrossanlassCostStatus =
  | 'planned'
  | 'committed'
  | 'paid'
  | 'for_sale'
  | 'sold'
  | 'returned'
  | 'cancelled'
export type GrossanlassAssetTreatment = 'expense' | 'inventory'

export interface GrossanlassCostKindRow {
  cost_kind: GrossanlassCostKind
  cash_chf: number
  netto_chf: number
  soll_chf: number
  line_count: number
}

export interface GrossanlassCostPayerRow {
  payer_group_id: string | null
  payer_name: string
  rahmen_chf: number | null
  cash_chf: number
  netto_chf: number
  soll_chf: number
  line_count: number
}

export interface GrossanlassCostRequesterRow {
  group_id: string
  group_name: string
  soll_chf: number
  ist_chf: number
  cash_chf: number
  netto_chf: number
  line_count: number
}

export interface GrossanlassBudget {
  id?: string
  payer_group_id: string | null
  payer_name: string | null
  rahmen_chf: number | null
  updated_at?: string
}

export interface GrossanlassCost {
  id: string
  department_id: string
  procurement_line_id: string | null
  commitment_id: string | null
  cost_kind: GrossanlassCostKind
  asset_treatment: GrossanlassAssetTreatment | null
  requesting_group_id: string | null
  requesting_group_name: string | null
  payer_group_id: string | null
  payer_group_name: string | null
  category_id: string | null
  category_name: string | null
  label: string
  partner_address_id: string | null
  soll_chf: number | null
  cash_out_chf: number | null
  deposit_chf: number | null
  deposit_returned_chf: number | null
  proceeds_expected_chf: number | null
  proceeds_actual_chf: number | null
  status: GrossanlassCostStatus
  notes: string | null
  cash_chf: number
  netto_chf: number
  created_at: string
  updated_at: string
}

export type GrossanlassCostPayload = Partial<{
  label: string
  cost_kind: GrossanlassCostKind
  asset_treatment: GrossanlassAssetTreatment | null
  status: GrossanlassCostStatus
  procurement_line_id: string | null
  commitment_id: string | null
  requesting_group_id: string | null
  payer_group_id: string | null
  category_id: string | null
  partner_address_id: string | null
  soll_chf: number | null
  cash_out_chf: number | null
  deposit_chf: number | null
  deposit_returned_chf: number | null
  proceeds_expected_chf: number | null
  proceeds_actual_chf: number | null
  notes: string | null
}>

export async function getGrossanlassBedarfOverview(departmentId: string): Promise<GrossanlassBedarfOverview> {
  const response = await apiClient.get<GrossanlassBedarfOverview>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/bedarf`,
  )
  return response.data
}

export async function assignGrossanlassCollectorToInquiry(
  departmentId: string,
  wishId: string,
  data: Partial<{ name: string; email: string; place: string; category_ids: string[] }> = {},
): Promise<GrossanlassBedarfOverview> {
  const response = await apiClient.post<GrossanlassBedarfOverview>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/collector/${wishId}/to-inquiry`,
    data,
  )
  return response.data
}

export async function assignGrossanlassCollectorToMaterial(
  departmentId: string,
  wishId: string,
  data: Partial<{
    target_round_id: string
    label: string
    quantity: number
    location: string
    wish_kind: GrossanlassWishKind
  }> = {},
): Promise<GrossanlassBedarfOverview> {
  const response = await apiClient.post<GrossanlassBedarfOverview>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/collector/${wishId}/to-material`,
    data,
  )
  return response.data
}

export async function discardGrossanlassCollectorItem(
  departmentId: string,
  wishId: string,
): Promise<GrossanlassBedarfOverview> {
  const response = await apiClient.post<GrossanlassBedarfOverview>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/collector/${wishId}/discard`,
  )
  return response.data
}

export async function updateGrossanlassBedarfWish(
  departmentId: string,
  wishLineId: string,
  data: Partial<CreateGrossanlassWishPayload>,
): Promise<GrossanlassBedarfOverview> {
  const response = await apiClient.put<GrossanlassBedarfOverview>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/wishes/${wishLineId}`,
    data,
  )
  return response.data
}

export async function getGrossanlassProcurementOverview(
  departmentId: string,
): Promise<GrossanlassProcurementOverview> {
  const response = await apiClient.get<GrossanlassProcurementOverview>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/overview`,
  )
  return response.data
}

export async function saveGrossanlassProcurementRahmen(
  departmentId: string,
  data: {
    rahmen_chf: number | null
    categories: Array<{ category_id: string; rahmen_chf: number | null }>
    payer_budgets?: Array<{ payer_group_id: string | null; rahmen_chf: number | null }>
  },
): Promise<GrossanlassProcurementOverview> {
  const response = await apiClient.put<GrossanlassProcurementOverview>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/overview/rahmen`,
    data,
  )
  return response.data
}

export async function listGrossanlassProcurementLines(
  departmentId: string,
  status?: string,
): Promise<GrossanlassProcurementLine[]> {
  const response = await apiClient.get<GrossanlassProcurementLine[]>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/lines`,
    { params: status ? { status } : undefined },
  )
  return response.data
}

export async function createGrossanlassProcurementLine(
  departmentId: string,
  data: {
    wish_line_ids: string[]
    label?: string
    quantity?: number
    location?: string
    group_id?: string
    notes?: string | null
    category_id?: string | null
    cost_kind?: GrossanlassCostKind
    payer_group_id?: string | null
    asset_treatment?: GrossanlassAssetTreatment | null
  },
): Promise<GrossanlassProcurementLine> {
  const response = await apiClient.post<GrossanlassProcurementLine>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/lines`,
    data,
  )
  return response.data
}

export async function addWishesToGrossanlassProcurementLine(
  departmentId: string,
  lineId: string,
  data: {
    wish_line_ids: string[]
    label?: string
    quantity?: number
    category_id?: string | null
  },
): Promise<GrossanlassProcurementLine> {
  const response = await apiClient.post<GrossanlassProcurementLine>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/lines/${lineId}/wishes`,
    data,
  )
  return response.data
}

export async function updateGrossanlassProcurementLine(
  departmentId: string,
  lineId: string,
  data: Partial<{
    label: string
    quantity: number
    location: string
    group_id: string
    notes: string | null
    category_id: string | null
  }>,
): Promise<GrossanlassProcurementLine> {
  const response = await apiClient.put<GrossanlassProcurementLine>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/lines/${lineId}`,
    data,
  )
  return response.data
}

export async function deleteGrossanlassProcurementLine(
  departmentId: string,
  lineId: string,
): Promise<void> {
  await apiClient.delete(`/api/departments/${departmentId}/grossanlass/beschaffung/lines/${lineId}`)
}

export async function listGrossanlassProcurementCategories(
  departmentId: string,
): Promise<GrossanlassProcurementCategory[]> {
  const response = await apiClient.get<GrossanlassProcurementCategory[]>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/categories`,
  )
  return response.data
}

export async function createGrossanlassProcurementCategory(
  departmentId: string,
  data: {
    name: string
    parent_id?: string | null
    sort_order?: number
  },
): Promise<GrossanlassProcurementCategory> {
  const response = await apiClient.post<GrossanlassProcurementCategory>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/categories`,
    data,
  )
  return response.data
}

export async function updateGrossanlassProcurementCategory(
  departmentId: string,
  categoryId: string,
  data: Partial<{
    name: string
    parent_id: string | null
    sort_order: number
  }>,
): Promise<GrossanlassProcurementCategory> {
  const response = await apiClient.put<GrossanlassProcurementCategory>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/categories/${categoryId}`,
    data,
  )
  return response.data
}

export async function deleteGrossanlassProcurementCategory(
  departmentId: string,
  categoryId: string,
): Promise<void> {
  await apiClient.delete(
    `/api/departments/${departmentId}/grossanlass/beschaffung/categories/${categoryId}`,
  )
}

export async function removeWishFromGrossanlassProcurementLine(
  departmentId: string,
  lineId: string,
  wishLineId: string,
): Promise<GrossanlassProcurementLine> {
  const response = await apiClient.delete<GrossanlassProcurementLine>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/lines/${lineId}/wishes/${wishLineId}`,
  )
  return response.data
}

export async function createGrossanlassProcurementQuote(
  departmentId: string,
  lineId: string,
  data: {
    supplier: string
    supplier_address_id?: string | null
    amount_chf: number
    notes?: string | null
  },
): Promise<GrossanlassProcurementQuote> {
  const response = await apiClient.post<GrossanlassProcurementQuote>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/lines/${lineId}/quotes`,
    data,
  )
  return response.data
}

export async function updateGrossanlassProcurementQuote(
  departmentId: string,
  lineId: string,
  quoteId: string,
  data: Partial<{
    supplier: string
    supplier_address_id: string | null
    amount_chf: number
    notes: string | null
  }>,
): Promise<GrossanlassProcurementQuote> {
  const response = await apiClient.put<GrossanlassProcurementQuote>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/lines/${lineId}/quotes/${quoteId}`,
    data,
  )
  return response.data
}

export async function deleteGrossanlassProcurementQuote(
  departmentId: string,
  lineId: string,
  quoteId: string,
): Promise<void> {
  await apiClient.delete(
    `/api/departments/${departmentId}/grossanlass/beschaffung/lines/${lineId}/quotes/${quoteId}`,
  )
}

export async function selectGrossanlassProcurementQuote(
  departmentId: string,
  lineId: string,
  quoteId: string,
): Promise<GrossanlassProcurementLine> {
  const response = await apiClient.post<GrossanlassProcurementLine>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/lines/${lineId}/quotes/${quoteId}/select`,
  )
  return response.data
}

export async function extractGrossanlassProcurementQuoteContact(
  departmentId: string,
  file: File,
): Promise<GrossanlassProcurementQuoteContactExtract> {
  const formData = new FormData()
  formData.append('pdf', file)
  const response = await apiClient.post<GrossanlassProcurementQuoteContactExtract>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/quotes/extract-contact`,
    formData,
    {
      transformRequest: [(data, headers) => {
        if (headers && typeof headers === 'object') {
          delete (headers as Record<string, unknown>)['Content-Type']
        }
        return data
      }],
    },
  )
  return response.data
}

export async function uploadGrossanlassProcurementQuotePdf(
  departmentId: string,
  lineId: string,
  quoteId: string,
  file: File,
): Promise<GrossanlassProcurementQuote> {
  const formData = new FormData()
  formData.append('pdf', file)
  const response = await apiClient.post<GrossanlassProcurementQuote>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/lines/${lineId}/quotes/${quoteId}/pdf`,
    formData,
    {
      transformRequest: [(data, headers) => {
        if (headers && typeof headers === 'object') {
          delete (headers as Record<string, unknown>)['Content-Type']
        }
        return data
      }],
    },
  )
  return response.data
}

export async function upsertGrossanlassProcurementOrder(
  departmentId: string,
  lineId: string,
  data: {
    cost_chf: number
    order_ref?: string | null
    notes?: string | null
    ordered_at?: string
  },
): Promise<GrossanlassProcurementLine> {
  const response = await apiClient.put<GrossanlassProcurementLine>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/lines/${lineId}/order`,
    data,
  )
  return response.data
}

export async function recordGrossanlassProcurementReceived(
  departmentId: string,
  lineId: string,
  data: {
    full?: boolean
    allocations?: Array<{ wish_line_id: string; quantity: number }>
  },
): Promise<GrossanlassProcurementLine> {
  const response = await apiClient.post<GrossanlassProcurementLine>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/lines/${lineId}/received`,
    data,
  )
  return response.data
}

export async function listGrossanlassBudgets(departmentId: string): Promise<GrossanlassBudget[]> {
  const response = await apiClient.get<GrossanlassBudget[]>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/budgets`,
  )
  return response.data
}

export async function listGrossanlassCosts(
  departmentId: string,
  params?: Record<string, string | undefined>,
): Promise<GrossanlassCost[]> {
  const response = await apiClient.get<GrossanlassCost[]>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/costs`,
    { params },
  )
  return response.data
}

export async function createGrossanlassCost(
  departmentId: string,
  data: GrossanlassCostPayload,
): Promise<GrossanlassCost> {
  const response = await apiClient.post<GrossanlassCost>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/costs`,
    data,
  )
  return response.data
}

export async function updateGrossanlassCost(
  departmentId: string,
  costId: string,
  data: GrossanlassCostPayload,
): Promise<GrossanlassCost> {
  const response = await apiClient.patch<GrossanlassCost>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/costs/${costId}`,
    data,
  )
  return response.data
}

export async function deleteGrossanlassCost(departmentId: string, costId: string): Promise<void> {
  await apiClient.delete(`/api/departments/${departmentId}/grossanlass/beschaffung/costs/${costId}`)
}

export function formatChf(amount: number | null | undefined): string {
  if (amount == null || Number.isNaN(amount)) return '–'
  return new Intl.NumberFormat('de-CH', { style: 'currency', currency: 'CHF' }).format(amount)
}
