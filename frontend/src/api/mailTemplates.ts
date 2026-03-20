import apiClient from './apiClient'

export interface MailTemplateDefinition {
  key: string
  title: string
  subject: string
  description: string
  body_preview: string
}

export async function getMailTemplates(departmentId?: string): Promise<MailTemplateDefinition[]> {
  const params = new URLSearchParams()
  if (departmentId) {
    params.set('department_id', departmentId)
  }
  const query = params.toString()
  const url = query ? `/api/mail-templates?${query}` : '/api/mail-templates'
  const { data } = await apiClient.get<MailTemplateDefinition[]>(url)
  return data
}
