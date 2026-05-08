import apiClient from './apiClient'

export type PublicFoundMessageStatus = 'open' | 'in_progress' | 'done'

export interface PublicFoundItemMessage {
  id: string
  entity_type: string
  material_id: string | null
  batch_id: string | null
  public_code: string
  material_name: string
  department_name: string
  serial_line: string | null
  message: string
  sender_name: string | null
  sender_email: string | null
  public_url: string
  status: PublicFoundMessageStatus
  created_at: string
  read_at: string | null
}

/**
 * mailto-URL für Antwort an den Finder (Betreff/Text nur kodiert, Adresse unverändert –
 * vollständig kodierte Adressen öffnen in Chrome/Outlook oft nicht zuverlässig).
 */
export function buildPublicFoundReplyMailto(msg: PublicFoundItemMessage): string {
  const email = msg.sender_email?.trim()
  if (!email) return ''
  const subject = encodeURIComponent(`QR-Kontakt: ${msg.material_name}`)
  const body = encodeURIComponent(
    `Hallo,\n\nbezüglich deiner Nachricht zum Material „${msg.material_name}“:\n\n`
  )
  const addr = encodeMailtoMailboxIfNeeded(email)
  return `mailto:${addr}?subject=${subject}&body=${body}`
}

function encodeMailtoMailboxIfNeeded(email: string): string {
  if (/^[^\s?&<>"']+@[^\s?&<>"']+$/.test(email)) {
    return email
  }
  return encodeURIComponent(email)
}

/**
 * Ruft den Standard-Mail-Client auf (expliziter Klick, zuverlässiger als rohes &lt;a href&gt; in SPAs).
 */
export function openPublicFoundReplyMailto(msg: PublicFoundItemMessage): void {
  const href = buildPublicFoundReplyMailto(msg)
  if (!href) return
  openMailtoHref(href)
}

export function openMailtoHref(href: string): void {
  try {
    const a = document.createElement('a')
    a.href = href
    a.rel = 'noopener noreferrer'
    a.style.display = 'none'
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
  } catch {
    window.location.assign(href)
  }
}

export async function getPublicFoundUnreadCount(departmentId: string): Promise<{ unread_count: number }> {
  const { data } = await apiClient.get<{ unread_count: number }>(
    `/api/departments/${encodeURIComponent(departmentId)}/public-found-messages/unread-count`
  )
  return data
}

export async function getPublicFoundMessages(
  departmentId: string,
  options?: { unreadOnly?: boolean; limit?: number; bucket?: 'open' | 'active' | 'done' | 'all' }
): Promise<{ count: number; unread_count: number; items: PublicFoundItemMessage[] }> {
  const limit = options?.limit ?? 50
  const params: Record<string, string> = { limit: String(limit) }
  if (options?.bucket !== undefined) {
    params.bucket = options.bucket
  } else {
    const unreadOnly = options?.unreadOnly !== false
    params.unread_only = unreadOnly ? '1' : '0'
  }
  const { data } = await apiClient.get<{
    count: number
    unread_count: number
    items: PublicFoundItemMessage[]
  }>(`/api/departments/${encodeURIComponent(departmentId)}/public-found-messages`, {
    params,
  })
  return data
}

export async function markPublicFoundMessageRead(
  departmentId: string,
  messageId: string
): Promise<{ item: PublicFoundItemMessage }> {
  const { data } = await apiClient.patch<{ ok: boolean; item: PublicFoundItemMessage }>(
    `/api/departments/${encodeURIComponent(departmentId)}/public-found-messages/${encodeURIComponent(messageId)}/read`
  )
  return { item: data.item }
}

export async function updatePublicFoundMessageStatus(
  departmentId: string,
  messageId: string,
  status: PublicFoundMessageStatus
): Promise<{ item: PublicFoundItemMessage }> {
  const { data } = await apiClient.patch<{ ok: boolean; item: PublicFoundItemMessage }>(
    `/api/departments/${encodeURIComponent(departmentId)}/public-found-messages/${encodeURIComponent(messageId)}`,
    { status }
  )
  return { item: data.item }
}
