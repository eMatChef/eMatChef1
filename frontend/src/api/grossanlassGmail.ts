import apiClient, { absoluteApiUrl } from './apiClient'

export type GrossanlassGmailStatus = {
  oauth_configured: boolean
  redirect_uri: string
  connected: boolean
  email: string | null
  connected_at: string | null
  settings_path: string
}

export type GrossanlassMailTemplateKind = 'anfrage' | 'dank_absage' | 'zusage_ok' | 'nicht_genommen'

export type GrossanlassMailTemplate = {
  kind: GrossanlassMailTemplateKind | string
  subject: string
  body: string
}

export type GrossanlassMailPreview = {
  subject: string
  body: string
  to: string
  placeholders: Record<string, string>
}

export async function getGrossanlassGmailStatus(departmentId: string): Promise<GrossanlassGmailStatus> {
  const response = await apiClient.get<GrossanlassGmailStatus>(
    `/api/departments/${departmentId}/grossanlass/gmail/status`,
  )
  return response.data
}

export function grossanlassGmailConnectUrl(departmentId: string): string {
  return absoluteApiUrl(`/api/departments/${departmentId}/grossanlass/gmail/connect`)
}

export async function disconnectGrossanlassGmail(departmentId: string): Promise<GrossanlassGmailStatus> {
  const response = await apiClient.post<GrossanlassGmailStatus>(
    `/api/departments/${departmentId}/grossanlass/gmail/disconnect`,
  )
  return response.data
}

export async function getGrossanlassMailTemplates(departmentId: string): Promise<GrossanlassMailTemplate[]> {
  const response = await apiClient.get<GrossanlassMailTemplate[]>(
    `/api/departments/${departmentId}/grossanlass/gmail/templates`,
  )
  return response.data
}

export async function saveGrossanlassMailTemplates(
  departmentId: string,
  templates: GrossanlassMailTemplate[],
): Promise<GrossanlassMailTemplate[]> {
  const response = await apiClient.put<GrossanlassMailTemplate[]>(
    `/api/departments/${departmentId}/grossanlass/gmail/templates`,
    { templates },
  )
  return response.data
}

export async function previewGrossanlassMail(
  departmentId: string,
  data: { kind?: string; inquiry_id?: string },
): Promise<GrossanlassMailPreview> {
  const response = await apiClient.post<GrossanlassMailPreview>(
    `/api/departments/${departmentId}/grossanlass/gmail/preview`,
    data,
  )
  return response.data
}
