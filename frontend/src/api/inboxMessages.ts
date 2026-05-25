import apiClient from './apiClient'

export interface UserDirectMessage {
  id: string
  type: 'user_message'
  sender_user_id: string
  sender_name: string
  sender_first_name?: string | null
  sender_last_name?: string | null
  sender_nickname?: string | null
  sender_avatar_initials?: string | null
  sender_background_color?: string | null
  sender_text_color?: string | null
  subject: string
  message: string
  created_at: string
  read?: boolean
  read_at?: string | null
}

export interface UserDirectMessageSent extends UserDirectMessage {
  recipient_user_id: string
  recipient_name: string
  recipient_first_name?: string | null
  recipient_last_name?: string | null
  recipient_nickname?: string | null
  recipient_avatar_initials?: string | null
  recipient_background_color?: string | null
  recipient_text_color?: string | null
}

export interface UserDirectMessagesSentResponse {
  count: number
  items: UserDirectMessageSent[]
}

export interface UserDirectMessagesResponse {
  unread_count: number
  items: UserDirectMessage[]
}

export type InboxMessageBucket = 'unread' | 'read' | 'all'

export async function getUserDirectMessages(
  departmentId: string,
  options?: { bucket?: InboxMessageBucket; limit?: number },
): Promise<UserDirectMessagesResponse> {
  const { data } = await apiClient.get<UserDirectMessagesResponse>(
    `/api/departments/${encodeURIComponent(departmentId)}/inbox/messages`,
    {
      params: {
        bucket: options?.bucket ?? 'all',
        limit: options?.limit ?? 100,
      },
    },
  )
  return data
}

export async function sendUserDirectMessage(
  departmentId: string,
  payload: { recipient_user_id: string; subject: string; message: string },
): Promise<{ item: UserDirectMessage }> {
  const { data } = await apiClient.post<{ ok: boolean; item: UserDirectMessage }>(
    `/api/departments/${encodeURIComponent(departmentId)}/inbox/messages`,
    payload,
  )
  return { item: data.item }
}

export async function getUserDirectMessagesSent(
  departmentId: string,
  options?: { limit?: number },
): Promise<UserDirectMessagesSentResponse> {
  const { data } = await apiClient.get<UserDirectMessagesSentResponse>(
    `/api/departments/${encodeURIComponent(departmentId)}/inbox/messages/sent`,
    {
      params: {
        limit: options?.limit ?? 100,
      },
    },
  )
  return data
}

export async function markUserDirectMessageRead(
  departmentId: string,
  messageId: string,
): Promise<void> {
  await apiClient.patch(
    `/api/departments/${encodeURIComponent(departmentId)}/inbox/messages/${encodeURIComponent(messageId)}/read`,
  )
}
