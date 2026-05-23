import apiClient from './apiClient'

export interface ActivityMwNotification {
  id: string
  type: string
  activity_id: string
  activity_name: string
  activity_type: string
  activity_no?: number | null
  activity_status?: string | null
  group_id?: string | null
  group_name?: string | null
  creator_user_id: string
  creator_name: string
  creator_first_name?: string | null
  creator_last_name?: string | null
  creator_nickname?: string | null
  creator_avatar_initials?: string | null
  creator_background_color?: string | null
  creator_text_color?: string | null
  created_at: string
  read?: boolean
  issue_report_type?: string | null
  issue_report_quantity?: number | null
  material_name?: string | null
}

export interface ActivityMwNotificationsResponse {
  unread_count: number
  items: ActivityMwNotification[]
}

export type ActivityMwNotificationBucket = 'unread' | 'read' | 'all'

export async function getActivityMwNotifications(
  departmentId: string,
  options?: { bucket?: ActivityMwNotificationBucket; limit?: number },
): Promise<ActivityMwNotificationsResponse> {
  const { data } = await apiClient.get<ActivityMwNotificationsResponse>(
    '/api/activities/mw-notifications',
    {
      params: {
        department_id: departmentId,
        bucket: options?.bucket ?? 'unread',
        limit: options?.limit ?? 100,
      },
    },
  )
  return data
}

export async function markActivityMwNotificationRead(
  departmentId: string,
  notificationId: string,
): Promise<void> {
  await apiClient.patch(`/api/activities/mw-notifications/${notificationId}/read`, null, {
    params: { department_id: departmentId },
  })
}
