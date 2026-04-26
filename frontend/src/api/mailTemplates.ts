import apiClient from './apiClient'

export interface MailTemplateDefinition {
  key: string
  title: string
  subject: string
  description: string
  body_preview: string
}

export type MailTemplateMessages = Record<
  string,
  {
    subject?: string
    text_body?: string
    html?: Record<string, string>
  }
>

export const MAIL_TEMPLATE_LOCALES = ['de', 'en', 'fr', 'it'] as const
export type MailTemplateLocale = (typeof MAIL_TEMPLATE_LOCALES)[number]

export async function getMailTemplates(
  departmentId?: string,
  locale: MailTemplateLocale = 'de'
): Promise<MailTemplateDefinition[]> {
  const params = new URLSearchParams()
  params.set('locale', locale)
  if (departmentId) {
    params.set('department_id', departmentId)
  }
  const { data } = await apiClient.get<MailTemplateDefinition[]>(`/api/mail-templates?${params.toString()}`)
  return data
}

export async function getMailTemplateMessages(locale: MailTemplateLocale): Promise<{
  locale: string
  messages: MailTemplateMessages
}> {
  const params = new URLSearchParams()
  params.set('locale', locale)
  const { data } = await apiClient.get<{ locale: string; messages: MailTemplateMessages }>(
    `/api/mail-templates/messages?${params.toString()}`
  )
  return data
}

export async function putMailTemplateMessages(
  locale: MailTemplateLocale,
  messages: MailTemplateMessages
): Promise<{ ok: boolean; locale: string }> {
  const { data } = await apiClient.put<{ ok: boolean; locale: string }>('/api/mail-templates/messages', {
    locale,
    messages,
  })
  return data
}
