import apiClient from '@/api/apiClient'
import type { MediaPhoto } from '@/api/media'

export type DepartmentMediaKind = 'photos' | 'documents'

export type DepartmentMediaLink = {
  kind: string
  label: string
  path: string
}

export type DepartmentMediaItem = MediaPhoto & {
  kind: DepartmentMediaKind
  context: string
  context_id: string
  context_label: string
  source_path: string
  can_replace?: boolean
  can_rename?: boolean
  links?: DepartmentMediaLink[]
}

export type DepartmentMediaList = {
  items: DepartmentMediaItem[]
  total: number
}

export async function listDepartmentMedia(
  departmentId: string,
  params?: { kind?: DepartmentMediaKind | ''; context?: string; q?: string },
): Promise<DepartmentMediaList> {
  const { data } = await apiClient.get<DepartmentMediaList>(
    `/api/departments/${departmentId}/media`,
    { params: params || {} },
  )
  return data
}

export async function replaceDepartmentMedia(
  departmentId: string,
  item: Pick<DepartmentMediaItem, 'context' | 'context_id' | 'filename'>,
  file: File,
): Promise<DepartmentMediaItem> {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('context', item.context)
  formData.append('context_id', item.context_id)
  formData.append('filename', item.filename || '')

  const { data } = await apiClient.post<{ item: DepartmentMediaItem }>(
    `/api/departments/${departmentId}/media/replace`,
    formData,
    {
      transformRequest: [(payload, headers) => {
        if (headers && typeof headers === 'object') {
          delete (headers as Record<string, unknown>)['Content-Type']
        }
        return payload
      }],
    },
  )
  return data.item
}

export async function renameDepartmentMedia(
  departmentId: string,
  item: Pick<DepartmentMediaItem, 'context' | 'context_id' | 'filename'>,
  originalFilename: string,
): Promise<DepartmentMediaItem> {
  const { data } = await apiClient.patch<{ item: DepartmentMediaItem }>(
    `/api/departments/${departmentId}/media/rename`,
    {
      context: item.context,
      context_id: item.context_id,
      filename: item.filename || '',
      original_filename: originalFilename,
    },
  )
  return data.item
}
