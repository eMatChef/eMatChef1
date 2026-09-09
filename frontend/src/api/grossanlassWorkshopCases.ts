import apiClient from './apiClient'

export type GrossanlassWorkshopOrigin = 'own' | 'loan' | 'buy'
export type GrossanlassWorkshopPath = 'repair' | 'owner'
export type GrossanlassWorkshopStatus = 'open' | 'in_progress' | 'waiting_owner' | 'done' | 'cancelled'

export type GrossanlassWorkshopCase = {
  id: string
  title: string
  description: string
  origin: GrossanlassWorkshopOrigin
  owner_firm_name: string
  material_label: string
  path: GrossanlassWorkshopPath
  status: GrossanlassWorkshopStatus
  created_by_id: string
  created_by_name: string | null
  created_at: string
  updated_at: string
}

export type GrossanlassWorkshopCasePayload = {
  title?: string
  description?: string
  origin?: GrossanlassWorkshopOrigin
  owner_firm_name?: string
  material_label?: string
  path?: GrossanlassWorkshopPath
  status?: GrossanlassWorkshopStatus
}

export async function getGrossanlassWorkshopCases(
  departmentId: string,
): Promise<GrossanlassWorkshopCase[]> {
  const response = await apiClient.get<GrossanlassWorkshopCase[]>(
    `/api/departments/${departmentId}/grossanlass/workshop-cases`,
  )
  return response.data
}

export async function createGrossanlassWorkshopCase(
  departmentId: string,
  data: GrossanlassWorkshopCasePayload,
): Promise<GrossanlassWorkshopCase> {
  const response = await apiClient.post<GrossanlassWorkshopCase>(
    `/api/departments/${departmentId}/grossanlass/workshop-cases`,
    data,
  )
  return response.data
}

export async function updateGrossanlassWorkshopCase(
  departmentId: string,
  caseId: string,
  data: GrossanlassWorkshopCasePayload,
): Promise<GrossanlassWorkshopCase> {
  const response = await apiClient.patch<GrossanlassWorkshopCase>(
    `/api/departments/${departmentId}/grossanlass/workshop-cases/${caseId}`,
    data,
  )
  return response.data
}
