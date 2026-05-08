import apiClient from './apiClient'
import { getWorkshopStats, type WorkshopStats } from './workshop'
import { getPendingJoinRequests, getPendingAdminJoinRequests, type PendingJoinRequest, type PendingAdminJoinRequest } from './joinRequests'

export interface DashboardActivity {
  id: string
  name: string
  type: string
  status: string
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

export interface GetDashboardDataOptions {
  /** Nur für SA/OrgChef/SubOrgChef; join-request-Listen sonst leer lassen (keine unnötigen API-Calls). */
  includeJoinRequests?: boolean
}

/**
 * Lädt alle Dashboard-Daten für ein Department
 */
export async function getDashboardData(
  departmentId: string,
  options?: GetDashboardDataOptions
): Promise<DashboardData> {
  const includeJoinRequests = options?.includeJoinRequests === true
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
    includeJoinRequests ? getPendingJoinRequests(departmentId).catch(() => []) : Promise.resolve([]),
    includeJoinRequests ? getPendingAdminJoinRequests(departmentId).catch(() => []) : Promise.resolve([])
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
