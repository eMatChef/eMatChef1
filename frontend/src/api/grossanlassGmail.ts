import apiClient, { absoluteApiUrl } from './apiClient'

export type GrossanlassGmailStatus = {
  oauth_configured: boolean
  redirect_uri: string
  connected: boolean
  email: string | null
  connected_at: string | null
  settings_path: string
}

export type GrossanlassMailTemplateKind =
  | 'anfrage'
  | 'dank_absage'
  | 'zusage_ok'
  | 'nicht_genommen'
  | 'nehmen'

export const GROSSANLASS_MAIL_BUILTIN_PLACEHOLDERS = [
  'ANREDE',
  'FIRMA',
  'ANLASS',
  'ORT',
  'ZEITRAUMTEXT',
  'MATERIALLISTE',
  'ABSENDER',
  'REFERENZ',
  'EMAIL',
] as const

export const GROSSANLASS_MAIL_OPTIONAL_KINDS: GrossanlassMailTemplateKind[] = [
  'dank_absage',
  'zusage_ok',
  'nicht_genommen',
  'nehmen',
]

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

export type GrossanlassMailCustomPlaceholder = {
  key: string
  sample: string
}

export type GrossanlassMailTemplatePack = {
  templates: GrossanlassMailTemplate[]
  custom_placeholders: GrossanlassMailCustomPlaceholder[]
}

function unwrapTemplatePack(data: GrossanlassMailTemplate[] | GrossanlassMailTemplatePack): GrossanlassMailTemplatePack {
  if (Array.isArray(data)) {
    return { templates: data, custom_placeholders: [] }
  }
  return {
    templates: Array.isArray(data.templates) ? data.templates : [],
    custom_placeholders: Array.isArray(data.custom_placeholders) ? data.custom_placeholders : [],
  }
}

export async function getGrossanlassMailTemplates(departmentId: string): Promise<GrossanlassMailTemplatePack> {
  const response = await apiClient.get<GrossanlassMailTemplate[] | GrossanlassMailTemplatePack>(
    `/api/departments/${departmentId}/grossanlass/gmail/templates`,
  )
  return unwrapTemplatePack(response.data)
}

export async function saveGrossanlassMailTemplates(
  departmentId: string,
  templates: GrossanlassMailTemplate[],
  customPlaceholders: GrossanlassMailCustomPlaceholder[] = [],
): Promise<GrossanlassMailTemplatePack> {
  const response = await apiClient.put<GrossanlassMailTemplate[] | GrossanlassMailTemplatePack>(
    `/api/departments/${departmentId}/grossanlass/gmail/templates`,
    { templates, custom_placeholders: customPlaceholders },
  )
  return unwrapTemplatePack(response.data)
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
