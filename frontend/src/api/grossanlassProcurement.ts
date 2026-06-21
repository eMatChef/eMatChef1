import apiClient from './apiClient'
import type { GrossanlassWishKind } from './grossanlassWishes'

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
  label: string
  quantity: number
  location: string
  valid_from: string
  valid_to: string
  notes?: string | null
  created_by_name: string
  created_at: string
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
  status: GrossanlassProcurementStatus
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

export interface GrossanlassBedarfOverview {
  pool: GrossanlassProcurementPoolWish[]
  lines: GrossanlassProcurementLine[]
}

export interface GrossanlassProcurementOverview {
  totals: {
    line_count: number
    soll_chf: number
    ist_chf: number
    delta_chf: number
    open_quotes_count: number
    ordered_not_received_count: number
  }
  by_status: Record<string, number>
  by_group: Array<{
    group_id: string
    group_name: string
    soll_chf: number
    ist_chf: number
    line_count: number
  }>
}

export async function getGrossanlassBedarfOverview(departmentId: string): Promise<GrossanlassBedarfOverview> {
  const response = await apiClient.get<GrossanlassBedarfOverview>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/bedarf`,
  )
  return response.data
}

export async function updateGrossanlassBedarfWish(
  departmentId: string,
  wishLineId: string,
  data: Partial<{
    label: string
    quantity: number
    location: string
    notes: string | null
  }>,
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

export function formatChf(amount: number | null | undefined): string {
  if (amount == null || Number.isNaN(amount)) return '–'
  return new Intl.NumberFormat('de-CH', { style: 'currency', currency: 'CHF' }).format(amount)
}
