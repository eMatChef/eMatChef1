import apiClient from './apiClient'
import type { ActivityMwNotification, ActivityMwNotificationsResponse } from './activityNotifications'

export type ActivityUserNotificationBucket = 'unread' | 'read' | 'all'

export async function getUserActivityStatusNotifications(
  departmentId: string,
  options?: { bucket?: ActivityUserNotificationBucket; limit?: number },
): Promise<ActivityMwNotificationsResponse> {
  const { data } = await apiClient.get<ActivityMwNotificationsResponse>(
    `/api/departments/${encodeURIComponent(departmentId)}/inbox/activity-status`,
    {
      params: {
        bucket: options?.bucket ?? 'all',
        limit: options?.limit ?? 100,
      },
    },
  )
  return data
}

export async function markUserActivityStatusNotificationRead(
  departmentId: string,
  notificationId: string,
): Promise<void> {
  await apiClient.patch(
    `/api/departments/${encodeURIComponent(departmentId)}/inbox/activity-status/${encodeURIComponent(notificationId)}/read`,
  )
}

export type { ActivityMwNotification }
