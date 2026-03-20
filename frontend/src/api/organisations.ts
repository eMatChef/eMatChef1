import apiClient from './apiClient'

export interface Organisation {
  id: string
  name: string
}

/**
 * Lädt alle Organisationen
 */
export async function getOrganisations(): Promise<Organisation[]> {
  const response = await apiClient.get<Organisation[]>('/api/organisations')
  return response.data
}

/**
 * Lädt eine einzelne Organisation
 */
export async function getOrganisation(id: string): Promise<Organisation> {
  const response = await apiClient.get<Organisation>(`/api/organisations/${id}`)
  return response.data
}

export interface CreateOrganisationRequest {
  name: string
}

export interface UpdateOrganisationRequest {
  name?: string
}

/**
 * Erstellt eine neue Organisation
 */
export async function createOrganisation(data: CreateOrganisationRequest): Promise<Organisation> {
  const response = await apiClient.post<Organisation>('/api/organisations', data)
  return response.data
}

/**
 * Aktualisiert eine Organisation
 */
export async function updateOrganisation(id: string, data: UpdateOrganisationRequest): Promise<Organisation> {
  const response = await apiClient.patch<Organisation>(`/api/organisations/${id}`, data)
  return response.data
}
