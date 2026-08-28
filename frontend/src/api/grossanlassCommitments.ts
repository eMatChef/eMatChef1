import apiClient from './apiClient'

export type GrossanlassCommitmentFamily = 'vehicle' | 'material'
export type GrossanlassCommitmentOrigin = 'loan' | 'buy' | 'buy_resale'

export type GrossanlassCommitmentService = {
  id?: string
  kind: string
  fromIso?: string
  toIso?: string
  who?: string
  label?: string | null
}

export type GrossanlassCommitment = {
  id: string
  inquiry_id: string | null
  name: string
  family: GrossanlassCommitmentFamily
  origin: GrossanlassCommitmentOrigin
  source: string
  plate: string | null
  barcode: string | null
  category_id: string | null
  released: boolean
  present_from: string | null
  present_to: string | null
  handover_from: string | null
  handover_to: string | null
  return_from: string | null
  return_to: string | null
  wish_label: string | null
  wish_from: string | null
  wish_to: string | null
  services: GrossanlassCommitmentService[]
  quantity: number
  item_details: GrossanlassCommitmentItemDetails
  created_at: string
  updated_at: string
}

export type GrossanlassCommitmentPart = {
  name: string
  qty: number
}

export type GrossanlassCommitmentItemDetails = {
  weight?: string
  pack_unit?: string
  pack_size?: string
  notes?: string
  parts?: GrossanlassCommitmentPart[]
}

export type GrossanlassCommitmentPayload = {
  name: string
  source: string
  family?: GrossanlassCommitmentFamily
  origin?: GrossanlassCommitmentOrigin
  quantity?: number
  item_details?: GrossanlassCommitmentItemDetails
  plate?: string
  inquiry_id?: string
  category_id?: string | null
  released?: boolean
  present_from?: string | null
  present_to?: string | null
  handover_from?: string | null
  handover_to?: string | null
  return_from?: string | null
  return_to?: string | null
  wish_label?: string | null
  wish_from?: string | null
  wish_to?: string | null
  services?: GrossanlassCommitmentService[]
  cost_kind?: 'purchase' | 'rental' | 'loan' | 'buy_resale' | 'ancillary'
  payer_group_id?: string | null
  requesting_group_id?: string | null
  asset_treatment?: 'expense' | 'inventory' | null
  soll_chf?: number | null
  cash_out_chf?: number | null
  deposit_chf?: number | null
  proceeds_expected_chf?: number | null
}

export async function getGrossanlassCommitments(departmentId: string): Promise<GrossanlassCommitment[]> {
  const response = await apiClient.get<GrossanlassCommitment[]>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/zusagen`,
  )
  return response.data
}

export async function createGrossanlassCommitment(
  departmentId: string,
  data: GrossanlassCommitmentPayload,
): Promise<GrossanlassCommitment> {
  const response = await apiClient.post<GrossanlassCommitment>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/zusagen`,
    data,
  )
  return response.data
}

export async function updateGrossanlassCommitment(
  departmentId: string,
  id: string,
  data: Partial<GrossanlassCommitmentPayload>,
): Promise<GrossanlassCommitment> {
  const response = await apiClient.patch<GrossanlassCommitment>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/zusagen/${id}`,
    data,
  )
  return response.data
}

export async function createGrossanlassCommitmentFromInquiry(
  departmentId: string,
  inquiryId: string,
): Promise<GrossanlassCommitment> {
  const response = await apiClient.post<GrossanlassCommitment>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/zusagen/from-inquiry/${inquiryId}`,
  )
  return response.data
}
