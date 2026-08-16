import type { JsListPhase } from '@/utils/activityJsListStatus'

/** Listen-Zeile Aktivitäten-Übersicht (API → View). */
export interface ActivityListItem {
  id: string
  no?: string
  name: string
  departmentId?: string
  departmentName?: string
  type: 'activity' | 'camp' | 'event' | 'external'
  status:
    | 'draft'
    | 'submitted'
    | 'approved'
    | 'packing'
    | 'packed'
    | 'at_event'
    | 'returned'
    | 'completed'
    | 'cancelled'
  invitedDepartments?: Array<{ id?: string; name?: string; organisation_name?: string; status?: string }>
  groupId?: string | null
  groupName?: string
  usageStart?: string
  usageEnd?: string
  itemCount?: number
  totalPrice?: number
  wantsJsMaterial?: boolean
  jsListPhase?: JsListPhase | null
  /** Onboarding Hybrid-Sandbox Übungsfall */
  onboardingSandbox?: boolean
  createdAt: string
  updatedAt: string
}
