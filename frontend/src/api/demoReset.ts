import apiClient from './apiClient'

export type DemoResetStatus = {
  supported: boolean
  scenario: string | null
  label: string | null
  reason: string | null
}

export type DemoResetConfigChange = { from: unknown; to: unknown }

/** Plan eines Demo-Resets (Vorschau und Ausführung verwenden denselben Umfang). */
export type DemoResetPlan = {
  scenario: string
  department: { id: string; name: string }
  delete: {
    groups: { id: string; name: string }[]
    group_members: number
    group_shares: number
    places: number
    addresses: { id: string; name: string; type: string }[]
    join_requests: number
  }
  restore: {
    config: Record<string, DemoResetConfigChange>
    managed: string[]
    recreated: number
  }
  keep: Record<string, number | string[]> & { memberships_extra?: string[] }
  blocked: string[]
}

export type DemoResetPreview = {
  message: string
  plan: DemoResetPlan
  plan_hash: string
  notes: string[]
}

export async function getDemoResetStatus(departmentId: string): Promise<DemoResetStatus> {
  const response = await apiClient.get<DemoResetStatus>(`/api/departments/${departmentId}/demo-reset`)
  return response.data
}

/** Dry-Run: schreibt nichts, liefert den Plan und dessen Hash. */
export async function previewDemoReset(departmentId: string): Promise<DemoResetPreview> {
  const response = await apiClient.post<DemoResetPreview>(`/api/departments/${departmentId}/demo-reset/preview`)
  return response.data
}

/** Führt den Reset aus; bricht ab (HTTP 409), wenn sich der Datenstand seit der Vorschau geändert hat. */
export async function executeDemoReset(departmentId: string, scenario: string, planHash: string): Promise<DemoResetPreview> {
  const response = await apiClient.post<DemoResetPreview>(`/api/departments/${departmentId}/demo-reset`, {
    confirm: scenario,
    plan_hash: planHash,
  })
  return response.data
}
