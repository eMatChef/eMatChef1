import apiClient from './apiClient'

export type GrossanlassRoundStatus = 'scheduled' | 'open' | 'closed'
export type GrossanlassRoundType = 'ressort_wuensche'
export type GrossanlassFormPurpose = 'material_wish' | 'company_tip' | 'free'
export type GrossanlassMaterialStage = 'grob' | 'fein'

export interface GrossanlassPlanningRound {
  id: string
  activity_id: string
  name: string
  round_type: GrossanlassRoundType
  form_purpose: GrossanlassFormPurpose
  material_stage: GrossanlassMaterialStage | null
  status: GrossanlassRoundStatus
  opens_at: string | null
  closes_at: string | null
  use_auto_schedule: boolean
  opened_at: string | null
  closed_at: string | null
  created_by_user_id: string
  created_at: string
  updated_at: string
}

export async function getGrossanlassPlanningRounds(
  departmentId: string,
): Promise<GrossanlassPlanningRound[]> {
  const response = await apiClient.get<GrossanlassPlanningRound[]>(
    `/api/departments/${departmentId}/grossanlass/planung/rounds`,
  )
  return response.data
}

export async function createGrossanlassPlanningRound(
  departmentId: string,
  data: {
    name: string
    round_type?: GrossanlassRoundType
    form_purpose?: GrossanlassFormPurpose
    material_stage?: GrossanlassMaterialStage | null
    opens_at?: string | null
    closes_at?: string | null
    use_auto_schedule?: boolean
  },
): Promise<GrossanlassPlanningRound> {
  const response = await apiClient.post<GrossanlassPlanningRound>(
    `/api/departments/${departmentId}/grossanlass/planung/rounds`,
    data,
  )
  return response.data
}

export async function updateGrossanlassPlanningRound(
  departmentId: string,
  roundId: string,
  data: {
    name?: string
    opens_at?: string | null
    closes_at?: string | null
    use_auto_schedule?: boolean
  },
): Promise<GrossanlassPlanningRound> {
  const response = await apiClient.put<GrossanlassPlanningRound>(
    `/api/departments/${departmentId}/grossanlass/planung/rounds/${roundId}`,
    data,
  )
  return response.data
}

export async function openGrossanlassPlanningRound(
  departmentId: string,
  roundId: string,
): Promise<GrossanlassPlanningRound> {
  const response = await apiClient.post<GrossanlassPlanningRound>(
    `/api/departments/${departmentId}/grossanlass/planung/rounds/${roundId}/open`,
  )
  return response.data
}

export async function closeGrossanlassPlanningRound(
  departmentId: string,
  roundId: string,
): Promise<GrossanlassPlanningRound> {
  const response = await apiClient.post<GrossanlassPlanningRound>(
    `/api/departments/${departmentId}/grossanlass/planung/rounds/${roundId}/close`,
  )
  return response.data
}

export async function reopenGrossanlassPlanningRound(
  departmentId: string,
  roundId: string,
): Promise<GrossanlassPlanningRound> {
  const response = await apiClient.post<GrossanlassPlanningRound>(
    `/api/departments/${departmentId}/grossanlass/planung/rounds/${roundId}/reopen`,
  )
  return response.data
}
