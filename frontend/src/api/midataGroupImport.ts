import apiClient from './apiClient'

export interface MiDataImportNode {
  external_group_id: string
  parent_external_group_id: string
  name: string
  type: string
  imported: boolean
  group_id: string | null
}

export interface MiDataGroupImportState {
  available: boolean
  has_mappings: boolean
  tree: MiDataImportNode[] | null
}

export async function getMiDataGroupImport(departmentId: string): Promise<MiDataGroupImportState> {
  const response = await apiClient.get<MiDataGroupImportState>(
    `/api/departments/${encodeURIComponent(departmentId)}/midata-group-import`,
  )
  return response.data
}

export async function importMiDataGroups(
  departmentId: string,
  externalGroupIds: string[],
): Promise<{ status: string; imported: number; skipped: number }> {
  const response = await apiClient.post(
    `/api/departments/${encodeURIComponent(departmentId)}/midata-group-import`,
    { external_group_ids: externalGroupIds },
  )
  return response.data
}
