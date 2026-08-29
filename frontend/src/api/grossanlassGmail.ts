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
  | 'praezisieren'
  | 'dank_absage'
  | 'zusage_ok'
  | 'nicht_genommen'
  | 'nehmen'
  | 'nachfassen'

export const GROSSANLASS_MAIL_OPTIONAL_KINDS: GrossanlassMailTemplateKind[] = [
  'praezisieren',
  'dank_absage',
  'zusage_ok',
  'nicht_genommen',
  'nehmen',
  'nachfassen',
]

export const GROSSANLASS_MAIL_BUILTIN_PLACEHOLDERS = [
  'ANREDE',
  'FIRMA',
  'ANLASS',
  'ORT',
  'ZEITRAUMTEXT',
  'MATERIALLISTE',
  'BEREICHE',
  'ABSENDER',
  'REFERENZ',
  'EMAIL',
  'WEBSEITE',
  'WAS',
  'HINWEISE',
  'VORNAME',
  'NACHNAME',
  'KONTAKT',
  'TELEFON',
] as const

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
  attachment_filename?: string | null
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

export type GrossanlassGmailRouting = {
  label_root: string
  label_inquiries: string
  label_waiting: string
  label_replied: string
  label_by_package: boolean
  extra_labels: string[]
  reference_prefix: string
}

export const GROSSANLASS_GMAIL_ROUTING_DEFAULTS: GrossanlassGmailRouting = {
  label_root: 'eMatChef',
  label_inquiries: 'Firmenanfragen',
  label_waiting: 'Status/Wartet auf Antwort',
  label_replied: 'Status/Antwort erhalten',
  label_by_package: true,
  extra_labels: [],
  reference_prefix: '',
}

export type GrossanlassMailTemplatePack = {
  templates: GrossanlassMailTemplate[]
  custom_placeholders: GrossanlassMailCustomPlaceholder[]
  gmail_routing: GrossanlassGmailRouting
}

function unwrapRouting(raw: unknown): GrossanlassGmailRouting {
  const row = raw && typeof raw === 'object' ? (raw as Record<string, unknown>) : {}
  const extra = Array.isArray(row.extra_labels)
    ? row.extra_labels.map((item) => String(item)).filter(Boolean)
    : []
  return {
    label_root: String(row.label_root ?? GROSSANLASS_GMAIL_ROUTING_DEFAULTS.label_root),
    label_inquiries: String(row.label_inquiries ?? GROSSANLASS_GMAIL_ROUTING_DEFAULTS.label_inquiries),
    label_waiting: String(row.label_waiting ?? GROSSANLASS_GMAIL_ROUTING_DEFAULTS.label_waiting),
    label_replied: String(row.label_replied ?? GROSSANLASS_GMAIL_ROUTING_DEFAULTS.label_replied),
    label_by_package: row.label_by_package !== false,
    extra_labels: extra,
    reference_prefix: String(row.reference_prefix ?? ''),
  }
}

function unwrapTemplatePack(data: GrossanlassMailTemplate[] | GrossanlassMailTemplatePack): GrossanlassMailTemplatePack {
  if (Array.isArray(data)) {
    return {
      templates: data,
      custom_placeholders: [],
      gmail_routing: { ...GROSSANLASS_GMAIL_ROUTING_DEFAULTS },
    }
  }
  return {
    templates: Array.isArray(data.templates) ? data.templates : [],
    custom_placeholders: Array.isArray(data.custom_placeholders) ? data.custom_placeholders : [],
    gmail_routing: unwrapRouting(data.gmail_routing),
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
  gmailRouting: GrossanlassGmailRouting = GROSSANLASS_GMAIL_ROUTING_DEFAULTS,
): Promise<GrossanlassMailTemplatePack> {
  const response = await apiClient.put<GrossanlassMailTemplate[] | GrossanlassMailTemplatePack>(
    `/api/departments/${departmentId}/grossanlass/gmail/templates`,
    { templates, custom_placeholders: customPlaceholders, gmail_routing: gmailRouting },
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

export type GrossanlassMailBatchPreview = GrossanlassMailPreview & { inquiry_id: string }

export async function previewGrossanlassMails(
  departmentId: string,
  inquiryIds: string[],
  kind = 'anfrage',
): Promise<GrossanlassMailBatchPreview[]> {
  const response = await apiClient.post<GrossanlassMailBatchPreview[]>(
    `/api/departments/${departmentId}/grossanlass/gmail/preview-batch`,
    { inquiry_ids: inquiryIds, kind },
  )
  return Array.isArray(response.data) ? response.data : []
}

export type GrossanlassGmailLabelRow = {
  name: string
  exists: boolean
  gmail_id: string | null
}

export type GrossanlassGmailLabelOverview = {
  labels: GrossanlassGmailLabelRow[]
  gmail_labels: string[]
  unused_gmail_labels: string[]
  suggested_root: string
  gmail_routing: GrossanlassGmailRouting
  category_names?: string[]
  categories_imported?: number
}

function unwrapLabelOverview(data: Partial<GrossanlassGmailLabelOverview>): GrossanlassGmailLabelOverview {
  return {
    labels: Array.isArray(data.labels) ? data.labels : [],
    gmail_labels: Array.isArray(data.gmail_labels) ? data.gmail_labels : [],
    unused_gmail_labels: Array.isArray(data.unused_gmail_labels)
      ? data.unused_gmail_labels.map((item) => String(item))
      : [],
    suggested_root: String(data.suggested_root ?? ''),
    gmail_routing: unwrapRouting(data.gmail_routing),
    category_names: Array.isArray(data.category_names)
      ? data.category_names.map((item) => String(item))
      : [],
    categories_imported: Number(data.categories_imported) || 0,
  }
}

export async function getGrossanlassGmailLabels(departmentId: string): Promise<GrossanlassGmailLabelOverview> {
  const response = await apiClient.get<Partial<GrossanlassGmailLabelOverview>>(
    `/api/departments/${departmentId}/grossanlass/gmail/labels`,
  )
  return unwrapLabelOverview(response.data)
}

export async function importGrossanlassGmailLabels(
  departmentId: string,
  root: string,
): Promise<GrossanlassGmailLabelOverview> {
  const response = await apiClient.post<Partial<GrossanlassGmailLabelOverview>>(
    `/api/departments/${departmentId}/grossanlass/gmail/labels/import`,
    { root },
  )
  return unwrapLabelOverview(response.data)
}

export async function syncGrossanlassGmailLabels(
  departmentId: string,
): Promise<GrossanlassGmailLabelOverview & { created: number; renamed: number }> {
  const response = await apiClient.post<Partial<GrossanlassGmailLabelOverview> & { created?: number; renamed?: number }>(
    `/api/departments/${departmentId}/grossanlass/gmail/labels/sync`,
  )
  return {
    ...unwrapLabelOverview(response.data),
    created: Number(response.data.created) || 0,
    renamed: Number(response.data.renamed) || 0,
  }
}
