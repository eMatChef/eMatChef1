import apiClient from './apiClient'

export type InventoryTaskStatus = 'open' | 'in_progress' | 'completed' | 'cancelled'

export interface InventoryTaskLine {
  id: string
  material_item_id?: string | null
  material_name?: string | null
  expected_qty: number
  counted_qty?: number | null
  notes?: string | null
}

export interface InventoryTaskLinesJson {
  lines: InventoryTaskLine[]
}

export interface InventoryTask {
  id: string
  department_id: string
  title: string
  status: InventoryTaskStatus
  status_label: string
  workshop_ticket_id: string | null
  lines_json?: InventoryTaskLinesJson
  created_by_user_id?: string | null
  created_at: string
  updated_at: string
}

export interface CreateInventoryTaskRequest {
  department_id: string
  title: string
  status?: InventoryTaskStatus
  lines_json?: InventoryTaskLinesJson
  workshop_ticket_id?: string | null
}

export interface UpdateInventoryTaskRequest {
  title?: string
  status?: InventoryTaskStatus
  lines_json?: InventoryTaskLinesJson
  workshop_ticket_id?: string | null
}

export async function listInventoryTasks(
  departmentId: string,
  opts?: { status?: InventoryTaskStatus; workshop_ticket_id?: string },
): Promise<InventoryTask[]> {
  const { data } = await apiClient.get<{ tasks: InventoryTask[] }>('/api/inventory-tasks', {
    params: {
      department_id: departmentId,
      status: opts?.status,
      workshop_ticket_id: opts?.workshop_ticket_id,
    },
  })
  return data.tasks ?? []
}

export async function getInventoryTask(id: string): Promise<InventoryTask> {
  const { data } = await apiClient.get<{ task: InventoryTask }>(`/api/inventory-tasks/${id}`)
  return data.task
}

export async function createInventoryTask(payload: CreateInventoryTaskRequest): Promise<InventoryTask> {
  const { data } = await apiClient.post<{ task: InventoryTask }>('/api/inventory-tasks', payload)
  return data.task
}

export async function updateInventoryTask(
  id: string,
  payload: UpdateInventoryTaskRequest,
): Promise<InventoryTask> {
  const { data } = await apiClient.patch<{ task: InventoryTask }>(`/api/inventory-tasks/${id}`, payload)
  return data.task
}

export async function deleteInventoryTask(id: string): Promise<void> {
  await apiClient.delete(`/api/inventory-tasks/${id}`)
}
