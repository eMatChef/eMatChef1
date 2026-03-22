import apiClient from './apiClient'

export type MailerTransportMode = 'smtp_json' | 'env' | 'file_spool'

export interface MailOutboundSettingsDto {
  from_address: string
  from_name: string | null
  uses_file: boolean
  env_fallback_address: string
  use_custom_smtp: boolean
  smtp_host: string
  smtp_port: number | null
  smtp_user: string
  smtp_encryption: string
  smtp_password_set: boolean
  mailer_transport_mode: MailerTransportMode
  mail_spool_path: string | null
  uses_file_spool: boolean
}

export interface MailOutboundSettingsPatch {
  from_address: string
  from_name?: string | null
  use_custom_smtp: boolean
  smtp_host?: string
  smtp_port?: number | null
  smtp_user?: string
  smtp_encryption?: string
  /** Nur setzen, wenn neu eingegeben — sonst weglassen, Passwort bleibt gespeichert */
  smtp_password?: string
}

export interface MailSendLogEntry {
  at: string
  kind: string
  to: string
  subject: string
}

export async function getMailSettings(departmentId?: string): Promise<MailOutboundSettingsDto> {
  const params = new URLSearchParams()
  if (departmentId) params.set('department_id', departmentId)
  const q = params.toString()
  const url = q ? `/api/mail/settings?${q}` : '/api/mail/settings'
  const { data } = await apiClient.get<MailOutboundSettingsDto>(url)
  return data
}

export async function patchMailSettings(
  body: MailOutboundSettingsPatch,
  departmentId?: string
): Promise<MailOutboundSettingsDto> {
  const params = new URLSearchParams()
  if (departmentId) params.set('department_id', departmentId)
  const q = params.toString()
  const url = q ? `/api/mail/settings?${q}` : '/api/mail/settings'
  const { data } = await apiClient.patch<MailOutboundSettingsDto>(url, body)
  return data
}

export async function getMailSendLog(limit: number, departmentId?: string): Promise<MailSendLogEntry[]> {
  const params = new URLSearchParams()
  params.set('limit', String(limit))
  if (departmentId) params.set('department_id', departmentId)
  const { data } = await apiClient.get<{ entries: MailSendLogEntry[] }>(`/api/mail/send-log?${params}`)
  return data.entries
}

export async function postMailTestSend(to: string): Promise<void> {
  await apiClient.post('/api/mail/test-send', { to })
}
