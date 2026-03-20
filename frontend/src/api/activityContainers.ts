import apiClient from './apiClient'

export interface ActivityPackContainer {
  id: string
  activity_id: string
  container_batch_id: string | null
  label: string
  status: string
}

export interface ActivityPackContainerItem {
  id: string
  pack_container_id: string
  material_item_id: string
  material_batch_id: string | null
  quantity_packed: number
  quantity_issued: number
  quantity_returned: number
  condition_out: string
  notes: string | null
  material_name?: string
  serial_number?: string | null
  batch_label?: string | null
}

export async function getActivityPackContainers(activityId: string): Promise<ActivityPackContainer[]> {
  const response = await apiClient.get<ActivityPackContainer[]>(`/api/activities/${activityId}/pack-containers`)
  return response.data
}

export async function createActivityPackContainer(activityId: string, data: {
  label: string
  status?: string
  container_batch_id?: string | null
}): Promise<ActivityPackContainer> {
  const response = await apiClient.post<ActivityPackContainer>(`/api/activities/${activityId}/pack-containers`, data)
  return response.data
}

export async function updateActivityPackContainer(activityId: string, containerId: string, data: Partial<{
  label: string
  status: string
  container_batch_id: string | null
}>): Promise<ActivityPackContainer> {
  const response = await apiClient.patch<ActivityPackContainer>(`/api/activities/${activityId}/pack-containers/${containerId}`, data)
  return response.data
}

export async function deleteActivityPackContainer(activityId: string, containerId: string): Promise<void> {
  await apiClient.delete(`/api/activities/${activityId}/pack-containers/${containerId}`)
}

export async function getActivityPackContainerItems(activityId: string, containerId: string): Promise<ActivityPackContainerItem[]> {
  const response = await apiClient.get<ActivityPackContainerItem[]>(`/api/activities/${activityId}/pack-containers/${containerId}/items`)
  return response.data
}

export async function createActivityPackContainerItem(
  activityId: string,
  containerId: string,
  data: {
    material_item_id: string
    material_batch_id?: string | null
    quantity_packed?: number
    quantity_issued?: number
    quantity_returned?: number
    condition_out?: string
    notes?: string | null
  }
): Promise<ActivityPackContainerItem> {
  const response = await apiClient.post<ActivityPackContainerItem>(`/api/activities/${activityId}/pack-containers/${containerId}/items`, data)
  return response.data
}

export async function updateActivityPackContainerItem(
  activityId: string,
  containerId: string,
  itemId: string,
  data: Partial<{
    material_batch_id: string | null
    quantity_packed: number
    quantity_issued: number
    quantity_returned: number
    condition_out: string
    notes: string | null
  }>
): Promise<ActivityPackContainerItem> {
  const response = await apiClient.patch<ActivityPackContainerItem>(
    `/api/activities/${activityId}/pack-containers/${containerId}/items/${itemId}`,
    data
  )
  return response.data
}

export async function deleteActivityPackContainerItem(activityId: string, containerId: string, itemId: string): Promise<void> {
  await apiClient.delete(`/api/activities/${activityId}/pack-containers/${containerId}/items/${itemId}`)
}

