import apiClient from './apiClient'

export type GaGuestShareStatus = 'offered' | 'accepted' | 'declined' | 'completed'

export type GaGuestInventoryItem = {
  id: string
  name: string
  qty: number
  family: 'vehicle' | 'material'
  bookable: boolean
  released?: boolean
  share_id: string | null
  share_status: GaGuestShareStatus | null
}

export type GaGuestDepartment = {
  id: string
  name: string
  status: string
  items: GaGuestInventoryItem[]
}

export type GaGuestShare = {
  id: string
  kind: 'offer' | 'sale'
  status: GaGuestShareStatus
  guest_department_id: string
  guest_name: string
  name: string
  qty: number
  family: 'vehicle' | 'material'
  material_item_id: string | null
  commitment_id: string | null
  bookable: boolean
  from: string | null
  to: string | null
  from_label: string
  to_label: string
}

export type GaGuestSaleStock = {
  id: string
  name: string
  qty: number
  origin: string
  source: string
}

export type GaJsSubmitStatus = 'submitted' | 'missing'

export type GaGuestJsLine = {
  department_id: string
  department_name: string
  qty: number
  status: GaJsSubmitStatus
}

export type GaGuestJsArticle = {
  id: string
  name: string
  unit: string
  catalog_hint: string | null
  pdf_line_no: number | null
  lines: GaGuestJsLine[]
}

export type GaGaesteJs = {
  catalog_name: string | null
  articles: GaGuestJsArticle[]
}

export type GaGaestePayload = {
  departments: GaGuestDepartment[]
  offers: GaGuestShare[]
  sales: GaGuestShare[]
  sale_stock: GaGuestSaleStock[]
  js?: GaGaesteJs
}

export async function getGrossanlassGaeste(departmentId: string): Promise<GaGaestePayload> {
  const response = await apiClient.get<GaGaestePayload>(
    `/api/departments/${departmentId}/grossanlass/gaeste`,
  )
  return response.data
}

export async function listGrossanlassGuestReleases(
  guestDepartmentId: string,
  hostDepartmentId: string,
): Promise<{ items: GaGuestInventoryItem[]; releases: GaGuestShare[] }> {
  const response = await apiClient.get(
    `/api/departments/${guestDepartmentId}/grossanlass/hosts/${hostDepartmentId}/freigaben`,
  )
  return response.data
}

export async function releaseGrossanlassGuestMaterial(
  guestDepartmentId: string,
  hostDepartmentId: string,
  data: { material_item_id: string; qty?: number },
): Promise<{ items: GaGuestInventoryItem[]; releases: GaGuestShare[] }> {
  const response = await apiClient.post(
    `/api/departments/${guestDepartmentId}/grossanlass/hosts/${hostDepartmentId}/freigaben`,
    data,
  )
  return response.data
}

export async function acceptGrossanlassGuestLoan(
  departmentId: string,
  shareId: string,
): Promise<GaGaestePayload> {
  const response = await apiClient.post<GaGaestePayload>(
    `/api/departments/${departmentId}/grossanlass/gaeste/shares/${shareId}/accept`,
  )
  return response.data
}

export async function sellGrossanlassToGuest(
  departmentId: string,
  data: { guest_department_id: string; commitment_id: string; qty?: number },
): Promise<GaGaestePayload> {
  const response = await apiClient.post<GaGaestePayload>(
    `/api/departments/${departmentId}/grossanlass/gaeste/sales`,
    data,
  )
  return response.data
}
