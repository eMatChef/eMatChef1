import apiClient from './apiClient'

export type GrossanlassCommitmentFamily = 'vehicle' | 'material'
export type GrossanlassCommitmentOrigin = 'loan' | 'buy' | 'buy_resale' | 'own' | 'donation'

export type GrossanlassCommitmentOwnerKind = 'grossanlass' | 'external' | 'department'

/** Eingangsstand aus den Charge-Bewegungen (Server berechnet). */
export type GrossanlassInboundState = 'none' | 'partial' | 'complete'

export type GrossanlassChargeMovementKind =
  | 'received'
  | 'returned_to_store'
  | 'to_workshop'
  | 'returned_to_owner'
  | 'sold_handover'
  | 'disposed'
  | 'consumed'
  | 'lost'

export type GrossanlassChargeMovement = {
  id: string
  commitment_id: string
  kind: GrossanlassChargeMovementKind
  quantity: number
  occurred_at: string
  place_id: string | null
  place_name: string | null
  einsatz_id: string | null
  note: string | null
  created_by_user_id: string | null
  created_by_name: string | null
  created_at: string
}

export type GrossanlassChargeMovementPayload = {
  kind?: 'received'
  quantity: number
  place_id?: string | null
  note?: string | null
  occurred_at?: string | null
}

export type GrossanlassChargeMovementResult = {
  movement: GrossanlassChargeMovement
  inbound: { expected: number; received: number; missing: number; state: GrossanlassInboundState }
  commitment: GrossanlassCommitment
}

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
  packed?: boolean
  returned_to_firm?: boolean
  return_required?: boolean
  owner_kind?: GrossanlassCommitmentOwnerKind
  owner_department_id?: string | null
  owner_department_name?: string | null
  procurement_line_id?: string | null
  received_quantity?: number
  missing_quantity?: number
  inbound_state?: GrossanlassInboundState
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
  from_line_id?: string
  absprache?: boolean
  inbound_status?: 'expected' | 'here'
  inbound_mode?: 'pickup' | 'delivery'
  quote_id?: string
  order_id?: string
  order_ref?: string
  qty_checked?: boolean
  pickup_einsatz_id?: string
  delivery_einsatz_id?: string
}

export type GrossanlassCommitmentPayload = {
  name: string
  source: string
  family?: GrossanlassCommitmentFamily
  origin?: GrossanlassCommitmentOrigin
  return_required?: boolean
  owner_kind?: GrossanlassCommitmentOwnerKind
  owner_department_id?: string | null
  procurement_line_id?: string | null
  quantity?: number
  item_details?: GrossanlassCommitmentItemDetails
  plate?: string
  barcode?: string | null
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

export async function deleteGrossanlassCommitment(
  departmentId: string,
  id: string,
): Promise<void> {
  await apiClient.delete(`/api/departments/${departmentId}/grossanlass/beschaffung/zusagen/${id}`)
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

export async function listGrossanlassChargeMovements(
  departmentId: string,
  commitmentId: string,
): Promise<GrossanlassChargeMovement[]> {
  const response = await apiClient.get<GrossanlassChargeMovement[]>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/zusagen/${commitmentId}/movements`,
  )
  return response.data
}

/** Wareneingang (Teilmenge) an einer Charge buchen. */
export async function recordGrossanlassChargeMovement(
  departmentId: string,
  commitmentId: string,
  data: GrossanlassChargeMovementPayload,
): Promise<GrossanlassChargeMovementResult> {
  const response = await apiClient.post<GrossanlassChargeMovementResult>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/zusagen/${commitmentId}/movements`,
    { kind: 'received', ...data },
  )
  return response.data
}
