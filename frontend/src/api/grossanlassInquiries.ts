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
}

export type GrossanlassInquiry = {
  id: string
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

export async function syncGrossanlassInquiryGmail(departmentId: string): Promise<GrossanlassInquiry[]> {
  const response = await apiClient.post<GrossanlassInquiry[]>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/anfragen/sync-gmail`,
  )
  return response.data
}
