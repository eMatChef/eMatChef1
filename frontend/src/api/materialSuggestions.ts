import apiClient from './apiClient'

// ============== Types ==============

export interface MaterialSuggestion {
  material_item_id: string
  name: string
  usage_count: number
  avg_quantity: number
  last_used: string | null
  source: 'group_weekday' | 'group' | 'personal'
}

export interface MaterialSuggestionsResponse {
  suggestions: MaterialSuggestion[]
  meta: {
    department_id: string
    group_id: string | null
    day_of_week: number
    type: string
    count: number
  }
}

// ============== API ==============

export async function getMaterialSuggestions(params: {
  department_id: string
  group_id?: string | null
  day_of_week?: number
  type?: string
  limit?: number
  min_usage?: number
}): Promise<MaterialSuggestionsResponse> {
  const query = new URLSearchParams()
  query.set('department_id', params.department_id)
  if (params.group_id) query.set('group_id', params.group_id)
  if (params.day_of_week) query.set('day_of_week', String(params.day_of_week))
  if (params.type) query.set('type', params.type)
  if (params.limit) query.set('limit', String(params.limit))
  if (params.min_usage) query.set('min_usage', String(params.min_usage))

  const response = await apiClient.get(`/api/activities/material-suggestions?${query.toString()}`)
  return response.data
}
