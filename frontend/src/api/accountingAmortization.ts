import apiClient from '@/api/apiClient'

export type AmortizationSuggestion = {
  material_item_id: string
  material_name: string
  acquisition_value_chf: string
  useful_life_years: number
  suggested_annual_chf: string
  booked_amortization_chf: string
  remaining_suggestion_chf: string
}

export type AmortizationSuggestionsResponse = {
  year: number
  useful_life_years: number
  suggestions: AmortizationSuggestion[]
}

export async function getAmortizationSuggestions(
  departmentId: string,
  year: number,
  usefulLifeYears = 5,
): Promise<AmortizationSuggestionsResponse> {
  const { data } = await apiClient.get<AmortizationSuggestionsResponse>(
    `/api/departments/${departmentId}/accounting/amortization/suggestions`,
    { params: { year: String(year), useful_life_years: String(usefulLifeYears) } },
  )
  return data
}
