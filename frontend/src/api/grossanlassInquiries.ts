import apiClient from './apiClient'

export type GrossanlassInquiryStatus =
  | 'entwurf'
  | 'gesendet'
  | 'antwort'
  | 'zusage'
  | 'absage'
  | 'vorschlag'

export type GrossanlassInquiryThreadEntry = {
  who: 'ok' | 'firm' | string
  text: string
  at?: string
  from?: string
  subject?: string
  gmail_message_id?: string
}

export type GrossanlassGmailUnmatched = {
  id: string
  gmail_message_id: string
  gmail_thread_id: string
  from_email: string
  from_name: string
  subject: string
  body: string
  received_at: string
  gmail_open_url: string | null
}

export type GrossanlassGmailSyncResult = {
  updated: GrossanlassInquiry[]
  unmatched: GrossanlassGmailUnmatched[]
  ignored: number
}

export type GrossanlassInquiry = {
  id: string
  reference?: string
  name: string
  email: string
  place: string
  category_ids: string[]
  status: GrossanlassInquiryStatus
  tip_from: string | null
  tip_wish_id: string | null
  thread: GrossanlassInquiryThreadEntry[]
  gmail_draft_id?: string | null
  gmail_thread_id?: string | null
  gmail_message_id?: string | null
  gmail_open_url?: string | null
  created_at: string
  updated_at: string
}

export async function getGrossanlassInquiries(departmentId: string): Promise<GrossanlassInquiry[]> {
  const response = await apiClient.get<GrossanlassInquiry[]>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/anfragen`,
  )
  return response.data
}

export async function createGrossanlassInquiry(
  departmentId: string,
  data: { name: string; email?: string; place?: string; category_ids?: string[] | string; status?: GrossanlassInquiryStatus },
): Promise<GrossanlassInquiry> {
  const response = await apiClient.post<GrossanlassInquiry>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/anfragen`,
    data,
  )
  return response.data
}

export async function updateGrossanlassInquiry(
  departmentId: string,
  inquiryId: string,
  data: Partial<{ name: string; email: string; place: string; category_ids: string[]; status: GrossanlassInquiryStatus }>,
): Promise<GrossanlassInquiry> {
  const response = await apiClient.patch<GrossanlassInquiry>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/anfragen/${inquiryId}`,
    data,
  )
  return response.data
}

export async function importGrossanlassInquiryTips(departmentId: string): Promise<GrossanlassInquiry[]> {
  const response = await apiClient.post<GrossanlassInquiry[]>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/anfragen/from-tips`,
  )
  return response.data
}

export type GrossanlassInquiryCsvResult = {
  created: GrossanlassInquiry[]
  skipped: number
  errors: { line: number; message: string }[]
}

export async function importGrossanlassInquiryCsv(
  departmentId: string,
  csv: string,
): Promise<GrossanlassInquiryCsvResult> {
  const response = await apiClient.post<GrossanlassInquiryCsvResult>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/anfragen/import-csv`,
    { csv },
  )
  return {
    created: Array.isArray(response.data.created) ? response.data.created : [],
    skipped: Number(response.data.skipped) || 0,
    errors: Array.isArray(response.data.errors) ? response.data.errors : [],
  }
}

export async function markGrossanlassInquiriesSent(
  departmentId: string,
  ids: string[],
): Promise<GrossanlassInquiry[]> {
  const response = await apiClient.post<GrossanlassInquiry[]>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/anfragen/mark-sent`,
    { ids },
  )
  return response.data
}

export async function recordGrossanlassInquiryReply(
  departmentId: string,
  inquiryId: string,
  text?: string,
): Promise<GrossanlassInquiry> {
  const response = await apiClient.post<GrossanlassInquiry>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/anfragen/${inquiryId}/reply`,
    { text },
  )
  return response.data
}

export async function createGrossanlassInquiryDrafts(
  departmentId: string,
  ids: string[],
): Promise<GrossanlassInquiry[]> {
  const response = await apiClient.post<GrossanlassInquiry[]>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/anfragen/create-drafts`,
    { ids },
  )
  return response.data
}

export async function syncGrossanlassInquiryGmail(departmentId: string): Promise<GrossanlassGmailSyncResult> {
  const response = await apiClient.post<GrossanlassGmailSyncResult | GrossanlassInquiry[]>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/anfragen/sync-gmail`,
  )
  const data = response.data
  if (Array.isArray(data)) {
    return { updated: data, unmatched: [], ignored: 0 }
  }
  return {
    updated: Array.isArray(data.updated) ? data.updated : [],
    unmatched: Array.isArray(data.unmatched) ? data.unmatched : [],
    ignored: Number(data.ignored) || 0,
  }
}

export async function getGrossanlassGmailUnmatched(departmentId: string): Promise<GrossanlassGmailUnmatched[]> {
  const response = await apiClient.get<GrossanlassGmailUnmatched[]>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/anfragen/unmatched`,
  )
  return Array.isArray(response.data) ? response.data : []
}

export async function assignGrossanlassGmailUnmatched(
  departmentId: string,
  unmatchedId: string,
  inquiryId: string,
): Promise<{ inquiry: GrossanlassInquiry; unmatched: GrossanlassGmailUnmatched[] }> {
  const response = await apiClient.post<{ inquiry: GrossanlassInquiry; unmatched: GrossanlassGmailUnmatched[] }>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/anfragen/unmatched/${unmatchedId}/assign`,
    { inquiry_id: inquiryId },
  )
  return response.data
}

export async function discardGrossanlassGmailUnmatched(
  departmentId: string,
  unmatchedId: string,
): Promise<GrossanlassGmailUnmatched[]> {
  const response = await apiClient.post<GrossanlassGmailUnmatched[]>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/anfragen/unmatched/${unmatchedId}/discard`,
  )
  return Array.isArray(response.data) ? response.data : []
}

export async function unmatchedToGrossanlassInquiry(
  departmentId: string,
  unmatchedId: string,
  data: { name?: string; email?: string; place?: string } = {},
): Promise<{ inquiry: GrossanlassInquiry; unmatched: GrossanlassGmailUnmatched[] }> {
  const response = await apiClient.post<{ inquiry: GrossanlassInquiry; unmatched: GrossanlassGmailUnmatched[] }>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/anfragen/unmatched/${unmatchedId}/to-inquiry`,
    data,
  )
  return response.data
}

export async function createGrossanlassInquiryReplyDraft(
  departmentId: string,
  inquiryId: string,
  kind: string,
): Promise<GrossanlassInquiry> {
  const response = await apiClient.post<GrossanlassInquiry>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/anfragen/${inquiryId}/reply-draft`,
    { kind },
  )
  return response.data
}
