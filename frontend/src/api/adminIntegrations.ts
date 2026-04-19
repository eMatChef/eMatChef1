import apiClient from './apiClient'

export interface FcalIntegrationStatus {
  fcalApiKeyConfigured: boolean
}

export async function getFcalIntegration(): Promise<FcalIntegrationStatus> {
  const { data } = await apiClient.get<{ fcal_api_key_configured: boolean }>('/api/admin/integrations/fcal')
  return { fcalApiKeyConfigured: data.fcal_api_key_configured }
}

/** Nur Superadmin. Leerer String entfernt den gespeicherten Key. */
export async function saveFcalIntegration(fcalApiKey: string): Promise<FcalIntegrationStatus> {
  const { data } = await apiClient.put<{ fcal_api_key_configured: boolean }>('/api/admin/integrations/fcal', {
    fcal_api_key: fcalApiKey,
  })
  return { fcalApiKeyConfigured: data.fcal_api_key_configured }
}
