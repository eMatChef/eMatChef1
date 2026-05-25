export interface DisplayActivityRow {
  id: string
  name: string
  type: string
  status: string
  usage_start?: string | null
  usage_end?: string | null
  planning_start?: string | null
  planning_end?: string | null
  public_code?: string | null
  public_url?: string | null
}

export interface DisplayWorkshopTicketRow {
  id: string
  title: string
  priority: string
  priority_label: string
  status: string
  status_label: string
  created_at: string
  material_item: { id: string; name: string }
  public_code?: string | null
  public_url?: string | null
}

export interface DisplayStatistics {
  activities_by_status: Record<string, number>
  workshop_by_status: Record<string, number>
}

export interface DepartmentDisplayData {
  activities: DisplayActivityRow[]
  workshopTickets: DisplayWorkshopTicketRow[]
  department_name?: string
  screen_name?: string
  subtitle_text?: string | null
  show_activities?: boolean
  show_workshop?: boolean
  show_statistics?: boolean
  activity_types?: string[]
  activity_statuses?: string[]
  workshop_statuses?: string[]
  statistics?: DisplayStatistics | null
}
