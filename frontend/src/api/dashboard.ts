import apiClient from './apiClient'
import { getWorkshopStats, type WorkshopStats } from './workshop'
import { getPendingJoinRequests, getPendingAdminJoinRequests, type PendingJoinRequest, type PendingAdminJoinRequest } from './joinRequests'

export interface DashboardActivity {
  id: string
  name: string
  type: string
  status: string
  customer_name?: string | null
  group_name?: string | null
  usage_start?: string | null
  usage_end?: string | null
  item_count?: number
  total_price?: string | null
  planning_start?: string | null
  planning_end?: string | null
}

export interface DashboardData {
  activities: DashboardActivity[]
  activitiesUpcoming: DashboardActivity[]
  activitiesByStatus: Record<string, number>
  workshopStats: WorkshopStats | null
  pendingJoinRequests: PendingJoinRequest[]
  pendingAdminJoinRequests: PendingAdminJoinRequest[]
}

/**
 * Lädt alle Dashboard-Daten für ein Department
 */
export async function getDashboardData(departmentId: string): Promise<DashboardData> {
  const [activitiesRes, upcomingRes, workshopStats, pendingJoinRequests, pendingAdminJoinRequests] = await Promise.all([
    apiClient.get<DashboardActivity[]>('/api/activities', {
      params: { department_id: departmentId }
    }),
    apiClient.get<DashboardActivity[]>('/api/activities', {
      params: {
        department_id: departmentId,
        tab: 'upcoming'
      }
    }),
    getWorkshopStats(departmentId).catch(() => null),
    getPendingJoinRequests(departmentId).catch(() => []),
    getPendingAdminJoinRequests(departmentId).catch(() => [])
  ])

  const activities = activitiesRes.data || []
  const activitiesUpcoming = upcomingRes.data || []

  const activitiesByStatus: Record<string, number> = {}
  for (const a of activities) {
    activitiesByStatus[a.status] = (activitiesByStatus[a.status] || 0) + 1
  }

  return {
    activities,
    activitiesUpcoming,
    activitiesByStatus,
    workshopStats,
    pendingJoinRequests,
    pendingAdminJoinRequests
  }
}
